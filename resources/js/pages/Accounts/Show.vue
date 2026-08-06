<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import Amount from '../../components/Amount.vue';
import AssetSearchField from '../../components/AssetSearchField.vue';
import CreditSummaryCard from '../../components/CreditSummaryCard.vue';
import Gauge from '../../components/Gauge.vue';
import PhIcon from '../../components/PhIcon.vue';
import TagSpendingBars from '../../components/TagSpendingBars.vue';
import TransactionList from '../../components/TransactionList.vue';
import TransactionTable from '../../components/TransactionTable.vue';
import { shareOf } from '../../money';
import { routes } from '../../routes';

const props = defineProps({
    /** @type {{id: number, asset_id: string, label: string, quantity: string, price_cents: number|null, value_cents: number|null, price_date_label: string|null, delete_url: string}[]} */
    positions: { type: Array, default: () => [] },
    account: { type: Object, required: true },
    month_label: { type: String, required: true },
    transactions: { type: Array, required: true },
    tag_spending: { type: Array, required: true },
    credits: { type: Array, required: true },
    add_url: { type: String, required: true },
    /** @type {{id: number|null, name: string, status: string, is_me: boolean, remove_url: string|null}[]} */
    members: { type: Array, default: () => [] },
    can_invite: { type: Boolean, default: false },
    invite_url: { type: String, default: '' },
    /** @type {{requester_name: string, is_requester: boolean, i_approved: boolean, approvals_count: number, voters_count: number, approve_url: string, refuse_url: string}|null} */
    deletion_request: { type: Object, default: null },
});

/* Comptes à positions — crypto, PEA : le compte suit la valeur du portefeuille. */
const showPositions = computed(() => props.account.has_positions || props.positions.length > 0);
const addingPosition = ref(false);
const positionForm = useForm({ asset_id: '', quantity: '' });

function submitPosition() {
    positionForm.post(`${props.account.url}/positions`, {
        preserveScroll: true,
        onSuccess: () => {
            positionForm.reset();
            addingPosition.value = false;
        },
    });
}

function deletePosition(position) {
    router.delete(position.delete_url, { preserveScroll: true });
}

function syncPositions() {
    router.post(`${props.account.url}/positions/synchroniser`, {}, { preserveScroll: true });
}

const confirmingDeletion = ref(false);

function deleteAccount() {
    router.delete(props.account.delete_url, {
        // Compte partagé : la demande part et la fiche reste — le dialogue se referme.
        onSuccess: () => (confirmingDeletion.value = false),
    });
}

/* Compte joint : le propriétaire invite par e-mail exact, chacun peut se retirer. */
const inviting = ref(false);
const inviteForm = useForm({ email: '' });

/*
 * Tant qu'une invitation attend sa réponse, le compte joint reste en salle
 * d'attente : pas d'opérations ni de pointage — on saisit à plusieurs, ou pas.
 * Retirer l'invitation en souffrance rouvre le compte aussitôt.
 */
const pendingMembers = computed(() => props.members.filter((member) => member.status === 'pending'));
const awaitingMembers = computed(() => props.account.type === 'joint' && pendingMembers.value.length > 0);

/* Compte partagé : supprimer devient une demande, chaque membre devant accepter. */
const hasOtherMembers = computed(() => props.members.some((member) => member.status === 'member'));

const MEMBER_STATUS = {
    owner: 'Propriétaire',
    member: 'Membre',
    pending: 'Invitation en attente',
};

function submitInvite() {
    inviteForm.post(props.invite_url, {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
            inviting.value = false;
        },
    });
}

function removeMember(member) {
    router.delete(member.remove_url, { preserveScroll: true });
}

/* Réévaluation : recale le solde sur la valeur affichée par le courtier. */
const revaluing = ref(false);
const revaluationForm = useForm({ current_value: '' });

function submitRevaluation() {
    revaluationForm.post(props.account.revalue_url, {
        preserveScroll: true,
        onSuccess: () => {
            revaluationForm.reset();
            revaluing.value = false;
        },
    });
}

/* Les opérations à venir ne sont pas encore dans le cycle : ni pointées, ni à pointer. */
const inCycle = computed(() => props.transactions.filter((transaction) => !transaction.is_upcoming));
const pendingCount = computed(() => inCycle.value.filter((transaction) => !transaction.is_pointed).length);
const pointedCount = computed(() => inCycle.value.length - pendingCount.value);

