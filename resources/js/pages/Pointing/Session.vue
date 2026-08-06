<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Amount from '../../components/Amount.vue';
import Chip from '../../components/Chip.vue';
import PhIcon from '../../components/PhIcon.vue';
import TagPill from '../../components/TagPill.vue';
import { routes } from '../../routes';

const props = defineProps({
    account: { type: Object, required: true },
    period_label: { type: String, required: true },
    transactions: { type: Array, required: true },
    tags: { type: Array, required: true },
});

/**
 * La file se déduit des props — rafraîchies par le serveur après chaque geste —
 * moins les opérations déjà traitées pendant la session, gardées en local.
 * Le total se recalcule : un oubli ajouté en cours de route allonge la file.
 */
const pointedIds = ref(new Set());
const skippedIds = ref(new Set());
const isPointing = ref(false);

const queue = computed(() =>
    props.transactions.filter(
        (transaction) => !pointedIds.value.has(transaction.id) && !skippedIds.value.has(transaction.id),
    ),
);
const current = computed(() => queue.value[0] ?? null);

const doneCount = computed(() => pointedIds.value.size + skippedIds.value.size);
const totalCount = computed(() => doneCount.value + queue.value.length);
const progressPercent = computed(() =>
    totalCount.value === 0 ? 100 : Math.round((doneCount.value / totalCount.value) * 100),
);

function pointCurrent() {
    // La route de pointage bascule l'état : un double envoi dé-pointerait.
    if (isPointing.value) {
        return;
    }

    const transaction = current.value;
    isPointing.value = true;

    router.patch(
        transaction.pointing_url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => (pointedIds.value = new Set([...pointedIds.value, transaction.id])),
            onFinish: () => (isPointing.value = false),
        },
    );
}

function skipCurrent() {
    skippedIds.value = new Set([...skippedIds.value, current.value.id]);
}

/* Ajout d'un oubli sans quitter la session. */
const isAdding = ref(false);

const addForm = useForm({
    account_id: props.account.id,
    direction: 'depense',
    amount: '',
    label: '',
    tag_id: props.tags[0]?.id ?? null,
    is_recurring: false,
    stay: true,
});

