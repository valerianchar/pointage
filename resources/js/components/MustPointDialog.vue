<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { usePointingDue } from '../composables/usePointingDue';
import { routes } from '../routes';
import PhIcon from './PhIcon.vue';

const page = usePage();
const { blockingAccounts } = usePointingDue();

/**
 * L'écran bloque tout sauf la page Bilan elle-même — c'est là que le pointage
 * se fait, il faut bien pouvoir y travailler.
 */
const isVisible = computed(
    () => blockingAccounts.value.length > 0 && !page.url.startsWith(routes.bilan),
);

const deadlineLabel = (account) => (account.days_until_period_end === 0 ? "aujourd'hui" : 'demain');

const operationsLabel = (account) =>
    account.pending_count > 1 ? `${account.pending_count} opérations à pointer` : '1 opération à pointer';
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
                {{ blockingAccounts.length > 1
                    ? 'Plusieurs comptes terminent leur période de pointage — choisissez par lequel commencer.'
                    : `La période de pointage de « ${blockingAccounts[0].name} » se termine ${deadlineLabel(blockingAccounts[0])}.` }}
            </p>
            <p class="mt-1 text-[12px] text-ink-muted lg:text-[11px]">
                La navigation est bloquée tant que le pointage n'est pas fait.
            </p>

            <!-- Un compte par bouton : on choisit, on n'« arrive » plus sur le premier. -->
            <div class="mt-3.5 flex flex-col gap-2">
                <Link
                    v-for="account in blockingAccounts"
                    :key="account.id"
                    :href="`${routes.bilan}?cloture=${account.id}&pointer=1`"
                    class="btn-outline flex items-center justify-between gap-2 px-3.5 py-2.5 text-left text-[14px] lg:text-[13px]"
                >
                    <span class="min-w-0 truncate">{{ account.name }}</span>
                    <span class="shrink-0 text-[11px] text-ink-muted">
                        {{ operationsLabel(account) }} · fin {{ deadlineLabel(account) }}
                    </span>
                </Link>
            </div>
        </div>
    </div>
</template>
