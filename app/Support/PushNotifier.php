<?php

namespace App\Support;

use App\Models\PushDeviceToken;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotifier
{
    public static function send(UserNotification $notification): void
    {
        $serverKey = config('pixelcraft.fcm_server_key');

        if (! $serverKey) {
            return;
        }

        $tokens = PushDeviceToken::query()
            ->where('user_id', $notification->user_id)
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return;
        }

        foreach ($tokens as $token) {
            try {
                Http::withHeaders([
                    'Authorization' => 'key='.$serverKey,
                    'Content-Type' => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'notification' => [
                        'title' => $notification->title,
                        'body' => $notification->body ?? '',
                    ],
                    'data' => [
                        'type' => $notification->type,
                        'url' => $notification->url,
                        'notification_id' => $notification->id,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('FCM push failed', [
                    'token' => substr($token, 0, 12).'…',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public static function register(User $user, string $platform, string $token, ?string $deviceName = null): PushDeviceToken
    {
        return PushDeviceToken::query()->updateOrCreate(
            ['user_id' => $user->id, 'token' => $token],
            [
                'platform' => $platform,
                'device_name' => $deviceName,
                'last_used_at' => now(),
            ],
        );
    }

    public static function unregister(User $user, string $token): void
    {
        PushDeviceToken::query()
            ->where('user_id', $user->id)
            ->where('token', $token)
            ->delete();
    }
}
