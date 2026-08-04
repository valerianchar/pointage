<script setup>
const props = defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    /**
     * Certains formulaires n'affichent le libellé qu'en desktop et laissent le
     * placeholder nommer le champ sur mobile, faute de place. Le libellé reste
     * dans le DOM en toutes circonstances, pour les lecteurs d'écran.
     */
    labelDesktopOnly: { type: Boolean, default: false },
});
</script>

<template>
    <div>
        <!-- Le champ est enveloppé par son libellé : l'association est implicite. -->
        <label class="block">
            <span
                class="label-caps block"
                :class="props.labelDesktopOnly ? 'sr-only lg:not-sr-only lg:mb-[5px]' : 'mb-[5px]'"
            >
                {{ props.label }}
            </span>
            <slot />
        </label>
        <p v-if="props.error" class="mt-1.5 text-[11px] text-accent-soft">{{ props.error }}</p>
    </div>
</template>
