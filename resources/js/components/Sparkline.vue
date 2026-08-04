<script setup>
import { computed } from 'vue';
import { useMoney } from '../composables/useMoney';

const props = defineProps({
    /** @type {{label: string, balance_cents: number}[]} */
    points: { type: Array, required: true },
});

const { money } = useMoney();

/** Hauteurs ramenées à 50–95 % : l'écart entre semaines reste lisible. */
const MINIMUM_HEIGHT_PERCENT = 50;
const HEIGHT_RANGE_PERCENT = 45;

const bars = computed(() => {
    const balances = props.points.map((point) => point.balance_cents);
    const lowest = Math.min(...balances);
    const span = Math.max(...balances) - lowest;

    return props.points.map((point) => ({
        label: point.label,
        title: `${point.label} — ${money(point.balance_cents)}`,
        heightPercent:
            span === 0
                ? MINIMUM_HEIGHT_PERCENT + HEIGHT_RANGE_PERCENT
                : MINIMUM_HEIGHT_PERCENT + ((point.balance_cents - lowest) / span) * HEIGHT_RANGE_PERCENT,
    }));
});
</script>

<template>
    <!-- Hauteur et espacement sont fixés par l'appelant : le graphe sert autant la
         carte de l'accueil mobile que le widget desktop, aux dimensions différentes. -->
    <div class="flex items-end" role="img" aria-label="Évolution du solde">
        <div
            v-for="bar in bars"
            :key="bar.label"
            :title="bar.title"
            class="flex-1 rounded-t-xs bg-spark"
            :style="{ height: `${bar.heightPercent}%` }"
        />
    </div>
</template>
