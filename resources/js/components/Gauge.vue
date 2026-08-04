<script setup>
import { ProgressIndicator, ProgressRoot } from 'reka-ui';

const props = defineProps({
    percent: { type: Number, required: true },
    label: { type: String, required: true },
    barClass: { type: String, default: 'bg-gauge' },
});
</script>

<template>
    <ProgressRoot
        :model-value="props.percent"
        :aria-label="props.label"
        class="h-1 w-full overflow-hidden rounded-xs bg-gauge-track"
    >
        <ProgressIndicator
            class="h-1 rounded-xs transition-[width] duration-300 ease-out"
            :class="props.barClass"
            :style="{ width: `${props.percent}%` }"
        />
    </ProgressRoot>
</template>
