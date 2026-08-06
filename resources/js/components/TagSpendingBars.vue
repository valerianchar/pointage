<script setup>
import { computed } from 'vue';
import { shareOf } from '../money';
import Amount from './Amount.vue';
import Gauge from './Gauge.vue';

const props = defineProps({
    /** @type {{tag: string, amount_cents: number}[]} */
    spending: { type: Array, required: true },
});

const rows = computed(() => {
    const highest = Math.max(...props.spending.map((entry) => entry.amount_cents), 1);

    return props.spending.map((entry) => ({
        ...entry,
        percent: shareOf(entry.amount_cents, highest),
    }));
});
</script>

<template>
    <div class="flex flex-col gap-[5px] lg:gap-1.5">
        <div v-for="row in rows" :key="row.tag" class="flex items-center gap-2 lg:gap-2.5">
            <span class="w-[96px] shrink-0 truncate text-[13px] text-accent-soft lg:w-24">{{ row.tag }}</span>
            <Gauge
                :percent="row.percent"
                :label="`Dépenses ${row.tag}`"
                bar-class="bg-gauge-secondary"
                class="flex-1"
            />
            <Amount :cents="row.amount_cents" class="w-[84px] text-right text-[13px] lg:w-20" />
        </div>
    </div>
</template>
