<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Résolution IP → localisation (ville, code postal, région, pays) et opérateur,
 * via l'API gratuite ip-api.com (sans clé, batch jusqu'à 100 IP, 15 req/min).
 *
 * Les résultats sont mis en cache par IP : une même adresse n'est interrogée
 * qu'une fois par mois. Les IP privées/locales sont ignorées.
 *
 * resolveMany() renvoie un statut par IP pour que l'appelant sache quoi faire :
 *   - 'ok'   : géo trouvée (clé 'geo' présente) → à enregistrer ;
 *   - 'skip' : IP non géolocalisable (privée/réservée, ou refusée par l'API)
 *              → à horodater pour ne pas réinterroger en boucle ;
 *   - 'fail' : échec transient (API injoignable, quota épuisé, non tentée)
 *              → NE PAS horodater, réessayer plus tard.
 */
class GeoIpLocator
{
    private const ENDPOINT = 'http://ip-api.com/batch';

    private const FIELDS = 'status,country,regionName,city,zip,isp,query';

    private const CACHE_TTL_DAYS = 30;

    /** État du quota restant communiqué par ip-api (X-Rl / X-Ttl). */
    private const BUDGET_CACHE_KEY = 'geoip:budget';

    /**
     * Résout un lot d'IP.
     *
     * @param  iterable<string>  $ips
     * @return array<string, array{status: string, geo?: array<string, string|null>}>
     */
    public static function resolveMany(iterable $ips): array
    {
        $results = [];
        $toLookup = [];

        foreach ($ips as $ip) {
            $ip = trim((string) $ip);
            if ($ip === '' || isset($results[$ip]) || isset($toLookup[$ip])) {
                continue;
            }

            if (! self::isPublic($ip)) {
                // IP privée/réservée : jamais géolocalisable.
                $results[$ip] = ['status' => 'skip'];

                continue;
            }

            $toLookup[$ip] = true;
        }

        $toFetch = [];
        foreach (array_keys($toLookup) as $ip) {
            $cached = Cache::get(self::cacheKey($ip));
            if ($cached !== null) {
                $results[$ip] = ['status' => 'ok', 'geo' => $cached];
            } else {
                $toFetch[] = $ip;
            }
        }

        // ip-api.com limite le batch à 100 IP par requête.
        foreach (array_chunk($toFetch, 100) as $chunk) {
            // Quota épuisé : on n'envoie pas (évite un 429). Ces IP seront
            // marquées en échec et réessayées au prochain passage.
            if (! self::hasBudget()) {
                foreach ($chunk as $ip) {
                    $results[$ip] = ['status' => 'fail'];
                }

                continue;
            }

            [$resolved, $failed] = self::fetchBatch($chunk);

            foreach ($chunk as $ip) {
                if (array_key_exists($ip, $resolved)) {
                    $geo = $resolved[$ip];
                    Cache::put(self::cacheKey($ip), $geo, now()->addDays(self::CACHE_TTL_DAYS));
                    $results[$ip] = ['status' => 'ok', 'geo' => $geo];
                } elseif ($failed) {
                    // Échec transport (réseau/quota) : réessai plus tard.
                    $results[$ip] = ['status' => 'fail'];
                } else {
                    // L'API a répondu mais cette IP est refusée (range réservé,
                    // requête invalide) : inutile de réessayer.
                    $results[$ip] = ['status' => 'skip'];
                }
            }
        }

        return $results;
    }

    /** Résout une seule IP, ou null si non géolocalisée / indisponible. */
    public static function resolve(string $ip): ?array
    {
        $result = self::resolveMany([$ip])[$ip] ?? null;

        return ($result['status'] ?? null) === 'ok' ? $result['geo'] : null;
    }

    /**
     * @param  array<int, string>  $ips
     * @return array{0: array<string, array<string, string|null>>, 1: bool}
     *         [résolues, échecTransport]
     */
    private static function fetchBatch(array $ips): array
    {
        if ($ips === []) {
            return [[], false];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(8)
                ->post(self::ENDPOINT.'?fields='.self::FIELDS, $ips);
        } catch (\Throwable $e) {
            Log::warning('GeoIp injoignable', ['error' => $e->getMessage()]);

            return [[], true];
        }

        // Mémorise le quota restant pour caler les requêtes suivantes.
        self::rememberBudget($response->header('X-Rl'), $response->header('X-Ttl'));

        if ($response->status() === 429) {
            // Quota dépassé : si ip-api n'a pas renseigné le délai, pause par défaut.
            if ($response->header('X-Rl') === null) {
                Cache::put(self::BUDGET_CACHE_KEY, ['remaining' => 0], now()->addSeconds(60));
            }
            Log::warning('GeoIp quota dépassé (429)');

            return [[], true];
        }

        if ($response->failed()) {
            Log::warning('GeoIp a échoué', ['status' => $response->status()]);

            return [[], true];
        }

        $out = [];
        foreach ($response->json() ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $ip = $row['query'] ?? null;
            if (! $ip || ($row['status'] ?? null) !== 'success') {
                continue; // IP refusée par l'API → restera en 'skip'
            }

            $out[$ip] = [
                'city' => self::clean($row['city'] ?? null),
                'postal' => self::clean($row['zip'] ?? null),
                'region' => self::clean($row['regionName'] ?? null),
                'country' => self::clean($row['country'] ?? null),
                'isp' => self::clean($row['isp'] ?? null),
            ];
        }

        return [$out, false];
    }

    /** Reste-t-il du quota dans la fenêtre courante ? */
    private static function hasBudget(): bool
    {
        $state = Cache::get(self::BUDGET_CACHE_KEY);

        // Pas d'entrée = fenêtre réinitialisée (ou jamais sollicitée) → on tente.
        if (! is_array($state)) {
            return true;
        }

        return ($state['remaining'] ?? 1) > 0;
    }

    /**
     * Stocke le nombre de requêtes restantes (X-Rl) avec une expiration calée
     * sur la fin de la fenêtre (X-Ttl). Une fois l'entrée expirée, le quota est
     * de nouveau considéré comme disponible.
     */
    private static function rememberBudget(?string $remaining, ?string $ttl): void
    {
        if ($remaining === null) {
            return;
        }

        $ttlSeconds = max(1, (int) $ttl);

        Cache::put(
            self::BUDGET_CACHE_KEY,
            ['remaining' => (int) $remaining],
            now()->addSeconds($ttlSeconds),
        );
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** Ignore les adresses privées, loopback et réservées (non géolocalisables). */
    private static function isPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private static function cacheKey(string $ip): string
    {
        return 'geoip:'.$ip;
    }
}
