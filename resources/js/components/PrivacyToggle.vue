<script setup>
import { router } from '@inertiajs/vue3';
import { useMoney } from '../composables/useMoney';
import { routes } from '../routes';
import PhIcon from './PhIcon.vue';

const { balancesHidden } = useMoney();

function togglePrivacy() {
    router.patch(routes.privacy, { hide_balances: !balancesHidden.value }, { preserveScroll: true });
}
</script>

<template>
    <button
        type="button"
        class="cursor-pointer text-ink-muted transition-colors hover:text-accent-soft"
        :aria-label="balancesHidden ? 'Afficher les soldes' : 'Masquer les soldes'"
        :title="balancesHidden ? 'Afficher les soldes' : 'Masquer les soldes'"
        @click="togglePrivacy"
    >
        <PhIcon :name="balancesHidden ? 'ph-eye-slash' : 'ph-eye'" />
    </button>
</template>
