<?php

use Illuminate\Support\Facades\Broadcast;

/*
 * Un seul canal privé par utilisateur : tout ce qui le concerne — demande de
 * suppression, activité d'un compte partagé — y passe. Personne d'autre que
 * lui ne peut s'y abonner.
 */
Broadcast::channel('users.{id}', fn ($user, int $id): bool => $user->id === $id);
