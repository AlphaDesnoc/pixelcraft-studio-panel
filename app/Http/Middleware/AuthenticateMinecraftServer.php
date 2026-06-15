<?php

namespace App\Http\Middleware;

use App\Models\MinecraftServer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMinecraftServer
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Server-Token')
            ?? $request->bearerToken();

        abort_if(blank($token), 401, 'Token serveur manquant.');

        $server = MinecraftServer::query()
            ->where('token', $token)
            ->first();

        abort_unless($server, 401, 'Token serveur invalide.');

        $request->attributes->set('minecraftServer', $server);

        return $next($request);
    }
}
