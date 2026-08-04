<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Chip from '../../components/Chip.vue';
import FormField from '../../components/FormField.vue';
import RecurringSwitch from '../../components/RecurringSwitch.vue';
import SegmentedControl from '../../components/SegmentedControl.vue';
import { routes } from '../../routes';

const props = defineProps({
    /** @type {{id: number, name: string, tags: {id: number, name: string}[]}[]} */
    accounts: { type: Array, required: true },
    /** @type {{value: string, label: string, submit_label: string}[]} */
    directions: { type: Array, required: true },
    selected_account_id: { type: Number, default: null },
});

const form = useForm({
    account_id: props.selected_account_id,
    direction: props.directions[0].value,
    amount: '',
    label: '',
    tag_id: null,
    is_recurring: false,
});

const selectedAccount = computed(() => props.accounts.find((account) => account.id === form.account_id) ?? null);
const availableTags = computed(() => selectedAccount.value?.tags ?? []);

const submitLabel = computed(
    () => props.directions.find((direction) => direction.value === form.direction)?.submit_label ?? 'Ajouter',
);

// Changer de compte recharge ses tags : celui qui était retenu n'existe plus ici.
watch(availableTags, (tags) => {
    form.tag_id = tags[0]?.id ?? null;
}, { immediate: true });

function submit() {
    form.post(routes.transactionStore);
}
</script>

<template>
    <Head title="Ajouter une opération" />

    <div class="max-w-[540px]">
        <h1 class="text-xl lg:text-[22px]">Ajouter une opération</h1>

        <p v-if="props.accounts.length === 0" class="mt-3 text-[13px] text-ink-muted">
            Déclarez d'abord un compte :
            <Link :href="routes.accountCreate" class="text-accent-soft">déclarer un compte</Link>.
        </p>

        <form v-else class="mt-3 lg:mt-4" @submit.prevent="submit">
            <SegmentedControl v-model="form.direction" :options="props.directions" />

            <div class="mt-3.5 grid gap-3 lg:mt-4 lg:grid-cols-2 lg:gap-3.5">
                <FormField label="Montant" :error="form.errors.amount">
                    <input
                        v-model="form.amount"
                        type="text"
                        inputmode="decimal"
                        class="field text-[15px]"
                        placeholder="0,00 €"
                    />
                </FormField>

                <FormField label="Libellé" :error="form.errors.label">
                    <input v-model="form.label" type="text" class="field" placeholder="Ex. Courses Carrefour" />
                </FormField>
            </div>

            <p class="label-caps mt-3 mb-1.5 lg:mt-4">Compte</p>
            <div class="flex flex-wrap gap-[5px] lg:gap-1.5">
                <Chip
                    v-for="account in props.accounts"
                    :key="account.id"
                    :selected="account.id === form.account_id"
                    @click="form.account_id = account.id"
                >
                    {{ account.name }}
                </Chip>
            </div>
            <p v-if="form.errors.account_id" class="mt-1.5 text-[11px] text-accent-soft">
                {{ form.errors.account_id }}
            </p>

            <p class="label-caps mt-3 mb-1.5 lg:mt-4">Tag</p>
            <div v-if="availableTags.length > 0" class="flex flex-wrap gap-[5px] lg:gap-1.5">
                <Chip
                    v-for="tag in availableTags"
                    :key="tag.id"
                    :selected="tag.id === form.tag_id"
                    @click="form.tag_id = tag.id"
                >
                    {{ tag.name }}
                </Chip>
            </div>
            <p v-else class="text-[11px] text-ink-muted">
                Ce compte n'a plus de tag.
                <Link :href="routes.tagsFor(form.account_id)" class="text-accent-soft">En ajouter un</Link>.
            </p>

            <div class="mt-4 flex items-center justify-between lg:mt-5">
                <div>
                    <p class="text-[13px]">Récurrente</p>
                    <p class="text-[10px] text-ink-muted lg:text-[11px]">Recréée automatiquement chaque mois</p>
                </div>
                <RecurringSwitch v-model="form.is_recurring" label="Opération récurrente" />
            </div>

            <button type="submit" class="btn-outline mt-4 w-full py-2.5 text-[13px] lg:mt-5" :disabled="form.processing">
                {{ submitLabel }}
            </button>
        </form>
    </div>
</template>
