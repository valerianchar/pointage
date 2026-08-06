<script setup>
const props = defineProps({
    selected: { type: Boolean, default: false },
});
</script>

<template>
    <button
        type="button"
        :aria-pressed="props.selected"
        class="cursor-pointer rounded-pill border px-[9px] py-1 text-[13px] transition-colors lg:px-[11px] lg:py-[5px] lg:text-[12px]"
        :class="
            props.selected
                ? 'border-accent bg-accent-surface text-accent-soft'
                : 'border-hairline bg-transparent text-ink-muted hover:text-ink'
        "
    >
        <slot />
    </button>
</template>
