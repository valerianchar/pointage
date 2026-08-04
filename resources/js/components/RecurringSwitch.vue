<script setup>
import { SwitchRoot, SwitchThumb } from 'reka-ui';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    label: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <SwitchRoot
        :model-value="props.modelValue"
        :aria-label="props.label"
        class="relative h-[22px] w-[38px] shrink-0 cursor-pointer rounded-pill transition-colors duration-200"
        :class="props.modelValue ? 'bg-accent' : 'bg-switch-track'"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <SwitchThumb
            class="absolute top-[2px] block size-[18px] rounded-full bg-ink transition-[left] duration-200"
            :class="props.modelValue ? 'left-[18px]' : 'left-[2px]'"
        />
    </SwitchRoot>
</template>
