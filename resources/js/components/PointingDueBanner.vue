<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePointingDue } from '../composables/usePointingDue';
import { routes } from '../routes';
import PhIcon from './PhIcon.vue';

const { reminderAccounts, reminderDismissed, dismissReminderForToday } = usePointingDue();

const reminderText = computed(() =>
    reminderAccounts.value
        .map(
            (account) =>
                `${account.name} — J−${account.days_until_period_end}, ${account.pending_count} à pointer`,
        )
        .join(' · '),
);

const targetUrl = computed(() => {
    const [firstDue] = reminderAccounts.value;

    return firstDue ? `${routes.bilan}?cloture=${firstDue.id}&pointer=1` : routes.bilan;
});
</script>

<template>
    <div
        v-if="reminderAccounts.length > 0 && !reminderDismissed"
        class="mb-3 flex max-w-[1080px] items-center gap-2.5 rounded-card border border-accent bg-accent-surface px-3 py-2.5 lg:mb-5 lg:gap-3 lg:px-3.5"
    >
        <PhIcon name="ph-bell-ringing" class="shrink-0 text-[16px] text-accent lg:text-[18px]" />
        <!-- Mobile : tout le texte est le lien ; desktop : un bouton dédié. -->
        <Link :href="targetUrl" class="min-w-0 flex-1 text-[12px] text-accent-soft lg:pointer-events-none lg:text-[12px]">
            Rappel pointage : {{ reminderText }}
        </Link>
        <Link :href="targetUrl" class="btn-outline hidden shrink-0 rounded-pill px-3 py-1 text-[11px] lg:block">
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
