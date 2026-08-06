<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import FormField from '../../components/FormField.vue';
import PhIcon from '../../components/PhIcon.vue';
import GuestLayout from '../../layouts/GuestLayout.vue';
import { useBiometricUnlock } from '../../composables/useBiometricUnlock';
import { routes } from '../../routes';

defineOptions({ layout: null });

const props = defineProps({
    name: { type: String, required: true },
    email: { type: String, required: true },
});

const form = useForm({ password: '' });
const {
    isSupported: biometricsSupported,
    isConfirming: biometricsConfirming,
    error: biometricsError,
    confirm: confirmBiometrics,
} = useBiometricUnlock(props.email);

function unlock() {
    form.delete(routes.lock, { onFinish: () => form.reset('password') });
}

async function unlockWithBiometrics() {
    if (await confirmBiometrics()) {
        router.post(`${routes.lock}/biometrie`);
    }
}

function signOut() {
    router.post(routes.logout);
}
</script>

<template>
    <Head title="Verrouillé" />

    <GuestLayout title="Pointage" :subtitle="`Session verrouillée — ${props.name}`">
        <form @submit.prevent="unlock">
            <FormField label="Mot de passe" :error="form.errors.password">
                <input
                    v-model="form.password"
                    type="password"
                    class="field"
                    placeholder="••••••••"
                    autocomplete="current-password"
                />
            </FormField>

            <button type="submit" class="btn-outline mt-5 w-full py-[11px] text-[15px]" :disabled="form.processing">
                Déverrouiller
            </button>

            <!-- Face ID / Touch ID : proposé uniquement si l'appareil sait le faire. -->
            <button
                v-if="biometricsSupported"
                type="button"
                class="mt-2.5 flex w-full cursor-pointer items-center justify-center gap-[7px] py-[11px] text-[14px] text-ink-muted transition-colors hover:text-accent-soft"
                :disabled="biometricsConfirming"
                @click="unlockWithBiometrics"
            >
                <PhIcon name="ph-user-focus" class="text-[17px]" />
                Continuer avec Face ID
            </button>

            <p v-if="biometricsError" class="mt-2 text-center text-[13px] text-accent-soft">
                {{ biometricsError }}
            </p>
        </form>

        <template #footer>
            <button type="button" class="cursor-pointer text-accent-soft" @click="signOut">Se déconnecter</button>
        </template>
    </GuestLayout>
</template>
