import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const DISMISSAL_KEY = 'pointage-rappel-reporte';

/** La croix « me le rappeler demain » vaut pour la journée en cours. */
const dismissedOn = ref(readDismissal());

function readDismissal() {
    try {
        return window.localStorage.getItem(DISMISSAL_KEY);
    } catch {
        return null;
    }
}

/**
 * Comptes dont la période de pointage s'achève bientôt avec des opérations non
 * pointées. À J−5..J−2 : bannière de rappel, fermable. À J−1 et J−0 : écran
 * « Pointage obligatoire », qui bloque la navigation.
 */
export function usePointingDue() {
    const page = usePage();

    const dueAccounts = computed(() =>
        (page.props.accounts ?? []).filter(
            (account) =>
                account.pending_count > 0 &&
                account.days_until_period_end >= 0 &&
                account.days_until_period_end <= 5,
        ),
    );

    const reminderAccounts = computed(() =>
        dueAccounts.value.filter((account) => account.days_until_period_end > 1),
    );

    /* Tous les comptes au pied du mur — l'écran bloquant propose de choisir. */
    const blockingAccounts = computed(() =>
        dueAccounts.value.filter((account) => account.days_until_period_end <= 1),
    );

    const reminderDismissed = computed(() => dismissedOn.value === new Date().toDateString());

    function dismissReminderForToday() {
        dismissedOn.value = new Date().toDateString();

        try {
            window.localStorage.setItem(DISMISSAL_KEY, dismissedOn.value);
        } catch {
            // Stockage indisponible (navigation privée) : la croix ne vaut que pour la page.
        }
    }

    return { reminderAccounts, blockingAccounts, reminderDismissed, dismissReminderForToday };
}
