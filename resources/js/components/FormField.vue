<script setup>
const props = defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
});
</script>

<template>
    <div>
        <!-- Le champ est enveloppé par son libellé : l'association est implicite. -->
        <label class="block">
            <span class="label-caps mb-[5px] block">{{ props.label }}</span>
            <slot />
        </label>
        <p v-if="props.error" class="mt-1.5 text-[11px] text-accent-soft">{{ props.error }}</p>
    </div>
</template>
