<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Amount from '../../components/Amount.vue';
import Chip from '../../components/Chip.vue';
import PhIcon from '../../components/PhIcon.vue';
import PointingToggle from '../../components/PointingToggle.vue';
import TagSpendingBars from '../../components/TagSpendingBars.vue';
import { useMoney } from '../../composables/useMoney';
import { usePushSubscription } from '../../composables/usePushSubscription';
import { routes } from '../../routes';

const props = defineProps({
    month_label: { type: String, required: true },
    activity: { type: Object, required: true },
    closings: { type: Array, required: true },
    closing_account_id: { type: Number, default: null },
    closing_open: { type: Boolean, default: false },
    closing_transactions: { type: Array, default: () => [] },
});

const page = usePage();
const { signedMoney, balancesHidden } = useMoney();

const accounts = computed(() => page.props.accounts);

/**
 * Le placeholder du commentaire diffère entre les deux maquettes — plus court
 * sur mobile — et un placeholder ne se règle pas en CSS.
 */
const desktopQuery = window.matchMedia('(min-width: 64rem)');
const isDesktop = ref(desktopQuery.matches);
const onDesktopChange = (event) => (isDesktop.value = event.matches);

onMounted(() => desktopQuery.addEventListener('change', onDesktopChange));
onUnmounted(() => desktopQuery.removeEventListener('change', onDesktopChange));

const notePlaceholder = computed(() =>
    isDesktop.value ? 'Ex. Oubli : péage + retrait espèces non saisis' : 'Ex. Oubli : péage + espèces',
);

/* ------------------------------------------------------------------ */
/* Rappels de pointage — notification navigateur                       */
/* ------------------------------------------------------------------ */

const push = usePushSubscription();

/* ------------------------------------------------------------------ */
/* Période de pointage — par compte                                    */
/* ------------------------------------------------------------------ */

const periodAccountId = ref(accounts.value[0]?.id ?? null);
const periodAccount = computed(() => accounts.value.find((account) => account.id === periodAccountId.value));

const periodForm = useForm({ period_start_day: '', period_end_day: '' });

watch(
    periodAccount,
    (account) => {
        if (account) {
            periodForm.period_start_day = String(account.period_start_day);
            periodForm.period_end_day = String(account.period_end_day);
        }
    },
    { immediate: true },
);

function periodNote(account, startDay, endDay) {
    return `${account.name} : pointage du ${startDay || '1'} au ${endDay || '31'} de chaque mois.`;
}

/**
 * La période s'enregistre dès que le champ est quitté, sans bouton : sur la
 * maquette, les deux champs sont posés seuls dans la carte.
 */
function savePeriod() {
    const account = periodAccount.value;
    const unchanged =
        Number(periodForm.period_start_day) === account.period_start_day &&
        Number(periodForm.period_end_day) === account.period_end_day;

    if (unchanged || !periodForm.period_start_day || !periodForm.period_end_day) {
        return;
    }

    periodForm.patch(account.period_url, { preserveScroll: true });
}

/* ------------------------------------------------------------------ */
/* Clôture du mois                                                     */
/* ------------------------------------------------------------------ */

// La bannière de rappel et l'écran « Pointage obligatoire » arrivent avec
// ?cloture=X&pointer=1 : le panneau s'ouvre directement sur le bon compte.
const closing = ref(props.closing_open);

const closingForm = useForm({
    account_id: props.closing_account_id ?? accounts.value[0]?.id ?? null,
    real_balance: '',
    note: '',
});

const closingAccount = computed(() => accounts.value.find((account) => account.id === closingForm.account_id));

/**
 * Les opérations à pointer viennent du serveur pour le compte de l'URL :
 * ouvrir le panneau ou changer de compte synchronise l'adresse, et l'état
 * local (panneau ouvert, saisies) survit à la visite.
 */
function openClosingFor(accountId) {
    closing.value = true;
    closingForm.account_id = accountId;

    router.get(
        routes.bilan,
        { cloture: accountId, pointer: 1 },
        { preserveScroll: true, preserveState: true },
    );
}

const pendingClosingCount = computed(
    () => props.closing_transactions.filter((transaction) => !transaction.is_pointed).length,
);
const pendingClosingLabel = computed(() =>
    pendingClosingCount.value > 0 ? `${pendingClosingCount.value} à pointer` : 'Tout est pointé',
);

/** « Clôture par anticipation possible » tant que la période court encore. */
const closingAvailability = computed(() =>
    closingAccount.value.days_until_period_end <= 0
        ? 'Période terminée — clôture disponible.'
        : `Fin de période le ${closingAccount.value.period_end_day} — clôture par anticipation possible.`,
);

