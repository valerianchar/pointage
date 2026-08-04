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

];
