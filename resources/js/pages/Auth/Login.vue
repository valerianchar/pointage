<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import FormField from '../../components/FormField.vue';
import GuestLayout from '../../layouts/GuestLayout.vue';
import { routes } from '../../routes';

defineOptions({ layout: null });

const page = usePage();
const registrationOpen = computed(() => page.props.registration_open);

const form = useForm({ email: '', password: '' });

// La maquette n'affiche qu'une ligne d'erreur sous les champs.
const firstError = computed(() => Object.values(form.errors)[0] ?? null);

function submit() {
    form.post(routes.login, { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Connexion" />

    <GuestLayout title="Pointage" subtitle="Pointez vos comptes, gardez la main sur vos dépenses.">
        <form @submit.prevent="submit">
            <FormField label="E-mail">
                <input
                    v-model="form.email"
                    type="email"
                    class="field"
                    placeholder="vous@exemple.fr"
                    autocomplete="email"
                />
            </FormField>

            <div class="mt-3.5">
                <FormField label="Mot de passe">
                    <input
                        v-model="form.password"
                        type="password"
                        class="field"
                        placeholder="••••••••"
                        autocomplete="current-password"
                    />
                </FormField>
            </div>

            <p v-if="firstError" class="mt-2.5 text-[13px] text-accent-soft lg:text-[12px]">{{ firstError }}</p>

            <button type="submit" class="btn-outline mt-5 w-full py-[11px] text-[15px]" :disabled="form.processing">
                Se connecter
            </button>
        </form>

        <template v-if="registrationOpen" #footer>
            Pas de compte ?
            <Link :href="routes.register" class="text-accent-soft">Créer un profil</Link>
        </template>
    </GuestLayout>
</template>
