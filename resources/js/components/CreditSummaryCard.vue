<script setup>
import { computed } from 'vue';
import { useMoney } from '../composables/useMoney';
import Amount from './Amount.vue';
import CreditProgress from './CreditProgress.vue';

const props = defineProps({
    credit: { type: Object, required: true },
});

const { plainMoney } = useMoney();

/**
 * Mensualité, jour de prélèvement et durée : ce qu'il faut pour reconnaître
 * l'échéance sur son relevé. Un crédit déclaré avant ces champs n'affiche que
 * ce qu'il porte.
 */
const scheduleLabel = computed(() =>
    [
        `${plainMoney(props.credit.monthly_cents)} / mois`,
        props.credit.payment_day === null ? null : `prélevé le ${props.credit.payment_day}`,
        props.credit.term_label ? `sur ${props.credit.term_label}` : null,
    ]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <div class="rounded-card bg-surface px-3 py-2.5 lg:px-3.5 lg:py-3">
        <div class="flex items-baseline justify-between gap-2 text-[12px]">
            <span class="min-w-0 truncate font-medium">{{ props.credit.name }}</span>
            <Amount :cents="props.credit.remaining_cents" class="shrink-0" />
        </div>
        <p class="mt-px text-[10px] text-ink-muted">{{ scheduleLabel }}</p>
        <CreditProgress :credit="props.credit" class="mt-[7px] lg:mt-2" />
    </div>
</template>