const nextPeriodStartDay = computed(() =>
    closingAccount.value.period_end_day >= 31 ? 1 : closingAccount.value.period_end_day + 1,
);

/**
 * L'écart d'un compte de marché est la dérive des cours, pas un oubli : ce
 * bouton le transforme en opération de réévaluation. Le solde théorique
 * rejoint le solde réel, et l'écart affiché retombe à zéro.
 */
const revaluationForm = useForm({ current_value: '' });

function recordVarianceAsRevaluation() {
    revaluationForm.current_value = closingForm.real_balance;
    revaluationForm.post(closingAccount.value.revalue_url, {
        preserveScroll: true,
        preserveState: true,
    });
}

/* Un oubli repéré sur le relevé : ajouté ici, il naît pointé. */
const additionForm = useForm({
    account_id: null,
    direction: 'depense',
    amount: '',
    label: '',
    pointed: true,
    stay: true,
});

function submitAddition() {
    additionForm.account_id = closingForm.account_id;
    additionForm.post(routes.transactionStore, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => additionForm.reset('amount', 'label'),
    });
}

/**
 * Écart affiché en direct pendant la saisie ; le serveur reste seul juge du
 * montant réellement enregistré.
 */
const realBalanceCents = computed(() => {
    const raw = closingForm.real_balance.replaceAll(/[\s€]/g, '').replace(',', '.');

    if (raw === '' || Number.isNaN(Number(raw))) {
        return null;
    }

    return Math.round(Number(raw) * 100);
});

const varianceCents = computed(() =>
    realBalanceCents.value === null ? null : realBalanceCents.value - closingAccount.value.balance_cents,
);

const varianceMessage = computed(() => {
    if (varianceCents.value === null) {
        return '';
    }

    if (varianceCents.value === 0) {
        return 'Parfait — votre compte réel correspond au pointage.';
    }

    return varianceCents.value > 0
        ? 'Compte réel au-dessus du pointage : il manque probablement des ajouts à saisir.'
        : 'Compte réel en dessous du pointage : il manque probablement des dépenses à saisir.';
});

function confirmClosing() {
    closingForm.post(routes.closings, {
        preserveScroll: true,
        onSuccess: () => {
            closing.value = false;
            closingForm.reset('real_balance', 'note');
        },
    });
}

function cancelClosing() {
    closing.value = false;
    closingForm.reset('real_balance', 'note');
    closingForm.clearErrors();
}

/* ------------------------------------------------------------------ */
/* Historique                                                          */
/* ------------------------------------------------------------------ */

function closingSummary(entry) {
    if (balancesHidden.value) {
        return `${entry.account_name} · •••`;
    }

    return `${entry.account_name} · ${signedMoney(-entry.pointed_expenses_cents)} · ${signedMoney(entry.pointed_incomes_cents)}`;
}

function varianceLabel(entry) {
    return `Écart ${signedMoney(entry.variance_cents)}`;
}
</script>