function submitAddition() {
    addForm.post(routes.transactionStore, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            addForm.reset('amount', 'label');
            isAdding.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Pointage — ${props.account.name}`" />

    <div class="mx-auto max-w-[560px]">
        <div class="flex items-center gap-2 lg:gap-2.5">
            <Link :href="props.account.url" aria-label="Retour au compte">
                <PhIcon name="ph-caret-left" class="text-[18px] text-accent" />
            </Link>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-[17px] font-medium lg:text-[20px]">Pointage — {{ props.account.name }}</h1>
                <p class="text-[12px] text-ink-muted lg:text-[11px]">Période {{ props.period_label.toLowerCase() }}</p>
            </div>
            <Amount :cents="props.account.balance_cents" class="shrink-0 text-[15px] lg:text-[16px]" />
        </div>

        <!-- Progression de la session. -->
        <div class="mt-3 flex items-center gap-2.5 lg:mt-4">
            <div class="h-1 flex-1 overflow-hidden rounded-xs bg-gauge-track">
                <div
                    class="h-1 rounded-xs bg-gauge transition-[width] duration-300 ease-out"
                    :style="{ width: `${progressPercent}%` }"
                />
            </div>
            <span class="shrink-0 text-[12px] text-ink-muted lg:text-[11px]">
                {{ doneCount }} / {{ totalCount }}
            </span>
        </div>

        <!-- L'opération courante, seule au centre : on la cherche sur le relevé. -->
        <div v-if="current" class="mt-4 rounded-card bg-surface p-4 lg:mt-5 lg:p-5">
            <p class="text-[12px] text-ink-muted lg:text-[11px]">{{ current.date_label }}</p>
            <div class="mt-1.5 flex items-start justify-between gap-3">
                <p class="min-w-0 text-[17px] font-medium lg:text-[18px]">{{ current.label }}</p>
                <Amount
                    :cents="current.amount_cents"
                    signed
                    class="shrink-0 text-[17px] lg:text-[18px]"
                    :class="current.amount_cents > 0 ? 'text-accent-soft' : ''"
                />
            </div>
            <div class="mt-1.5 flex items-center gap-1.5">
                <TagPill v-if="current.tag">{{ current.tag }}</TagPill>
                <PhIcon v-if="current.is_recurring" name="ph-arrows-clockwise" class="text-[13px] text-ink-muted" />
            </div>

            <p class="mt-3.5 text-[12px] text-ink-muted lg:text-[11px]">
                Cette opération apparaît-elle sur votre relevé ?
            </p>
            <div class="mt-2 flex gap-2">
                <button
                    type="button"
                    class="btn-outline flex-1 py-2.5 text-[15px] lg:text-[13px]"
                    :disabled="isPointing"
                    @click="pointCurrent"
                >
                    Pointée ✓
                </button>
                <button
                    type="button"
                    class="shrink-0 cursor-pointer rounded-card border border-hairline px-4 text-[15px] text-ink-muted transition-colors hover:border-outline-muted lg:text-[13px]"
                    @click="skipCurrent"
                >
                    Passer
                </button>
            </div>
        </div>

        <!-- Fin de la file : le récapitulatif de la session. -->
        <div v-else class="mt-4 rounded-card bg-surface p-4 text-center lg:mt-5 lg:p-6">
            <PhIcon name="ph-check-circle" class="text-[28px] text-accent" />
            <p class="mt-2 text-[17px] font-medium lg:text-[18px]">
                {{ totalCount === 0 ? 'Tout est pointé' : 'Pointage terminé' }}
            </p>
            <p class="mt-1 text-[13px] text-ink-muted lg:text-[12px]">
                <template v-if="pointedIds.size > 0">{{ pointedIds.size }} pointée(s)</template>
                <template v-if="pointedIds.size > 0 && skippedIds.size > 0"> · </template>
                <template v-if="skippedIds.size > 0">{{ skippedIds.size }} passée(s), toujours à pointer</template>
                <template v-if="totalCount === 0">Rien n'attend sur ce compte.</template>
            </p>
            <div class="mt-4 flex flex-col gap-2 lg:flex-row lg:justify-center">
                <Link :href="routes.bilan" class="btn-outline px-4 py-2.5 text-center text-[15px] lg:text-[13px]">
                    Faire le bilan du mois
                </Link>
                <Link
                    :href="props.account.url"
                    class="rounded-card border border-hairline px-4 py-2.5 text-center text-[15px] text-ink-muted transition-colors hover:border-outline-muted lg:text-[13px]"
                >
                    Retour au compte
                </Link>
            </div>
        </div>

        <!-- Un oubli sur le relevé : l'ajouter sans perdre sa place dans la file. -->
        <div class="mt-3 lg:mt-4">
            <button
                v-if="!isAdding"
                type="button"
                class="cursor-pointer text-[13px] text-accent-soft transition-colors hover:text-ink lg:text-[12px]"
                @click="isAdding = true"
            >
                + Une dépense manque sur le relevé ? L'ajouter
            </button>

            <form v-else class="rounded-card bg-surface p-3 lg:p-4" @submit.prevent="submitAddition">
                <p class="label-caps mb-2">Ajouter une dépense oubliée</p>
                <div class="flex gap-1.5 lg:gap-2">
                    <input
                        v-model="addForm.amount"
                        type="text"
                        inputmode="decimal"
                        class="field w-[110px] shrink-0 bg-page!"
                        placeholder="0,00 €"
                        aria-label="Montant"
                    />
                    <input
                        v-model="addForm.label"
                        type="text"
                        class="field min-w-0 flex-1 bg-page!"
                        placeholder="Libellé — ex. Péage"
                        aria-label="Libellé"
                    />
                </div>
                <p v-if="addForm.errors.amount" class="mt-1.5 text-[13px] text-accent-soft">
                    {{ addForm.errors.amount }}
                </p>
                <p v-if="addForm.errors.label" class="mt-1.5 text-[13px] text-accent-soft">
                    {{ addForm.errors.label }}
                </p>

                <div class="mt-2.5 flex flex-wrap gap-[5px] lg:gap-1.5">
                    <Chip
                        v-for="tag in props.tags"
                        :key="tag.id"
                        :selected="tag.id === addForm.tag_id"
                        @click="addForm.tag_id = tag.id"
                    >
                        {{ tag.name }}
                    </Chip>
                </div>

                <div class="mt-3 flex gap-2">
                    <button type="submit" class="btn-outline flex-1 py-2 text-[14px] lg:text-[13px]" :disabled="addForm.processing">
                        Ajouter à la file
                    </button>
                    <button
                        type="button"
                        class="shrink-0 cursor-pointer rounded-card border border-hairline px-3.5 text-[14px] text-ink-muted lg:text-[13px]"
                        @click="isAdding = false"
                    >
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
