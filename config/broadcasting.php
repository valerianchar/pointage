<?php

return [

    /*
     * Par défaut « log » : les événements temps réel s'écrivent dans le journal
     * et rien ne part sur le réseau — le mode des tests et d'un poste sans
     * Soketi. En production, BROADCAST_CONNECTION=pusher vise le Soketi de la
     * pile, qui parle le protocole Pusher sans rien envoyer chez Pusher.
     */
    'default' => env('BROADCAST_CONNECTION', 'log'),

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                // Le Soketi de la pile, joint en interne — jamais pusher.com.
                'host' => env('PUSHER_HOST', 'soketi'),
                'port' => (int) env('PUSHER_PORT', 6001),
                'scheme' => env('PUSHER_SCHEME', 'http'),
                'encrypted' => false,
                'useTLS' => env('PUSHER_SCHEME', 'http') === 'https',
                'cluster' => 'mt1',
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

    /*
     * Ce que le navigateur doit savoir pour se connecter, servi par les props
     * partagées Inertia — jamais baké dans les assets : la CI construit sans
     * .env. Hôte et port vides = même domaine que la page, en wss sur 443,
     * c'est le chemin de production derrière Traefik.
     */
    'client' => [
        'key' => env('PUSHER_APP_KEY'),
        'host' => env('PUSHER_CLIENT_HOST'),
        'port' => env('PUSHER_CLIENT_PORT'),
        'scheme' => env('PUSHER_CLIENT_SCHEME'),
    ],

];