<template>
    <Head title="Bilan" />

    <div class="max-w-[720px]">
        <h1 class="text-xl lg:text-[22px]">Bilan du mois</h1>

        <p v-if="accounts.length === 0" class="mt-3 text-[15px] text-ink-muted">
            Déclarez d'abord un compte :
            <Link :href="routes.accountCreate" class="text-accent-soft">déclarer un compte</Link>.
        </p>

        <template v-else>
            <section class="mt-3 rounded-card bg-surface p-3 lg:mt-3.5 lg:px-4 lg:py-3.5">
                <p class="label-caps">Période de pointage — par compte</p>

                <div class="mt-2 flex flex-wrap gap-[5px] lg:mt-2.5 lg:gap-1.5">
                    <Chip
                        v-for="account in accounts"
                        :key="account.id"
                        :selected="account.id === periodAccountId"
                        @click="periodAccountId = account.id"
                    >
                        {{ account.name }}
                    </Chip>
                </div>

                <div class="mt-2.5 flex items-center gap-[7px] text-[13px] text-ink-muted lg:gap-2 lg:text-[12px]">
                    <span>Du</span>
                    <input
                        v-model="periodForm.period_start_day"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        max="31"
                        class="field w-[46px] px-0 py-1.5 text-center bg-page! lg:w-[52px] lg:py-[7px]"
                        aria-label="Jour de début de la période"
                        @blur="savePeriod"
                    />
                    <span>au</span>
                    <input
                        v-model="periodForm.period_end_day"
                        type="number"
                        inputmode="numeric"
                        min="1"
                        max="31"
                        class="field w-[46px] px-0 py-1.5 text-center bg-page! lg:w-[52px] lg:py-[7px]"
                        aria-label="Jour de fin de la période"
                        @blur="savePeriod"
                    />
                    <span>de chaque mois</span>
                </div>

                <p
                    v-if="periodForm.errors.period_start_day || periodForm.errors.period_end_day"
                    class="mt-1.5 text-[13px] text-accent-soft"
                >
                    {{ periodForm.errors.period_start_day ?? periodForm.errors.period_end_day }}
                </p>
                <!-- La note récapitulative n'apparaît que sur la maquette desktop. -->
                <p v-else-if="periodAccount" class="mt-2 hidden text-[13px] text-ink-muted lg:block">
                    {{ periodNote(periodAccount, periodForm.period_start_day, periodForm.period_end_day) }}
                </p>
            </section>

            <!-- Invisible quand le navigateur ne sait pas faire de Web Push. -->
            <section
                v-if="push.isSupported.value"
                class="mt-2.5 flex items-center gap-3 rounded-card bg-surface p-3 lg:mt-3 lg:px-4 lg:py-3.5"
            >
                <PhIcon name="ph-bell-ringing" class="shrink-0 text-[19px] text-accent" />
                <div class="min-w-0 flex-1">
                    <p class="text-[15px] font-medium lg:text-[13px]">Rappel de pointage</p>
                    <p class="text-[12px] text-ink-muted lg:text-[11px]">
                        <span v-if="push.isDenied.value">
                            Les notifications sont bloquées pour ce site dans les réglages du navigateur.
                        </span>
                        <span v-else-if="push.isSubscribed.value">Activé sur cet appareil.</span>
                        <span v-else>
                            Une notification à la fin de chaque période, pour pointer votre compte.
                        </span>
                    </p>
                </div>
                <button
                    v-if="!push.isDenied.value"
                    type="button"
                    class="btn-outline shrink-0 px-3 py-1.5 text-[13px] lg:text-[12px]"
                    :disabled="push.isWorking.value"
                    @click="push.isSubscribed.value ? push.unsubscribe() : push.subscribe()"
                >
                    {{ push.isSubscribed.value ? 'Désactiver' : 'Activer' }}
                </button>
            </section>

            <div class="mt-2.5 grid grid-cols-2 gap-[7px] lg:mt-3.5 lg:grid-cols-3 lg:gap-2.5">
                <div class="rounded-card bg-surface px-3 py-2.5 lg:p-3">
                    <p class="label-caps text-[11px] lg:text-[10px]">Dépenses pointées</p>
                    <p class="mt-1 text-[17px] font-medium lg:text-[20px]">
                        {{ signedMoney(-props.activity.expenses_cents) }}
                    </p>
                </div>
                <div class="rounded-card bg-surface px-3 py-2.5 lg:p-3">
                    <p class="label-caps text-[11px] lg:text-[10px]">Ajouts pointés</p>
                    <p class="mt-1 text-[17px] font-medium text-accent-soft lg:text-[20px]">
                        {{ signedMoney(props.activity.incomes_cents) }}
                    </p>
                </div>
                <!-- La troisième carte n'existe qu'en desktop ; le mobile la remplace
                     par la ligne de texte posée sous la grille. -->
                <div class="hidden rounded-card bg-surface lg:block lg:p-3">
                    <p class="label-caps text-[12px]">Opérations</p>
                    <p class="mt-1 text-[20px] font-medium">{{ props.activity.pointed_count }} pointées</p>
                    <p class="mt-0.5 text-[12px] text-ink-muted">{{ props.activity.pending_count }} restantes</p>
                </div>
            </div>

            <p class="mt-2 text-[12px] text-ink-muted lg:hidden">
                {{ props.activity.pointed_count }} pointées · {{ props.activity.pending_count }} restantes
            </p>

            <section v-if="props.activity.by_tag.length > 0" class="mt-2.5 rounded-card bg-surface p-3 lg:mt-2.5">
                <p class="label-caps mb-1.5 text-[11px] lg:mb-2 lg:text-[10px]">Dépenses pointées par tag</p>
                <TagSpendingBars :spending="props.activity.by_tag" />
            </section>

            <section v-if="closing" class="mt-3 rounded-card border border-accent bg-surface p-3 lg:mt-3.5 lg:p-4">
                <h2 class="text-[16px] font-medium">Clôturer {{ props.month_label.toLowerCase() }}</h2>
                <p class="mt-0.5 text-[12px] text-ink-muted lg:text-[11px]">
                    Pointez toutes vos opérations du mois, ajoutez les oublis, puis comparez avec votre relevé.
                </p>
                <p class="mt-0.5 text-[12px] text-accent-soft lg:text-[11px]">{{ closingAvailability }}</p>

                <p class="label-caps mt-2.5 mb-1.5 lg:mt-3">Compte à vérifier</p>
                <div class="flex flex-wrap gap-[5px] lg:gap-1.5">
                    <Chip
                        v-for="account in accounts"
                        :key="account.id"
                        :selected="account.id === closingForm.account_id"
                        @click="openClosingFor(account.id)"
                    >
                        {{ account.name }}
                    </Chip>
                </div>

                <div class="mt-2.5 mb-1 flex items-center justify-between lg:mt-3">
                    <p class="label-caps">Pointer les opérations</p>
                    <p class="text-[12px] text-accent-soft lg:text-[11px]">{{ pendingClosingLabel }}</p>
                </div>
                <ul class="max-h-[170px] overflow-y-auto rounded-card bg-page px-2.5 py-0.5 lg:max-h-[200px]">
                    <li
                        v-for="transaction in props.closing_transactions"
                        :key="transaction.id"
                        class="flex items-center gap-2 border-b border-hairline-soft py-[7px] last:border-b-0 lg:gap-2.5"
                        :class="transaction.is_pointed ? 'opacity-55' : ''"
                    >
                        <PointingToggle
                            :pointed="transaction.is_pointed"
                            :url="transaction.pointing_url"
                            :label="transaction.label"
                            class="scale-90"
                        />
                        <span class="min-w-0 flex-1 truncate text-[13px] lg:text-[12px]">{{ transaction.label }}</span>
                        <Amount
                            :cents="transaction.amount_cents"
                            signed
                            class="shrink-0 text-[13px] lg:text-[12px]"
                            :class="transaction.amount_cents > 0 ? 'text-accent-soft' : ''"
                        />
                    </li>
                    <li v-if="props.closing_transactions.length === 0" class="py-2 text-[12px] text-ink-muted">
                        Aucune opération sur cette période.
                    </li>
                </ul>

                <p class="label-caps mt-2.5 mb-1">Ajouter un oubli</p>
                <div class="mb-1.5 flex gap-[5px]">
                    <Chip
                        :selected="additionForm.direction === 'depense'"
                        @click="additionForm.direction = 'depense'"
                    >
                        Dépense
                    </Chip>
                    <Chip :selected="additionForm.direction === 'ajout'" @click="additionForm.direction = 'ajout'">
                        Entrée
                    </Chip>
                </div>
                <form class="flex gap-[5px] lg:gap-1.5" @submit.prevent="submitAddition">
                    <input
                        v-model="additionForm.label"
                        type="text"
                        class="field min-w-0 flex-1 bg-page! py-[7px]"
                        placeholder="Libellé"
                        aria-label="Libellé de l'oubli"
                    />
                    <input
                        v-model="additionForm.amount"
                        type="text"
                        inputmode="decimal"
                        class="field w-[86px] shrink-0 bg-page! py-[7px] lg:w-[96px]"
                        placeholder="0,00 €"
                        aria-label="Montant de l'oubli"
                    />
                    <button
                        type="submit"
                        class="btn-outline shrink-0 px-2.5 text-[13px] lg:px-3 lg:text-[12px]"
                        :disabled="additionForm.processing"
                    >
                        OK
                    </button>
                </form>
                <p v-if="additionForm.errors.amount" class="mt-1.5 text-[13px] text-accent-soft">
                    {{ additionForm.errors.amount }}
                </p>
                <p v-if="additionForm.errors.label" class="mt-1.5 text-[13px] text-accent-soft">
                    {{ additionForm.errors.label }}
                </p>

                <!-- Mobile : ligne label/valeur ; desktop : deux colonnes empilées. -->
                <div class="mt-2.5 lg:mt-3 lg:grid lg:grid-cols-2 lg:gap-3.5">
                    <div>
                        <div class="flex items-center justify-between text-[14px] lg:block">
                            <p class="text-ink-muted lg:label-caps lg:mb-[5px]">Solde selon Pointage</p>
                            <Amount
                                :cents="closingAccount.balance_cents"
                                class="font-medium lg:block lg:py-2 lg:text-[18px]"
                            />
                        </div>
                        <p class="mt-[3px] text-[11px] text-ink-muted lg:mt-0 lg:text-[10px]">
                            Période du {{ closingAccount.period_start_day }} au {{ closingAccount.period_end_day }}.
                        </p>
                    </div>
                    <div class="mt-2.5 lg:mt-0">
                        <p class="label-caps mb-[5px]">Solde réel (relevé banque)</p>
                        <input
                            v-model="closingForm.real_balance"
                            type="text"
                            inputmode="decimal"
                            class="field bg-page! lg:text-[15px]"
                            placeholder="0,00 €"
                        />
                        <p v-if="closingForm.errors.real_balance" class="mt-1.5 text-[13px] text-accent-soft">
                            {{ closingForm.errors.real_balance }}
                        </p>
                    </div>
                </div>

                <div v-if="varianceCents !== null" class="mt-2.5 rounded-card bg-page p-2.5 lg:mt-3 lg:p-3">
                    <div class="flex justify-between text-[14px]">
                        <span class="text-ink-muted">Écart</span>
                        <span class="font-medium text-accent-soft">{{ signedMoney(varianceCents) }}</span>
                    </div>
                    <p class="mt-1 text-[12px] text-ink-muted lg:text-[11px]">{{ varianceMessage }}</p>

                    <!-- Pour un compte de marché (PEA, crypto), l'écart est la dérive
                         des cours : un clic l'absorbe en opération de réévaluation. -->
                    <button
                        v-if="varianceCents !== 0"
                        type="button"
                        class="mt-2 flex cursor-pointer items-center gap-1.5 text-[13px] text-accent-soft transition-colors hover:text-ink lg:text-[12px]"
                        :disabled="revaluationForm.processing"
                        @click="recordVarianceAsRevaluation"
                    >
                        <PhIcon name="ph-scales" class="text-[14px]" />
                        Enregistrer l'écart comme réévaluation de marché
                    </button>

                    <p class="label-caps mt-2.5 mb-1">Commentaire sur l'écart</p>
                    <input
                        v-model="closingForm.note"
                        type="text"
                        class="field bg-surface! lg:text-[13px]"
                        :placeholder="notePlaceholder"
                    />
                    <p class="mt-1 text-[11px] text-ink-muted lg:text-[10px]">
                        Décrivez les dépenses manquantes ou les oublis — visible dans
                        l'historique<span class="hidden lg:inline"> du bilan</span>.
                    </p>
                    <p v-if="closingForm.errors.note" class="mt-1.5 text-[13px] text-accent-soft">
                        {{ closingForm.errors.note }}
                    </p>
                </div>

                <p class="mt-2.5 text-[11px] text-ink-muted lg:text-[10px]">
                    Après validation, la prochaine période démarrera le {{ nextPeriodStartDay }} (lendemain de la
                    fin du mois clôturé).
                </p>

                <div class="mt-2 flex gap-1.5 lg:mt-2.5 lg:gap-2">
                    <button
                        type="button"
                        class="btn-outline flex-1 py-2.5 text-[14px] lg:text-[13px]"
                        :disabled="closingForm.processing"
                        @click="confirmClosing"
                    >
                        <span class="lg:hidden">Confirmer</span>
                        <span class="hidden lg:inline">Confirmer la clôture</span>
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-card border border-hairline px-3.5 py-2.5 text-[14px] text-ink-muted transition-colors hover:border-outline-muted lg:px-4 lg:text-[13px]"
                        @click="cancelClosing"
                    >
                        Annuler
                    </button>
                </div>
            </section>

            <button
                v-else
                type="button"
                class="btn-outline mt-3 w-full py-2.5 text-[15px] lg:mt-3.5"
                @click="openClosingFor(closingForm.account_id)"
            >
                Clôturer le mois
            </button>

            <template v-if="props.closings.length > 0">
                <p class="label-caps mt-4 mb-1 lg:mt-[22px] lg:mb-2">Mois clôturés</p>
                <ul>
                    <li
                        v-for="entry in props.closings"
                        :key="entry.id"
                        class="border-b border-hairline-soft py-2.5 lg:py-2.5"
                    >
                        <div class="flex items-center gap-2.5 lg:gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-[15px] font-medium">{{ entry.month_label }}</p>
                                <p class="mt-0.5 text-[12px] text-ink-muted lg:text-[11px]">{{ closingSummary(entry) }}</p>
                            </div>
                            <span
                                class="shrink-0 text-[13px]"
                                :class="entry.variance_cents === 0 ? 'text-accent-soft' : 'text-ink-muted'"
                            >
                                {{ varianceLabel(entry) }}
                            </span>
                        </div>
                        <p v-if="entry.note" class="mt-1.5 flex items-start gap-1.5 text-[12px] text-ink-muted lg:text-[11px]">
                            <PhIcon name="ph-note" class="mt-px shrink-0 text-[14px] text-accent" />
                            <span>{{ entry.note }}</span>
                        </p>
                    </li>
                </ul>
            </template>
        </template>
    </div>
</template>
