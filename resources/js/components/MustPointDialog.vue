<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { usePointingDue } from '../composables/usePointingDue';
import { routes } from '../routes';
import PhIcon from './PhIcon.vue';

const page = usePage();
const { blockingAccount } = usePointingDue();

/**
 * L'écran bloque tout sauf la page Bilan elle-même — c'est là que le pointage
 * se fait, il faut bien pouvoir y travailler.
 */
const isVisible = computed(
    () => blockingAccount.value !== null && !page.url.startsWith(routes.bilan),
);

const deadlineLabel = computed(() =>
    blockingAccount.value?.days_until_period_end === 0 ? "aujourd'hui" : 'demain',
);

const operationsLabel = computed(() =>
    blockingAccount.value?.pending_count > 1
        ? `${blockingAccount.value.pending_count} opérations restent à pointer`
        : '1 opération reste à pointer',
);
</script>

<template>
    <div
        v-if="isVisible"
        class="fixed inset-0 z-100 flex items-center justify-center bg-[rgba(10,11,20,0.78)] p-6"
        role="alertdialog"
        aria-modal="true"
        aria-label="Pointage obligatoire"
    >
        <div
            class="w-full max-w-[420px] rounded-xl border border-accent bg-chrome p-5 text-center lg:max-w-[440px] lg:p-6"
        >
            <PhIcon name="ph-warning-circle" class="text-[30px] text-accent lg:text-[34px]" />
            <p class="mt-2 text-[16px] font-medium lg:text-[17px]">Pointage obligatoire</p>
            <p class="mt-1.5 text-[13px] text-ink-muted lg:text-[12px]">
                La période de pointage de « {{ blockingAccount.name }} » se termine {{ deadlineLabel }} :
                {{ operationsLabel }}.
            </p>
            <p class="mt-1 text-[12px] text-ink-muted lg:text-[11px]">
                La navigation est bloquée tant que le pointage n'est pas fait.
            </p>
            <Link
                :href="`${routes.bilan}?cloture=${blockingAccount.id}&pointer=1`"
                class="btn-outline mt-3.5 block w-full py-2.5 text-[15px] lg:text-[13px]"
            >
                Faire mon pointage
            </Link>
        </div>
    </div>
</template>
