<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormField from '../../components/FormField.vue';
import GuestLayout from '../../layouts/GuestLayout.vue';
import { routes } from '../../routes';

defineOptions({ layout: null });

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

function submit() {
    form.post(routes.register, { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Créer un profil" />

    <GuestLayout title="Créer un profil" subtitle="Quelques secondes, et vos comptes sont à vous.">
        <form class="flex flex-col gap-3.5" @submit.prevent="submit">
            <FormField label="Nom" :error="form.errors.name">
                <input v-model="form.name" type="text" class="field" placeholder="Marie Olivier" autocomplete="name" />
            </FormField>

            <FormField label="E-mail" :error="form.errors.email">
                <input
                    v-model="form.email"
                    type="email"
                    class="field"
                    placeholder="vous@exemple.fr"
                    autocomplete="email"
                />
            </FormField>

            <FormField label="Mot de passe" :error="form.errors.password">
                <input
                    v-model="form.password"
                    type="password"
                    class="field"
                    placeholder="••••••••"
                    autocomplete="new-password"
                />
            </FormField>

            <FormField label="Confirmation">
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    class="field"
                    placeholder="••••••••"
                    autocomplete="new-password"
                />
            </FormField>

            <button type="submit" class="btn-outline mt-2 w-full py-[11px] text-[15px]" :disabled="form.processing">
                Créer mon profil
            </button>
        </form>

        <template #footer>
            Déjà un profil ?
            <Link :href="routes.login" class="text-accent-soft">Se connecter</Link>
        </template>
    </GuestLayout>
</template>
