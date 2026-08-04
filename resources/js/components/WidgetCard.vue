<script setup>
const props = defineProps({
    title: { type: String, required: true },
    /** Un widget occupe une ou deux des quatre colonnes de la grille. */
    span: { type: Number, default: 1 },
});
</script>

<template>
    <div class="rounded-card bg-surface p-3" :class="props.span === 2 ? 'col-span-2' : ''">
        <p class="text-[10px] tracking-[0.06em] text-ink-muted uppercase">{{ props.title }}</p>
        <slot />
    </div>
</template>
