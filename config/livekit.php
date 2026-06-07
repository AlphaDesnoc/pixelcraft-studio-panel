<?php

return [
    // Identifiants serveur LiveKit. Les valeurs par défaut correspondent au
    // mode dev (`livekit-server --dev`). À remplacer en production.
    'api_key' => env('LIVEKIT_API_KEY', 'devkey'),
    'api_secret' => env('LIVEKIT_API_SECRET', 'secret'),

    // URL WebSocket du serveur LiveKit, transmise au client.
    'url' => env('LIVEKIT_URL', 'ws://localhost:7880'),

    // Durée de validité d'un token (secondes).
    'ttl' => (int) env('LIVEKIT_TOKEN_TTL', 6 * 3600),
];
