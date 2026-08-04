<script setup>
import { Head, router } from '@inertiajs/vue3';
import Amount from '../../components/Amount.vue';

const props = defineProps({
    month_label: { type: String, required: true },
    instances: { type: Array, required: true },
    pending_count: { type: Number, required: true },
    total_count: { type: Number, required: true },
});

function togglePointing(instance) {
    router.patch(instance.pointing_url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Récurrentes" />

    <div class="max-w-[720px]">
        <h1 class="text-xl lg:text-[22px]">Récurrentes</h1>
        <p class="mt-1 text-[11px] text-ink-muted lg:mt-1.5 lg:text-[12px]">
            Créées automatiquement le 1er du mois. Pointez-les quand elles passent sur votre relevé.
        </p>
        <p class="mt-2 text-[11px] text-accent-soft lg:mt-2.5 lg:text-[12px]">
            {{ props.pending_count }} / {{ props.total_count }} à pointer ce mois-ci
        </p>

        <p v-if="props.instances.length === 0" class="mt-4 text-[13px] text-ink-muted">
            Aucune opération récurrente pour {{ props.month_label.toLowerCase() }}. Cochez « Récurrente » en ajoutant
            une opération pour qu'elle revienne chaque mois.
        </p>

        <div class="mt-2.5 flex flex-col gap-[7px] lg:mt-3.5 lg:gap-2">
            <div
                v-for="instance in props.instances"
                :key="instance.id"
                class="flex items-center gap-2.5 rounded-card bg-surface px-3 py-2.5 lg:gap-3 lg:px-4 lg:py-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[13px]">{{ instance.label }}</p>
                    <p class="mt-px truncate text-[10px] text-ink-muted lg:text-[11px]">
                        {{ instance.account_name }} · {{ instance.tag ?? '—' }}
                    </p>
                </div>

                <Amount
                    :cents="instance.amount_cents"
                    signed
                    class="text-[13px]"
                    :class="instance.amount_cents > 0 ? 'text-accent-soft' : 'text-ink'"
                />

                <button
                    type="button"
                    class="shrink-0 cursor-pointer rounded-pill border px-2.5 py-1 text-[11px] transition-colors lg:px-3"
                    :class="
                        instance.is_pointed
                            ? 'border-accent-surface bg-accent-surface text-accent-soft'
                            : 'border-accent bg-transparent text-accent hover:bg-accent-wash'
                    "
                    @click="togglePointing(instance)"
                >
                    {{ instance.is_pointed ? 'Pointée ✓' : 'Pointer' }}
                </button>
            </div>
        </div>
    </div>
</template>
