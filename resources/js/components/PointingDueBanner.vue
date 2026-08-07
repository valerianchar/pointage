<script setup>
import { Link } from '@inertiajs/vue3';
import { usePointingDue } from '../composables/usePointingDue';
import { routes } from '../routes';
import PhIcon from './PhIcon.vue';

const { reminderAccounts, reminderDismissed, dismissReminderForToday } = usePointingDue();

const closingUrl = (account) => `${routes.bilan}?cloture=${account.id}&pointer=1`;
</script>

<template>
    <div
        v-if="reminderAccounts.length > 0 && !reminderDismissed"
        class="mb-3 flex max-w-[1080px] items-center gap-2.5 rounded-card border border-accent bg-accent-surface px-3 py-2.5 lg:mb-5 lg:gap-3 lg:px-3.5"
    >
        <PhIcon name="ph-bell-ringing" class="shrink-0 text-[16px] text-accent lg:text-[18px]" />
        <!-- Chaque compte est son propre lien : on choisit lequel pointer. -->
        <p class="min-w-0 flex-1 text-[12px]">
            <span class="text-ink-muted">Rappel pointage : </span>
            <template v-for="(account, index) in reminderAccounts" :key="account.id">
                <span v-if="index > 0" class="text-ink-muted"> · </span>
                <Link :href="closingUrl(account)" class="text-accent-soft transition-colors hover:text-ink">
                    {{ account.name }} — J−{{ account.days_until_period_end }},
                    {{ account.pending_count }} à pointer →
                </Link>
            </template>
        </p>
        <Link
            v-if="reminderAccounts.length === 1"
            :href="closingUrl(reminderAccounts[0])"
            class="btn-outline hidden shrink-0 rounded-pill px-3 py-1 text-[11px] lg:block"
        >
            Pointer maintenant
        </Link>
        <button
            type="button"
            class="shrink-0 cursor-pointer text-[14px] text-ink-muted transition-colors hover:text-accent-soft"
            title="Me le rappeler demain"
            aria-label="Me le rappeler demain"
            @click="dismissReminderForToday"
        >
            <PhIcon name="ph-x" />
        </button>
    </div>
</template>
