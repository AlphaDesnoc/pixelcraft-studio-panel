<?php

namespace App\Support\Api;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PendingTwoFactorLogin
{
    private const PREFIX = 'api_2fa_pending:';

    private const TTL_SECONDS = 300;

    public static function create(int $userId, bool $remember): string
    {
        $token = Str::random(64);

        Cache::put(self::PREFIX.$token, [
            'user_id' => $userId,
            'remember' => $remember,
        ], self::TTL_SECONDS);

        return $token;
    }

    /**
     * @return array{user_id: int, remember: bool}|null
     */
    public static function consume(string $token): ?array
    {
        $key = self::PREFIX.$token;
        $payload = Cache::get($key);

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            return null;
        }

        Cache::forget($key);

        return [
            'user_id' => (int) $payload['user_id'],
            'remember' => (bool) ($payload['remember'] ?? false),
        ];
    }
}
