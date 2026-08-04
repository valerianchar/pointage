#!/bin/sh
set -e

# Un seul conteneur applique les migrations : le planificateur démarre avec la
# même image et ne doit pas entrer en concurrence avec lui.
if [ "${MIGRATE_ON_BOOT:-false}" = "true" ]; then
    # MySQL met une bonne minute à s'initialiser sur une petite machine. On
    # réessaie donc au lieu d'abandonner : la migration est rejouable sans risque.
    attempt=1
    until php artisan migrate --force; do
        if [ "$attempt" -ge 30 ]; then
            echo "Base de données injoignable après $attempt tentatives, abandon." >&2
            exit 1
        fi

        echo "→ base pas encore prête, nouvelle tentative dans 3 s ($attempt)"
        attempt=$((attempt + 1))
        sleep 3
    done
fi

# Caches de production, reconstruits à chaque démarrage : ils dépendent de
# variables d'environnement que l'image ne connaît pas à sa construction.
echo "→ mise en cache de la configuration"
php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
