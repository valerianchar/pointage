<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AccountTypePicker from '../../components/AccountTypePicker.vue';
import AssetSearchField from '../../components/AssetSearchField.vue';
import FormField from '../../components/FormField.vue';
import PhIcon from '../../components/PhIcon.vue';
import TagPill from '../../components/TagPill.vue';
import { routes } from '../../routes';

const props = defineProps({
    /** @type {{value: string, label: string, icon: string, default_tags: string[], has_positions: boolean, position_placeholder: string|null, price_source: string|null}[]} */
    types: { type: Array, required: true },
});

const form = useForm({
    name: '',
    initial_balance: '',
    type: props.types[0].value,
    /* Comptes à positions (crypto, PEA) : les avoirs remplacent le solde. */
    positions: [{ asset_id: '', quantity: '' }],
    /* Compte joint : il se partage, au moins une personne à inviter. */
    members: [''],
});

const selectedType = computed(() => props.types.find((type) => type.value === form.type) ?? props.types[0]);
const selectedTypeTags = computed(() => selectedType.value.default_tags);

/* Les erreurs d'une ligne arrivent sous « positions.2.asset_id » : chacune s'affiche sous la sienne. */
function errorsOfRow(index) {
    return Object.entries(form.errors)
        .filter(([key]) => key.startsWith(`positions.${index}.`))
        .map(([, message]) => message);
}

const isJoint = computed(() => form.type === 'joint');

function addMemberRow() {
    form.members.push('');
}

function removeMemberRow(index) {
    form.members.splice(index, 1);
}

const memberErrors = computed(() =>
    [...new Set(Object.entries(form.errors)
        .filter(([key]) => key === 'members' || key.startsWith('members.'))
        .map(([, message]) => message))],
);

function addPositionRow() {
    form.positions.push({ asset_id: '', quantity: '' });
}

function removePositionRow(index) {
    form.positions.splice(index, 1);
}

function submit() {
    /*
     * Les lignes vides sont retirées du formulaire même, pas seulement de
     * l'envoi : les erreurs « positions.0… » retombent ainsi sous la bonne
     * ligne à l'écran.
     */
    if (selectedType.value.has_positions) {
        form.positions = form.positions.filter(
            (position) => position.asset_id.trim() !== '' || position.quantity !== '',
        );
    }

    if (isJoint.value) {
        form.members = form.members.filter((email) => email.trim() !== '');
    }

    form.transform((data) => ({
        ...data,
        /* Seuls les comptes à positions en envoient. */
        positions: selectedType.value.has_positions ? data.positions : [],
        /* Et seuls les comptes joints invitent. */
        members: isJoint.value ? data.members : [],
    })).post(routes.accountStore, {
        onError: () => {
            if (selectedType.value.has_positions && form.positions.length === 0) {
                addPositionRow();
            }
            if (isJoint.value && form.members.length === 0) {
                addMemberRow();
            }
        },
    });
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

                <FormField v-if="!selectedType.has_positions" label="Solde actuel" :error="form.errors.initial_balance">
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

            <!-- Comptes à positions : on déclare ses avoirs, pas un solde — il
                 naît de la valeur du portefeuille au cours du jour. -->
            <template v-if="selectedType.has_positions">
                <p class="label-caps mt-3.5 mb-1.5 lg:mt-4">Positions</p>
                <div class="flex flex-col gap-1.5 lg:gap-2">
                    <div v-for="(position, index) in form.positions" :key="index">
                        <div class="flex gap-1.5 lg:gap-2">
                            <AssetSearchField
                                v-model="position.asset_id"
                                :account-type="form.type"
                                class="flex-1"
                                :placeholder="selectedType.position_placeholder"
                                :aria-label="`Actif de la position ${index + 1}`"
                            />
                            <input
                                v-model="position.quantity"
                                type="text"
                                inputmode="decimal"
                                class="field w-[110px] shrink-0"
                                placeholder="Quantité"
                                :aria-label="`Quantité de la position ${index + 1}`"
                            />
                            <button
                                v-if="form.positions.length > 1"
                                type="button"
                                class="shrink-0 cursor-pointer px-1 text-[14px] text-ink-muted transition-colors hover:text-accent-soft"
                                :aria-label="`Retirer la position ${index + 1}`"
                                @click="removePositionRow(index)"
                            >
                                <PhIcon name="ph-x" />
                            </button>
                        </div>
                        <!-- Chaque ligne porte ses propres manques, sous elle. -->
                        <p v-for="message in errorsOfRow(index)" :key="message" class="mt-1 text-[13px] text-accent-soft">
                            {{ message }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="mt-2 cursor-pointer text-[13px] text-accent-soft transition-colors hover:text-ink lg:text-[12px]"
                    @click="addPositionRow"
                >
                    + Ajouter une position
                </button>
                <p v-if="form.errors.positions" class="mt-1.5 text-[13px] text-accent-soft">
                    {{ form.errors.positions }}
                </p>
                <p class="mt-1.5 text-[11px] text-ink-faint lg:text-[10px]">
                    Le solde du compte naîtra de la valeur de ces positions, cours
                    {{ selectedType.price_source }} du jour, puis suivra le marché chaque nuit.
                </p>
            </template>

            <!-- Compte joint : il se partage — au moins une personne à inviter,
                 le compte s'ouvrira quand chacun aura accepté. -->
            <template v-if="isJoint">
                <p class="label-caps mt-3.5 mb-1.5 lg:mt-4">Membres à inviter</p>
                <div class="flex flex-col gap-1.5 lg:gap-2">
                    <div v-for="(email, index) in form.members" :key="index" class="flex gap-1.5 lg:gap-2">
                        <input
                            v-model="form.members[index]"
                            type="email"
                            class="field min-w-0 flex-1"
                            placeholder="E-mail exact de la personne"
                            :aria-label="`E-mail du membre ${index + 1}`"
                        />
                        <button
                            v-if="form.members.length > 1"
                            type="button"
                            class="shrink-0 cursor-pointer px-1 text-[14px] text-ink-muted transition-colors hover:text-accent-soft"
                            :aria-label="`Retirer le membre ${index + 1}`"
                            @click="removeMemberRow(index)"
                        >
                            <PhIcon name="ph-x" />
                        </button>
                    </div>
                </div>
                <button
                    type="button"
                    class="mt-2 cursor-pointer text-[13px] text-accent-soft transition-colors hover:text-ink lg:text-[12px]"
                    @click="addMemberRow"
                >
                    + Inviter une autre personne
                </button>
                <p v-for="message in memberErrors" :key="message" class="mt-1.5 text-[13px] text-accent-soft">
                    {{ message }}
                </p>
                <p class="mt-1.5 text-[11px] text-ink-faint lg:text-[10px]">
                    Chaque personne doit déjà avoir son profil ici. Le compte restera en attente —
                    sans opérations ni pointage — jusqu'à ce que chacune ait accepté.
                </p>
            </template>

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