const pointingPercent = computed(() =>
    inCycle.value.length === 0 ? 100 : shareOf(pointedCount.value, inCycle.value.length),
);
const pointingLabel = computed(() =>
    pendingCount.value > 0 ? `${pendingCount.value} à pointer` : 'Tout est pointé',
);
</script>

<template>
    <Head :title="props.account.name" />

    <div class="max-w-[980px]">
        <div class="flex items-center gap-2 lg:gap-2.5">
            <Link :href="routes.dashboard" class="text-[18px] text-accent lg:text-[19px]" aria-label="Retour à l'accueil">
                <PhIcon name="ph-caret-left" />
            </Link>
            <div>
                <h1 class="text-[17px] lg:text-[22px]">{{ props.account.name }}</h1>
                <p class="text-[12px] text-ink-muted lg:text-[11px]">{{ props.account.type_label }}</p>
            </div>
            <div class="hidden flex-1 lg:block" />
            <Amount :cents="props.account.balance_cents" class="hidden text-[26px] font-medium lg:block" />
        </div>

        <p class="mt-3 text-[28px] font-medium lg:hidden"><Amount :cents="props.account.balance_cents" /></p>

        <!-- Suppression demandée : chaque membre tranche, l'unanimité emporte le compte. -->
        <div v-if="props.deletion_request" class="mt-3 rounded-card border border-ink-muted bg-surface p-4 lg:mt-5">
            <p class="flex items-center gap-2 text-[15px] font-medium lg:text-[14px]">
                <PhIcon name="ph-trash" class="text-[18px] text-ink-muted" />
                Suppression demandée par {{ props.deletion_request.requester_name }}
            </p>
            <p class="mt-1.5 text-[13px] text-ink-muted lg:text-[12px]">
                {{ props.deletion_request.approvals_count }} accord{{ props.deletion_request.approvals_count > 1 ? 's' : '' }}
                sur {{ props.deletion_request.voters_count }} — le compte disparaîtra, avec tout son
                historique, quand chaque membre aura accepté. Un seul refus l'annule.
            </p>
            <div class="mt-3 flex gap-2">
                <button
                    v-if="!props.deletion_request.i_approved"
                    type="button"
                    class="btn-outline px-3 py-1.5 text-[13px] lg:text-[12px]"
                    @click="router.post(props.deletion_request.approve_url)"
                >
                    Supprimer
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-card border border-hairline px-3 py-1.5 text-[13px] text-ink-muted transition-colors hover:border-outline-muted lg:text-[12px]"
                    @click="router.delete(props.deletion_request.refuse_url)"
                >
                    {{ props.deletion_request.is_requester ? 'Annuler ma demande' : 'Refuser' }}
                </button>
            </div>
        </div>

        <!-- Salle d'attente du compte joint : tout le monde n'a pas encore répondu. -->
        <div v-if="awaitingMembers" class="mt-3 rounded-card border border-accent bg-surface p-4 lg:mt-5">
            <p class="flex items-center gap-2 text-[15px] font-medium lg:text-[14px]">
                <PhIcon name="ph-clock-countdown" class="text-[18px] text-accent-soft" />
                En attente des membres
            </p>
            <p class="mt-1.5 text-[13px] text-ink-muted lg:text-[12px]">
                {{ pendingMembers.map((member) => member.name).join(', ') }}
                {{ pendingMembers.length > 1 ? "n'ont pas encore accepté" : "n'a pas encore accepté" }}
                l'invitation. Le compte s'ouvrira — opérations, pointage — quand chacun aura répondu.
                Retirer une invitation en attente le rouvre aussitôt.
            </p>
        </div>

        <div v-if="!awaitingMembers" class="mt-2.5 grid gap-3.5 lg:mt-5 lg:grid-cols-2">
            <div class="rounded-card bg-surface px-3 py-2.5 lg:p-4">
                <div class="flex justify-between text-[13px] lg:text-[12px]">
                    <span>Pointage</span>
                    <span class="text-accent-soft">{{ pointingLabel }}</span>
                </div>
                <Gauge :percent="pointingPercent" label="Progression du pointage" class="mt-1.5 lg:mt-2.5" />
                <Link
                    v-if="props.account.pending_count > 0"
                    :href="`${routes.bilan}?cloture=${props.account.id}&pointer=1`"
                    class="mt-2.5 block text-[13px] text-accent-soft transition-colors hover:text-ink lg:mt-3 lg:text-[12px]"
                >
                    Pointer maintenant, relevé en main →
                </Link>
                <!-- Avec des positions, la synchro remplace la saisie manuelle :
                     les deux gestes recaleraient le même solde, chacun de son côté. -->
                <button
                    v-if="props.positions.length > 0"
                    type="button"
                    class="mt-2 flex cursor-pointer items-center gap-1.5 text-[13px] text-accent-soft transition-colors hover:text-ink lg:mt-2.5 lg:text-[12px]"
                    @click="syncPositions"
                >
                    <PhIcon name="ph-arrows-clockwise" class="text-[14px]" />
                    Rafraîchir — cours et solde du jour
                </button>
                <button
                    v-else
                    type="button"
                    class="mt-2 flex cursor-pointer items-center gap-1.5 text-[13px] text-accent-soft transition-colors hover:text-ink lg:mt-2.5 lg:text-[12px]"
                    @click="revaluing = true"
                >
                    <PhIcon name="ph-scales" class="text-[14px]" />
                    Réévaluer — recaler sur la valeur réelle
                </button>
            </div>

            <!-- La maquette pose ces barres à même le fond sur mobile, en carte sur desktop. -->
            <div v-if="props.tag_spending.length > 0" class="lg:rounded-card lg:bg-surface lg:p-4">
                <p class="label-caps mb-1.5 lg:mb-2 lg:text-[11px] lg:normal-case lg:tracking-normal">
                    Dépenses par tag
                </p>
                <TagSpendingBars :spending="props.tag_spending" />
            </div>
        </div>

        <template v-if="showPositions && !awaitingMembers">
            <p class="label-caps mt-4 lg:mt-[22px]">Positions</p>

            <div v-if="props.positions.length > 0" class="mt-1.5 flex flex-col gap-[7px] lg:gap-2">
                <div
                    v-for="position in props.positions"
                    :key="position.id"
                    class="flex items-center gap-2.5 rounded-card bg-surface px-3 py-2.5 lg:gap-3 lg:px-4 lg:py-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="text-[15px] font-medium lg:text-[13px]">{{ position.label }}</p>
                        <p class="mt-px text-[12px] text-ink-muted lg:text-[11px]">
                            {{ position.quantity }} ×
                            <Amount v-if="position.price_cents !== null" :cents="position.price_cents" />
                            <span v-else>cours inconnu</span>
                            <span v-if="position.price_date_label"> · cours du {{ position.price_date_label }}</span>
                        </p>
                    </div>
                    <Amount
                        v-if="position.value_cents !== null"
                        :cents="position.value_cents"
                        class="shrink-0 text-[15px] lg:text-[14px]"
                    />
                    <button
                        type="button"
                        class="shrink-0 cursor-pointer text-[14px] text-ink-muted transition-colors hover:text-accent-soft"
                        :aria-label="`Supprimer la position ${position.label}`"
                        @click="deletePosition(position)"
                    >
                        <PhIcon name="ph-x" />
                    </button>
                </div>
            </div>

            <p v-else class="mt-1.5 text-[13px] text-ink-muted lg:text-[12px]">
                Déclarez vos avoirs : le compte suivra leur valeur chaque nuit, sans rien à pointer.
            </p>

            <button
                v-if="!addingPosition"
                type="button"
                class="mt-2 cursor-pointer text-[13px] text-accent-soft transition-colors hover:text-ink lg:text-[12px]"
                @click="addingPosition = true"
            >
                + Déclarer une position
            </button>
            <form v-else class="mt-2 rounded-card bg-surface p-3" @submit.prevent="submitPosition">
                <div class="flex gap-1.5 lg:gap-2">
                    <AssetSearchField
                        v-model="positionForm.asset_id"
                        :account-type="props.account.type"
                        class="flex-1"
                        input-class="bg-page!"
                        :placeholder="props.account.position_placeholder"
                    />
                    <input
                        v-model="positionForm.quantity"
                        type="text"
                        inputmode="decimal"
                        class="field w-[110px] shrink-0 bg-page!"
                        placeholder="Quantité"
                        aria-label="Quantité"
                    />
                    <button
                        type="submit"
                        class="btn-outline shrink-0 px-3 text-[14px] lg:text-[13px]"
                        :disabled="positionForm.processing"
                    >
                        OK
                    </button>
                </div>
                <p v-if="positionForm.errors.asset_id" class="mt-1.5 text-[13px] text-accent-soft">
                    {{ positionForm.errors.asset_id }}
                </p>
                <p v-if="positionForm.errors.quantity" class="mt-1.5 text-[13px] text-accent-soft">
                    {{ positionForm.errors.quantity }}
                </p>
            </form>
            <p class="mt-1.5 text-[11px] text-ink-faint lg:text-[10px]">
                Cours fournis par {{ props.account.price_source }}, rafraîchis chaque nuit à 5 h 30 — le solde
                du compte est recalé sur la valeur totale des positions.
            </p>
        </template>

        <!-- Compte joint : qui partage ce compte, et l'invitation en un e-mail. -->
        <template v-if="props.account.type === 'joint'">
            <p class="label-caps mt-4 lg:mt-[22px]">Membres</p>

            <div class="mt-1.5 flex flex-col gap-[7px] lg:gap-2">
                <div
                    v-for="member in props.members"
                    :key="member.id ?? 'proprietaire'"
                    class="flex items-center gap-2.5 rounded-card bg-surface px-3 py-2.5 lg:gap-3 lg:px-4 lg:py-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[15px] font-medium lg:text-[13px]">
                            {{ member.name }}<span v-if="member.is_me" class="text-ink-muted"> — vous</span>
                        </p>
                        <p
                            class="mt-px text-[12px] lg:text-[11px]"
                            :class="member.status === 'pending' ? 'text-accent-soft' : 'text-ink-muted'"
                        >
                            {{ MEMBER_STATUS[member.status] }}
                        </p>
                    </div>
                    <button
                        v-if="member.remove_url && member.is_me"
                        type="button"
                        class="shrink-0 cursor-pointer text-[13px] text-ink-muted transition-colors hover:text-accent-soft lg:text-[12px]"
                        @click="removeMember(member)"
                    >
                        Quitter
                    </button>
                    <button
                        v-else-if="member.remove_url"
                        type="button"
                        class="shrink-0 cursor-pointer text-[14px] text-ink-muted transition-colors hover:text-accent-soft"
                        :aria-label="`Retirer ${member.name} du compte`"
                        @click="removeMember(member)"
                    >
                        <PhIcon name="ph-x" />
                    </button>
                </div>
            </div>

            <button
                v-if="props.can_invite && !inviting"
                type="button"
                class="mt-2 cursor-pointer text-[13px] text-accent-soft transition-colors hover:text-ink lg:text-[12px]"
                @click="inviting = true"
            >
                + Inviter un membre
            </button>
            <form v-else-if="props.can_invite" class="mt-2 rounded-card bg-surface p-3" @submit.prevent="submitInvite">
                <div class="flex gap-1.5 lg:gap-2">
                    <input
                        v-model="inviteForm.email"
                        type="email"
                        class="field min-w-0 flex-1 bg-page!"
                        placeholder="E-mail exact de la personne"
                        aria-label="E-mail de la personne à inviter"
                    />
                    <button
                        type="submit"
                        class="btn-outline shrink-0 px-3 text-[14px] lg:text-[13px]"
                        :disabled="inviteForm.processing"
                    >
                        Inviter
                    </button>
                </div>
                <p v-if="inviteForm.errors.email" class="mt-1.5 text-[13px] text-accent-soft">
                    {{ inviteForm.errors.email }}
                </p>
            </form>
            <p v-if="props.can_invite" class="mt-1.5 text-[11px] text-ink-faint lg:text-[10px]">
                La personne doit déjà avoir son profil ici. Elle recevra l'invitation sur son accueil —
                le compte ne lui est ouvert qu'après acceptation.
            </p>
        </template>

        <template v-if="props.credits.length > 0 && !awaitingMembers">
            <p class="label-caps mt-4 mb-1.5 lg:mt-[22px] lg:mb-2">Crédits sur ce compte</p>
            <div class="flex flex-col gap-[7px] lg:grid lg:grid-cols-2 lg:gap-3.5">
                <CreditSummaryCard v-for="credit in props.credits" :key="credit.id" :credit="credit" />
            </div>
        </template>

        <template v-if="!awaitingMembers">
            <div class="mt-4 flex items-baseline justify-between lg:mt-[26px]">
                <p class="label-caps">Opérations — {{ props.month_label.toLowerCase() }}</p>
                <Link :href="props.add_url" class="text-[13px] text-accent-soft lg:text-[12px]">+ Ajouter</Link>
            </div>

            <p v-if="props.transactions.length === 0" class="mt-3 text-[15px] text-ink-muted">
                Aucune opération ce mois-ci.
            </p>

            <TransactionList :transactions="props.transactions" class="mt-0.5" />
            <TransactionTable :transactions="props.transactions" class="mt-1" />
        </template>

        <!-- Suppression du compte : rare et irréversible, donc reléguée tout en
             bas et protégée par une confirmation explicite. -->
        <button
            type="button"
            class="mt-6 flex cursor-pointer items-center gap-1.5 text-[13px] text-ink-muted transition-colors hover:text-accent-soft lg:mt-8 lg:text-[12px]"
            @click="confirmingDeletion = true"
        >
            <PhIcon name="ph-trash" class="text-[14px]" />
            Supprimer ce compte
        </button>

        <DialogRoot :open="revaluing" @update:open="(open) => (revaluing = open)">
            <DialogPortal>
                <DialogOverlay class="fixed inset-0 z-80 bg-[rgba(10,11,20,0.6)]" />
                <DialogContent
                    class="fixed top-1/2 left-1/2 z-90 w-[calc(100vw-3rem)] max-w-[400px] -translate-x-1/2 -translate-y-1/2 rounded-card border border-hairline bg-chrome p-4 outline-none lg:p-5"
                >
                    <DialogTitle class="text-[16px] font-medium">Réévaluer « {{ props.account.name }} »</DialogTitle>
                    <DialogDescription class="mt-1.5 text-[13px] text-ink-muted lg:text-[12px]">
                        Saisissez la valeur affichée par votre courtier ou votre plateforme : la différence devient
                        une opération « Réévaluation marché », déjà pointée, exclue des statistiques de dépenses.
                    </DialogDescription>
                    <form class="mt-3" @submit.prevent="submitRevaluation">
                        <p class="label-caps mb-[5px]">Valeur actuelle du compte</p>
                        <input
                            v-model="revaluationForm.current_value"
                            type="text"
                            inputmode="decimal"
                            class="field"
                            placeholder="0,00 €"
                        />
                        <p v-if="revaluationForm.errors.current_value" class="mt-1.5 text-[13px] text-accent-soft">
                            {{ revaluationForm.errors.current_value }}
                        </p>
                        <div class="mt-3.5 flex gap-2">
                            <button
                                type="submit"
                                class="btn-outline flex-1 py-2.5 text-[14px] lg:text-[13px]"
                                :disabled="revaluationForm.processing"
                            >
                                Réévaluer
                            </button>
                            <button
                                type="button"
                                class="shrink-0 cursor-pointer rounded-card border border-hairline px-3.5 text-[14px] text-ink-muted transition-colors hover:border-outline-muted lg:text-[13px]"
                                @click="revaluing = false"
                            >
                                Annuler
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </DialogPortal>
        </DialogRoot>

        <DialogRoot :open="confirmingDeletion" @update:open="(open) => (confirmingDeletion = open)">
            <DialogPortal>
                <DialogOverlay class="fixed inset-0 z-80 bg-[rgba(10,11,20,0.6)]" />
                <DialogContent
                    class="fixed top-1/2 left-1/2 z-90 w-[calc(100vw-3rem)] max-w-[400px] -translate-x-1/2 -translate-y-1/2 rounded-card border border-hairline bg-chrome p-4 outline-none lg:p-5"
                >
                    <DialogTitle class="text-[16px] font-medium">Supprimer « {{ props.account.name }} » ?</DialogTitle>
                    <DialogDescription class="mt-1.5 text-[13px] text-ink-muted lg:text-[12px]">
                        <template v-if="hasOtherMembers">
                            Ce compte est partagé : votre demande sera envoyée aux autres membres, et le compte —
                            avec tout son historique — ne disparaîtra que lorsque chacun aura accepté. Un seul
                            refus l'annule.
                        </template>
                        <template v-else>
                            Le compte disparaît avec tout son historique : opérations, récurrentes, tags, crédits et
                            clôtures. Il n'y a pas de retour en arrière.
                        </template>
                    </DialogDescription>
                    <div class="mt-3.5 flex gap-2">
                        <button
                            type="button"
                            class="btn-outline flex-1 py-2.5 text-[14px] lg:text-[13px]"
                            @click="deleteAccount"
                        >
                            {{ hasOtherMembers ? 'Demander la suppression' : 'Supprimer définitivement' }}
                        </button>
                        <button
                            type="button"
                            class="shrink-0 cursor-pointer rounded-card border border-hairline px-3.5 text-[14px] text-ink-muted transition-colors hover:border-outline-muted lg:text-[13px]"
                            @click="confirmingDeletion = false"
                        >
                            Annuler
                        </button>
                    </div>
                </DialogContent>
            </DialogPortal>
        </DialogRoot>
    </div>
</template>
