<script setup>
import { computed } from 'vue';
import { useMoney } from '../composables/useMoney';
import Amount from './Amount.vue';
import CreditProgress from './CreditProgress.vue';

const props = defineProps({
    credit: { type: Object, required: true },
});

const { plainMoney } = useMoney();

const monthlyLabel = computed(() => `${plainMoney(props.credit.monthly_cents)} / mois`);
</script>

<template>
    <div class="rounded-card bg-surface px-3 py-2.5 lg:px-3.5 lg:py-3">
        <div class="flex items-baseline justify-between gap-2 text-[12px]">
            <span class="min-w-0 truncate font-medium">{{ props.credit.name }}</span>
            <Amount :cents="props.credit.remaining_cents" class="shrink-0" />
        </div>
        <p class="mt-px text-[10px] text-ink-muted">{{ monthlyLabel }}</p>
        <CreditProgress :credit="props.credit" class="mt-[7px] lg:mt-2" />
    </div>
</template>
