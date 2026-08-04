import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { formatCents, formatSignedCents, MASKED_AMOUNT, MASKED_SIGNED_AMOUNT } from '../money';

/**
 * Formatage des montants, mode confidentialité compris : quand il est actif, tous
 * les montants de l'interface deviennent des points.
 *
 * Un solde masqué garde sa largeur de champ (« ••••• € ») ; un montant qui
 * s'inscrit dans une phrase — mensualité, charge, projection — se réduit à « ••• ».
 */
export function useMoney() {
    const page = usePage();

    const balancesHidden = computed(() => page.props.auth.user?.hide_balances ?? false);

    return {
        balancesHidden,
        money: (cents) => (balancesHidden.value ? MASKED_AMOUNT : formatCents(cents)),
        signedMoney: (cents) => (balancesHidden.value ? MASKED_SIGNED_AMOUNT : formatSignedCents(cents)),
        plainMoney: (cents) => (balancesHidden.value ? MASKED_SIGNED_AMOUNT : formatCents(cents)),
    };
}
