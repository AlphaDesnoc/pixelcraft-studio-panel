<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RealtimeConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $reverb = config('reverb.apps.apps.0') ?? [];

        return response()->json([
            'echo_available' => config('broadcasting.default') === 'reverb',
            'auth_endpoint' => url('/api/v1/broadcasting/auth'),
            'reverb' => [
                'key' => $reverb['key'] ?? config('broadcasting.connections.reverb.key'),
                'host' => $reverb['options']['host'] ?? parse_url(config('app.url'), PHP_URL_HOST),
                'port' => (int) ($reverb['options']['port'] ?? 8080),
                'scheme' => $reverb['options']['scheme'] ?? 'https',
            ],
        ]);
    }
}
