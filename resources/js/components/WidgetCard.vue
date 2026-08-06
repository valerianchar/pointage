<script setup>
const props = defineProps({
    title: { type: String, required: true },
    /** Un widget occupe une ou deux des quatre colonnes de la grille. */
    span: { type: Number, default: 1 },
});
</script>

<template>
    <!-- La double largeur n'a de sens que sur la grille desktop : en mobile,
         la colonne est unique. -->
    <div class="rounded-card bg-surface p-3" :class="props.span === 2 ? 'lg:col-span-2' : ''">
        <p class="text-[10px] tracking-[0.06em] text-ink-muted uppercase">{{ props.title }}</p>
        <slot />
    </div>
</template>
