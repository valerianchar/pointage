<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inscriptions ouvertes
    |--------------------------------------------------------------------------
    |
    | Pointage suit des finances personnelles : une instance en ligne n'a en
    | général qu'un seul propriétaire. Laissez les inscriptions ouvertes le temps
    | de créer votre profil, puis fermez-les — l'écran d'inscription devient
    | inaccessible et son lien disparaît de la page de connexion.
    |
    */

    'registration_open' => (bool) env('POINTAGE_REGISTRATION_OPEN', true),

    /*
    |--------------------------------------------------------------------------
    | Jeton du déclencheur de tâches
    |--------------------------------------------------------------------------
    |
    | Les offres d'hébergement gratuites n'ont généralement pas de cron. Renseigner
    | un jeton ouvre une route qui génère les opérations récurrentes du mois, pour
    | qu'un service de cron externe l'appelle. Laissé vide, la route répond 404 :
    | le déclencheur n'existe pas.
    |
    | Générez-le avec : openssl rand -hex 32
    |
    */

    'tasks_token' => env('POINTAGE_TASKS_TOKEN'),

];
