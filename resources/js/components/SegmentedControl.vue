<script setup>
import { TabsList, TabsRoot, TabsTrigger } from 'reka-ui';

const props = defineProps({
    modelValue: { type: String, required: true },
    /** @type {{value: string, label: string}[]} */
    options: { type: Array, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <TabsRoot
        :model-value="props.modelValue"
        activation-mode="automatic"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <TabsList class="flex gap-1.5">
            <TabsTrigger
                v-for="option in props.options"
                :key="option.value"
                :value="option.value"
                class="flex-1 cursor-pointer rounded-card border py-[7px] text-center text-[12px] transition-colors lg:py-2 lg:text-[13px]"
                :class="
                    option.value === props.modelValue
                        ? 'border-accent bg-accent-surface text-accent-soft'
                        : 'border-hairline bg-transparent text-ink-muted hover:text-ink'
                "
            >
                {{ option.label }}
            </TabsTrigger>
        </TabsList>
    </TabsRoot>
</template>
