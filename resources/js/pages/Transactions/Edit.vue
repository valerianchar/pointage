<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Chip from '../../components/Chip.vue';
import FormField from '../../components/FormField.vue';
import PhIcon from '../../components/PhIcon.vue';
import SegmentedControl from '../../components/SegmentedControl.vue';
import { formatCents } from '../../money';

const props = defineProps({
    transaction: { type: Object, required: true },
    /** Le compte de l'opération, tags compris — l'opération n'en change pas. */
    account: { type: Object, required: true },
    /** @type {{value: string, label: string, submit_label: string}[]} */
    directions: { type: Array, required: true },
});

const form = useForm({
    direction: props.transaction.amount_cents < 0 ? 'depense' : 'ajout',
    // Montant présenté en valeur absolue : le sens porte le signe.
    amount: formatCents(Math.abs(props.transaction.amount_cents)).replace(' €', ''),
    label: props.transaction.label,
    occurred_on: props.transaction.occurred_on,
    tag_id: props.transaction.tag_id,
});

function submit() {
    form.patch(props.transaction.update_url);
}

function destroyTransaction() {
    router.delete(props.transaction.delete_url);
}
</script>

<template>
    <Head title="Modifier l'opération" />

    <div class="max-w-[540px]">
        <div class="flex items-center gap-2 lg:gap-2.5">
            <Link :href="props.account.url" aria-label="Retour au compte">
                <PhIcon name="ph-caret-left" class="text-[18px] text-accent" />
            </Link>
            <div>
                <h1 class="text-xl lg:text-[22px]">Modifier l'opération</h1>
                <p class="text-[12px] text-ink-muted lg:text-[11px]">{{ props.account.name }}</p>
            </div>
        </div>

        <form class="mt-3 lg:mt-4" @submit.prevent="submit">
            <SegmentedControl v-model="form.direction" :options="props.directions" />

            <div class="mt-3.5 grid gap-3 lg:mt-4 lg:grid-cols-2 lg:gap-3.5">
                <FormField label="Montant" :error="form.errors.amount">
                    <input v-model="form.amount" type="text" inputmode="decimal" class="field" placeholder="0,00 €" />
                </FormField>

                <FormField label="Libellé" :error="form.errors.label">
                    <input v-model="form.label" type="text" class="field" placeholder="Ex. Courses Carrefour" />
                </FormField>
            </div>

            <div class="mt-3 lg:mt-4 lg:w-1/2 lg:pr-2">
                <FormField label="Date" :error="form.errors.occurred_on">
                    <input v-model="form.occurred_on" type="date" class="field" />
                </FormField>
            </div>

            <p class="label-caps mt-3 mb-1.5 lg:mt-4">Tag</p>
            <div v-if="props.account.tags.length > 0" class="flex flex-wrap gap-[5px] lg:gap-1.5">
                <Chip
                    v-for="tag in props.account.tags"
                    :key="tag.id"
                    :selected="tag.id === form.tag_id"
                    @click="form.tag_id = tag.id === form.tag_id ? null : tag.id"
                >
                    {{ tag.name }}
                </Chip>
            </div>
            <p v-else class="text-[13px] text-ink-muted">Ce compte n'a pas de tag.</p>
            <p v-if="form.errors.tag_id" class="mt-1.5 text-[13px] text-accent-soft">{{ form.errors.tag_id }}</p>

            <button type="submit" class="btn-outline mt-4 w-full py-2.5 text-[15px] lg:mt-5" :disabled="form.processing">
                Enregistrer les modifications
            </button>
        </form>

        <!-- La suppression vit sur cette page : y venir est déjà un geste délibéré. -->
        <button
            type="button"
            class="mt-2.5 flex w-full cursor-pointer items-center justify-center gap-2 rounded-card border border-hairline py-2.5 text-[14px] text-ink-muted transition-colors hover:border-outline-muted hover:text-ink lg:mt-3 lg:text-[13px]"
            @click="destroyTransaction"
        >
            <PhIcon name="ph-trash" class="text-[15px]" />
            Supprimer l'opération
        </button>
        <p class="mt-1.5 text-center text-[11px] text-ink-muted lg:text-[10px]">
            La suppression est immédiate et recalcule le solde du compte.
        </p>
    </div>
</template>
