const AMOUNT_FORMATTER = new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export const MASKED_AMOUNT = '••••• €';
export const MASKED_SIGNED_AMOUNT = '•••';

/** Signe moins typographique (U+2212), plus lisible que le trait d'union. */
const MINUS_SIGN = '−';

export function formatCents(cents) {
    return `${AMOUNT_FORMATTER.format(cents / 100)} €`;
}

/**
 * Montant d'opération : toujours précédé de son signe, comme sur la maquette.
 */
export function formatSignedCents(cents) {
    return (cents > 0 ? '+' : MINUS_SIGN) + formatCents(Math.abs(cents));
}

/**
 * Part d'une valeur par rapport à un maximum, bornée à 100 % — largeur de jauge.
 */
export function shareOf(value, maximum) {
    if (maximum <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((value / maximum) * 100));
}
