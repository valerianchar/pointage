import { ref } from 'vue';

/**
 * Ouverture de la modale « Signaler un bug », partagée entre ses déclencheurs :
 * l'icône du pied de sidebar en desktop, celle de l'en-tête d'accueil en mobile.
 * La modale elle-même vit dans AppLayout.
 */
const isOpen = ref(false);

export function useBugReport() {
    return {
        isOpen,
        open: () => (isOpen.value = true),
        close: () => (isOpen.value = false),
    };
}
