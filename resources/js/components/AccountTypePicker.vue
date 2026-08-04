<script setup>
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import PhIcon from './PhIcon.vue';

const props = defineProps({
    modelValue: { type: String, required: true },
    /** @type {{value: string, label: string, icon: string}[]} */
    types: { type: Array, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <RadioGroupRoot
        :model-value="props.modelValue"
        aria-label="Type de compte"
        class="grid gap-1.5 lg:grid-cols-2"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <RadioGroupItem
            v-for="type in props.types"
            :key="type.value"
            :value="type.value"
            class="flex cursor-pointer items-center gap-2.5 rounded-card border px-[11px] py-[9px] text-left transition-colors"
            :class="
                type.value === props.modelValue
                    ? 'border-accent bg-accent-surface'
                    : 'border-hairline bg-transparent hover:bg-surface'
            "
        >
            <span
                class="size-4 shrink-0 rounded-full border-[1.5px] shadow-[inset_0_0_0_3px_var(--color-page)]"
                :class="type.value === props.modelValue ? 'border-accent bg-accent' : 'border-outline-muted bg-transparent'"
            />
            <PhIcon :name="type.icon" class="text-[17px] text-accent" />
            <span
                class="text-[13px]"
                :class="type.value === props.modelValue ? 'text-accent-soft' : 'text-ink-muted'"
            >
                {{ type.label }}
            </span>
        </RadioGroupItem>
    </RadioGroupRoot>
</template>
