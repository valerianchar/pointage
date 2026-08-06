<script setup>
import Gauge from './Gauge.vue';

const props = defineProps({
    credit: { type: Object, required: true },
});
</script>

<template>
    <div class="flex items-center gap-2 lg:gap-2.5">
        <Gauge
            :percent="props.credit.repaid_percent"
            :label="`Remboursement de ${props.credit.name}`"
            class="flex-1"
        />
        <span class="shrink-0 text-[11px] text-accent-soft lg:text-[10px]">
            {{ props.credit.repaid_percent }} % remboursé
        </span>
    </div>
</template>
