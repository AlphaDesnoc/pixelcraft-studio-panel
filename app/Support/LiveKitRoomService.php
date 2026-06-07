<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiveKitRoomService
{
    /**
     * Met à jour les permissions et métadonnées d'un participant en direct
     * (promotion intervenant / rétrogradation auditeur), sans reconnexion.
     */
    public static function updateParticipant(string $room, string $identity, bool $canPublish, array $metadata): bool
    {
        try {
            $response = Http::withToken(LiveKitToken::admin($room))
                ->acceptJson()
                ->asJson()
                ->timeout(5)
                ->post(self::endpoint('UpdateParticipant'), [
                    'room' => $room,
                    'identity' => $identity,
                    'metadata' => json_encode($metadata),
                    'permission' => [
                        'canSubscribe' => true,
                        'canPublish' => $canPublish,
                        'canPublishData' => true,
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('LiveKit injoignable (updateParticipant)', [
                'room' => $room,
                'identity' => $identity,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('LiveKit updateParticipant a échoué', [
                'room' => $room,
                'identity' => $identity,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response->successful();
    }

    /** URL HTTP de l'API Twirp LiveKit, dérivée de l'URL WebSocket configurée. */
    private static function endpoint(string $method): string
    {
        $base = (string) config('livekit.url');
        $http = preg_replace('#^ws(s)?://#', 'http$1://', $base);
        $http = rtrim($http, '/');

        return "{$http}/twirp/livekit.RoomService/{$method}";
    }
}
