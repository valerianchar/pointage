import { onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { routes } from '../routes';

/**
 * Abonnement de cet appareil aux rappels de pointage, par notification
 * navigateur. Trois états : indisponible (navigateur sans Web Push, ou service
 * worker absent en développement), abonné, non abonné.
 */
export function usePushSubscription() {
    const page = usePage();

    const isSupported = ref(false);
    const isSubscribed = ref(false);
    const isDenied = ref(false);
    const isWorking = ref(false);

    onMounted(async () => {
        isSupported.value =
            'serviceWorker' in navigator && 'PushManager' in window && Boolean(page.props.vapid_public_key);

        if (!isSupported.value) {
            return;
        }

        isDenied.value = Notification.permission === 'denied';

        const registration = await navigator.serviceWorker.getRegistration();
        const subscription = await registration?.pushManager.getSubscription();
        isSubscribed.value = Boolean(subscription);
    });

    async function subscribe() {
        isWorking.value = true;

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: vapidKeyBytes(page.props.vapid_public_key),
            });

            router.post(routes.pushSubscription, subscription.toJSON(), {
                preserveScroll: true,
                onSuccess: () => (isSubscribed.value = true),
            });
        } catch {
            isDenied.value = Notification.permission === 'denied';
        } finally {
            isWorking.value = false;
        }
    }

    async function unsubscribe() {
        isWorking.value = true;

        try {
            const registration = await navigator.serviceWorker.getRegistration();
            const subscription = await registration?.pushManager.getSubscription();

            if (!subscription) {
                isSubscribed.value = false;

                return;
            }

            const { endpoint } = subscription;
            await subscription.unsubscribe();

            router.delete(routes.pushSubscription, {
                data: { endpoint },
                preserveScroll: true,
                onSuccess: () => (isSubscribed.value = false),
            });
        } finally {
            isWorking.value = false;
        }
    }

    return { isSupported, isSubscribed, isDenied, isWorking, subscribe, unsubscribe };
}

/** La clé VAPID circule en base64 « URL-safe » ; l'API Push attend des octets. */
function vapidKeyBytes(base64Key) {
    const padded = base64Key + '='.repeat((4 - (base64Key.length % 4)) % 4);
    const raw = window.atob(padded.replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}
