<?php

return [
    // Quota de stockage par défaut par projet, en octets (2 Go).
    'default_quota' => env('PROJECT_STORAGE_QUOTA', 2 * 1024 * 1024 * 1024),

    // Taille maximale par fichier uploadé, en kilo-octets (50 Mo).
    'max_upload_kb' => env('FILE_MAX_UPLOAD_KB', 51200),

    // Extensions interdites à l'upload (sécurité).
    'blocked_extensions' => [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
        'exe', 'bat', 'cmd', 'com', 'msi', 'sh', 'cgi',
    ],
];
