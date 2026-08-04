import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { routes } from '../routes';

/**
 * Les six entrées de navigation, partagées par la tab bar mobile et la sidebar
 * desktop. L'onglet « Compte » ouvre le compte affiché, sinon le premier déclaré.
 *
 * `shortLabel` est l'étiquette de la tab bar, où six onglets à 9 px laissent peu
 * de place — la sidebar, elle, affiche le libellé complet.
 */
export function useNavigation() {
    const page = usePage();

    const currentPath = computed(() => page.url.split('?')[0]);
    const accounts = computed(() => page.props.accounts ?? []);

    const accountPath = computed(() => {
        if (currentPath.value.startsWith('/compte/')) {
            return currentPath.value;
        }

        const [firstAccount] = accounts.value;

        return firstAccount ? routes.account(firstAccount.id) : routes.accountCreate;
    });

    const items = computed(() => [
        { label: 'Accueil', icon: 'ph-house', href: routes.dashboard, isActive: currentPath.value === routes.dashboard },
        {
            label: 'Compte',
            icon: 'ph-wallet',
            href: accountPath.value,
            // « Déclarer un compte » prolonge la section Compte, comme sur la maquette.
            isActive: currentPath.value.startsWith('/compte/') || currentPath.value === routes.accountCreate,
        },
        {
            label: 'Ajouter',
            icon: 'ph-plus-circle',
            href: routes.transactionCreate,
            isActive: currentPath.value === routes.transactionCreate,
        },
        {
            label: 'Récurrentes',
            shortLabel: 'Récurrent',
            icon: 'ph-arrows-clockwise',
            href: routes.recurring,
            isActive: currentPath.value === routes.recurring,
        },
        {
            label: 'Crédits',
            icon: 'ph-hand-coins',
            href: routes.credits,
            isActive: currentPath.value === routes.credits,
        },
        { label: 'Tags', icon: 'ph-tag', href: routes.tags, isActive: currentPath.value === routes.tags },
    ]);

    return { items, accounts, currentPath };
}
