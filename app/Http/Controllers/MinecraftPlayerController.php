<?php

namespace App\Http\Controllers;

use App\Models\MinecraftPlayer;
use App\Models\MinecraftServer;
use App\Models\Project;
use App\Support\GeoIpLocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MinecraftPlayerController extends Controller
{
    /**
     * Liste JSON des joueurs + état du serveur, pour rafraîchir la vue
     * sans recharger la page (fetch côté front).
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $server = $project->minecraftServerOrCreate();

        $players = $project->minecraftPlayers()
            ->orderByDesc('online')
            ->orderByDesc('last_seen_at')
            ->get();

        $this->resolveMissingGeo($players);

        $players = $players
            ->map(fn ($p) => $p->toPayload())
            ->values();

        return response()->json([
            'server' => $server->toPayload(),
            'players' => $players,
        ]);
    }

    /**
     * Résout en lot la géolocalisation des joueurs qui ont une IP mais pas
     * encore de données géo (nouvelle IP ou jamais résolue). Une seule requête
     * batch suffit pour tout le monde ; les échecs sont silencieux.
     *
     * @param  \Illuminate\Support\Collection<int, MinecraftPlayer>  $players
     */
    private function resolveMissingGeo($players): void
    {
        $pending = $players->filter(
            fn (MinecraftPlayer $p) => $p->ip && $p->geo_resolved_at === null,
        );

        if ($pending->isEmpty()) {
            return;
        }

        $results = GeoIpLocator::resolveMany($pending->pluck('ip')->all());

        foreach ($pending as $player) {
            $result = $results[$player->ip] ?? null;
            $status = $result['status'] ?? 'fail';

            // Échec transient (API injoignable ou quota épuisé) : on ne touche
            // à rien pour réessayer au prochain rafraîchissement (toutes les 10 s).
            if ($status === 'fail') {
                continue;
            }

            // 'ok' → géo trouvée ; 'skip' → IP non géolocalisable (privée/réservée).
            // Dans les deux cas on horodate pour ne plus réinterroger.
            $geo = $result['geo'] ?? [];
            $player->forceFill([
                'geo_city' => $geo['city'] ?? null,
                'geo_postal' => $geo['postal'] ?? null,
                'geo_region' => $geo['region'] ?? null,
                'geo_country' => $geo['country'] ?? null,
                'geo_country_code' => $geo['country_code'] ?? null,
                'geo_lat' => $geo['lat'] ?? null,
                'geo_lon' => $geo['lon'] ?? null,
                'geo_timezone' => $geo['timezone'] ?? null,
                'geo_isp' => $geo['isp'] ?? null,
                'geo_org' => $geo['org'] ?? null,
                'geo_as' => $geo['as'] ?? null,
                'geo_proxy' => $geo['proxy'] ?? null,
                'geo_hosting' => $geo['hosting'] ?? null,
                'geo_mobile' => $geo['mobile'] ?? null,
                'geo_resolved_at' => now(),
            ])->save();
        }
    }

    public function regenerateToken(Request $request, Project $project): RedirectResponse
    {
        $server = $project->minecraftServerOrCreate();
        $server->forceFill([
            'link_code' => MinecraftServer::generateLinkCode(),
            'token' => MinecraftServer::generateToken(),
            'linked_at' => null,
        ])->save();

        return back()->with('success', 'Identifiant régénéré. L\'ancien serveur est déconnecté ; reliez-le avec le nouvel identifiant.');
    }

    public function destroy(Request $request, Project $project, MinecraftPlayer $player): RedirectResponse
    {
        abort_unless($player->project_id === $project->id, 404);

        $player->delete();

        return back()->with('success', 'Joueur supprimé.');
    }

    public function clearOffline(Request $request, Project $project): RedirectResponse
    {
        $project->minecraftPlayers()->where('online', false)->delete();

        return back()->with('success', 'Joueurs hors-ligne supprimés.');
    }
}
