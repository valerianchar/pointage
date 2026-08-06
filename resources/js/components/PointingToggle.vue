<script setup>
import { CheckboxRoot } from 'reka-ui';
import { router } from '@inertiajs/vue3';
import PhIcon from './PhIcon.vue';

const props = defineProps({
    pointed: { type: Boolean, required: true },
    url: { type: String, required: true },
    label: { type: String, required: true },
});

/**
 * Le pointage se fait sur place : la page ne défile pas et les jauges se
 * recalculent avec les propriétés fraîches renvoyées par le serveur.
 */
function togglePointing() {
    router.patch(props.url, {}, { preserveScroll: true });
}
</script>

<template>
    <CheckboxRoot
        :model-value="props.pointed"
        :aria-label="props.pointed ? `Dépointer ${props.label}` : `Pointer ${props.label}`"
        class="flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-full border-[1.5px] transition-colors"
        :class="props.pointed ? 'border-accent bg-accent' : 'border-outline-muted bg-transparent'"
        @update:model-value="togglePointing"
    >
        <PhIcon
            name="ph-check"
            class="text-[14px] text-page transition-opacity"
            :class="props.pointed ? 'opacity-100' : 'opacity-0'"
        />
    </CheckboxRoot>
</template>
