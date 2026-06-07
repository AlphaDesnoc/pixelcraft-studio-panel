<?php

namespace App\Support;

class LiveKitToken
{
    /**
     * Génère un access token LiveKit (JWT HS256) accordant l'accès à un salon.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function create(string $identity, string $name, string $room, array $metadata = []): string
    {
        $now = time();

        $payload = [
            'iss' => config('livekit.api_key'),
            'sub' => $identity,
            'nbf' => $now,
            'iat' => $now,
            'exp' => $now + config('livekit.ttl'),
            'name' => $name,
            'metadata' => json_encode($metadata),
            'video' => [
                'room' => $room,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
        ];

        return self::encode($payload, (string) config('livekit.api_secret'));
    }

    /** @param  array<string, mixed>  $payload */
    private static function encode(array $payload, string $secret): string
    {
        $segments = [
            self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            self::b64(json_encode($payload)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = self::b64($signature);

        return implode('.', $segments);
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
