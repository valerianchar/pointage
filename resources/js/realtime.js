import { router } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/*
 * Temps réel des comptes partagés, via le Soketi de la pile (protocole
 * Pusher). Le navigateur s'abonne à son canal privé et réagit : une dépense
 * saisie par l'autre membre, une demande de suppression — l'écran se recharge
 * tout seul, solde global compris.
 *
 * Sans clé dans les props partagées, rien ne se connecte : l'app vit très
 * bien sans temps réel.
 */

let echo = null;
let reloadTimer = null;

function toast(message, asError = false) {
    document.dispatchEvent(new CustomEvent('pointage:toast', { detail: { message, error: asError } }));
}

/* Plusieurs événements coup sur coup — la génération nocturne — ne rechargent qu'une fois. */
function quietReload() {
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(() => router.reload({ preserveScroll: true }), 400);
}

function xsrfToken() {
    const cookie = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

export function connectRealtime(broadcast) {
    if (echo !== null || !broadcast?.key || !broadcast.user_id) {
        return;
    }

    window.Pusher = Pusher;

    /* Hôte absent = même domaine que la page, en wss derrière Traefik. */
    const scheme = broadcast.scheme ?? 'https';
    const port = Number(broadcast.port ?? (scheme === 'https' ? 443 : 80));

    echo = new Echo({
        broadcaster: 'pusher',
        key: broadcast.key,
        wsHost: broadcast.host || window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        cluster: 'mt1',
        disableStats: true,
        auth: { headers: { 'X-XSRF-TOKEN': xsrfToken() } },
    });

    echo.private(`users.${broadcast.user_id}`)
        .listen('.account.activity', () => quietReload())
        .listen('.account.invited', (event) => {
            toast(`${event.inviter_name} vous invite à rejoindre « ${event.account_name} » — la réponse vous attend sur l'accueil.`);
            quietReload();
        })
        .listen('.account.deletion-requested', (event) => {
            toast(`${event.requester_name} demande la suppression de « ${event.account_name} » — à vous de trancher.`, true);
            quietReload();
        })
        .listen('.account.deletion-decided', (event) => {
            toast(
                event.deleted
                    ? `« ${event.account_name} » supprimé, d'un commun accord.`
                    : `Suppression de « ${event.account_name} » refusée — le compte reste.`,
                !event.deleted,
            );
            quietReload();
        });
}
