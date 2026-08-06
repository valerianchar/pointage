<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AccountTypePicker from '../../components/AccountTypePicker.vue';
import FormField from '../../components/FormField.vue';
import PhIcon from '../../components/PhIcon.vue';
import TagPill from '../../components/TagPill.vue';
import { routes } from '../../routes';

const props = defineProps({
    /** @type {{value: string, label: string, icon: string, default_tags: string[]}[]} */
    types: { type: Array, required: true },
});

const form = useForm({
    name: '',
    initial_balance: '',
    type: props.types[0].value,
});

const selectedTypeTags = computed(
    () => props.types.find((type) => type.value === form.type)?.default_tags ?? [],
);

function submit() {
    form.post(routes.accountStore);
}
</script>

<template>
    <Head title="Déclarer un compte" />

    <div class="max-w-[540px]">
        <div class="flex items-center gap-2 lg:gap-2.5">
            <Link :href="routes.dashboard" class="text-[18px] text-accent lg:text-[19px]" aria-label="Retour à l'accueil">
                <PhIcon name="ph-caret-left" />
            </Link>
            <h1 class="text-xl lg:text-[22px]">Déclarer un compte</h1>
        </div>

        <form class="mt-3.5 lg:mt-4" @submit.prevent="submit">
            <div class="grid gap-3 lg:grid-cols-2 lg:gap-3.5">
                <FormField label="Nom du compte" :error="form.errors.name">
                    <input v-model="form.name" type="text" class="field" placeholder="Ex. PEA Fortuneo" />
                </FormField>

                <FormField label="Solde actuel" :error="form.errors.initial_balance">
                    <input
                        v-model="form.initial_balance"
                        type="text"
                        inputmode="decimal"
                        class="field"
                        placeholder="0,00 €"
                    />
                </FormField>
            </div>

            <p class="label-caps mt-3.5 mb-1.5 lg:mt-4">Type de compte</p>
            <AccountTypePicker v-model="form.type" :types="props.types" />
            <p v-if="form.errors.type" class="mt-1.5 text-[13px] text-accent-soft">{{ form.errors.type }}</p>

            <p class="mt-3 mb-1.5 text-[12px] text-ink-muted lg:mt-3.5 lg:text-[11px]">
                Tags créés par défaut pour ce type :
            </p>
            <div class="flex flex-wrap gap-[5px] lg:gap-1.5">
                <TagPill v-for="tag in selectedTypeTags" :key="tag">{{ tag }}</TagPill>
            </div>

            <button type="submit" class="btn-outline mt-4 w-full py-2.5 text-[15px]" :disabled="form.processing">
                Créer le compte
            </button>
        </form>
    </div>
</template>
