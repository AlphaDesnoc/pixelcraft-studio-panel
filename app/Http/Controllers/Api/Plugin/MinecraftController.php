<?php

namespace App\Http\Controllers\Api\Plugin;

use App\Http\Controllers\Controller;
use App\Models\MinecraftPlayer;
use App\Models\MinecraftServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MinecraftController extends Controller
{
    private function server(Request $request): MinecraftServer
    {
        return $request->attributes->get('minecraftServer');
    }

    /**
     * Liaison via l'identifiant court (/pixellink <id>). Le plugin échange
     * l'identifiant contre le token permanent qu'il utilisera ensuite.
     */
    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'server_name' => ['nullable', 'string', 'max:60'],
        ]);

        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['code']));

        $server = MinecraftServer::query()
            ->where('link_code', $code)
            ->first();

        abort_unless($server, 404, 'Identifiant de liaison invalide.');

        $server->forceFill([
            'linked_at' => now(),
            'last_synced_at' => now(),
            'last_ip' => $request->ip(),
            'name' => trim((string) ($data['server_name'] ?? '')) ?: $server->name,
        ])->save();

        $server->loadMissing('project:id,name,slug');

        return response()->json([
            'ok' => true,
            'token' => $server->token,
            'project' => [
                'name' => $server->project?->name,
                'slug' => $server->project?->slug,
            ],
            'server' => [
                'name' => $server->name,
            ],
        ]);
    }

    /**
     * Handshake : le plugin valide son token et récupère les infos du projet.
     */
    public function link(Request $request): JsonResponse
    {
        $server = $this->server($request);

        $server->forceFill([
            'linked_at' => $server->linked_at ?? now(),
            'last_synced_at' => now(),
            'last_ip' => $request->ip(),
            'name' => $request->string('server_name')->trim()->value() ?: $server->name,
        ])->save();

        $server->loadMissing('project:id,name,slug');

        return response()->json([
            'ok' => true,
            'project' => [
                'name' => $server->project?->name,
                'slug' => $server->project?->slug,
            ],
            'server' => [
                'name' => $server->name,
            ],
        ]);
    }

    /**
     * Synchronisation complète : le plugin envoie l'ensemble des joueurs
     * actuellement connectés. On met à jour leur statut et on bascule hors-ligne
     * tous ceux qui ne sont plus présents.
     */
    public function sync(Request $request): JsonResponse
    {
        $server = $this->server($request);

        $data = $request->validate([
            'players' => ['present', 'array'],
            'players.*.uuid' => ['required', 'string', 'max:36'],
            'players.*.name' => ['required', 'string', 'max:32'],
            'players.*.ip' => ['nullable', 'string', 'max:45'],
            'players.*.server' => ['nullable', 'string', 'max:60'],
            // Intervalle de synchro (s) du proxy émetteur, pour calibrer le délai
            // d'expiration. Optionnel : valeur par défaut prudente sinon.
            'interval' => ['nullable', 'integer', 'min:5', 'max:3600'],
        ]);

        foreach ($data['players'] as $payload) {
            $this->upsertPlayer($server, $payload, online: true);
        }

        // Réconciliation tolérante au multi-proxy : on ne touche JAMAIS aux
        // joueurs présents sur un autre proxy. On passe hors-ligne uniquement
        // ceux dont le dernier signe de vie est trop ancien (déconnexion
        // manquée ou proxy planté). Chaque proxy rafraîchit ses propres joueurs
        // à chaque sync, ils restent donc en ligne.
        $interval = (int) ($data['interval'] ?? 60);
        $staleThreshold = now()->subSeconds(max($interval * 3, 120));

        MinecraftPlayer::query()
            ->where('project_id', $server->project_id)
            ->where('online', true)
            ->where('last_seen_at', '<', $staleThreshold)
            ->update(['online' => false, 'current_server' => null]);

        $server->forceFill([
            'last_synced_at' => now(),
            'last_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'ok' => true,
            'synced' => count($data['players']),
        ]);
    }

    /**
     * Connexion d'un joueur : capture UUID, pseudo et IP.
     */
    public function join(Request $request): JsonResponse
    {
        $server = $this->server($request);

        $payload = $request->validate([
            'uuid' => ['required', 'string', 'max:36'],
            'name' => ['required', 'string', 'max:32'],
            'ip' => ['nullable', 'string', 'max:45'],
            'server' => ['nullable', 'string', 'max:60'],
        ]);

        $player = $this->upsertPlayer($server, $payload, online: true, incrementJoin: true);

        return response()->json(['ok' => true, 'player' => $player->toPayload()]);
    }

    /**
     * Changement de serveur backend : le proxy Velocity signale sur quel
     * serveur le joueur vient d'arriver. Mise à jour en temps réel.
     */
    public function serverChange(Request $request): JsonResponse
    {
        $server = $this->server($request);

        $payload = $request->validate([
            'uuid' => ['required', 'string', 'max:36'],
            'name' => ['required', 'string', 'max:32'],
            'ip' => ['nullable', 'string', 'max:45'],
            'server' => ['required', 'string', 'max:60'],
        ]);

        $player = $this->upsertPlayer($server, $payload, online: true);

        return response()->json(['ok' => true, 'player' => $player->toPayload()]);
    }

    /**
     * Déconnexion d'un joueur (quitte le proxy).
     */
    public function quit(Request $request): JsonResponse
    {
        $server = $this->server($request);

        $payload = $request->validate([
            'uuid' => ['required', 'string', 'max:36'],
        ]);

        MinecraftPlayer::query()
            ->where('project_id', $server->project_id)
            ->where('uuid', $payload['uuid'])
            ->update(['online' => false, 'current_server' => null, 'last_seen_at' => now()]);

        return response()->json(['ok' => true]);
    }

    private function upsertPlayer(
        MinecraftServer $server,
        array $payload,
        bool $online = false,
        bool $incrementJoin = false,
    ): MinecraftPlayer {
        $player = MinecraftPlayer::query()->firstOrNew([
            'project_id' => $server->project_id,
            'uuid' => $payload['uuid'],
        ]);

        $player->minecraft_server_id = $server->id;
        $player->name = $payload['name'] ?? $player->name;

        if (! empty($payload['ip'])) {
            $player->ip = $payload['ip'];
        }

        if (array_key_exists('server', $payload) && $payload['server'] !== null) {
            $player->current_server = $payload['server'] ?: null;
        }

        $player->online = $online;
        $player->last_seen_at = now();
        $player->first_seen_at ??= now();

        if ($incrementJoin) {
            $player->join_count = (int) $player->join_count + 1;
        }

        $player->save();

        return $player;
    }
}
