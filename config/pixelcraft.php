<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domaine email des comptes internes
    |--------------------------------------------------------------------------
    |
    | Tous les comptes créés depuis l'administration auront pour adresse
    | "<pseudo>@<domaine>". Ce domaine est aussi affiché en suffixe dans le
    | formulaire de création/édition d'utilisateur.
    |
    */

    'email_domain' => env('PIXELCRAFT_EMAIL_DOMAIN', 'pixelcraftstudio.fr'),
];
