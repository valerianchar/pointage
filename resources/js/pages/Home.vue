<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AccountCard from '../components/AccountCard.vue';
import Amount from '../components/Amount.vue';
import Chip from '../components/Chip.vue';
import Gauge from '../components/Gauge.vue';
import PhIcon from '../components/PhIcon.vue';
import PrivacyToggle from '../components/PrivacyToggle.vue';
import Sparkline from '../components/Sparkline.vue';
import TagSpendingBars from '../components/TagSpendingBars.vue';
import WidgetCard from '../components/WidgetCard.vue';
import { useBugReport } from '../composables/useBugReport';
import { useMoney } from '../composables/useMoney';
import { shareOf } from '../money';
import { routes } from '../routes';
import { WIDGET } from '../widgets';

const props = defineProps({
    month_label: { type: String, required: true },
    income_cents: { type: Number, required: true },
    expense_cents: { type: Number, required: true },
    balance_history: { type: Array, required: true },
    /** @type {{key: string, label: string, title: string, span: number, enabled: boolean}[]} */
    widgets: { type: Array, required: true },
    /* Chaque widget optionnel n'est envoyé que s'il est affiché. */
    pointing: { type: Object, default: null },
    daily_expense: { type: Object, default: null },
    recurring_charge: { type: Object, default: null },
    credit_totals: { type: Object, default: null },
    tag_spending: { type: Array, default: () => [] },
    top_expenses: { type: Array, default: () => [] },
    crypto_totals: { type: Object, default: null },
    pea_totals: { type: Object, default: null },
    /** @type {{id: number, account_name: string, inviter_name: string, accept_url: string, decline_url: string}[]} */
    invitations: { type: Array, default: () => [] },
});

const page = usePage();
const { money, signedMoney, plainMoney } = useMoney();

const isCustomizing = ref(false);

const accounts = computed(() => page.props.accounts);
const totalBalanceCents = computed(() =>
    accounts.value.reduce((total, account) => total + account.balance_cents, 0),
);

const netCents = computed(() => props.income_cents - props.expense_cents);

/** Part des ajouts du mois qui n'a pas été dépensée. */
const savingsRatePercent = computed(() =>
    props.income_cents > 0 ? Math.max(0, Math.round((netCents.value / props.income_cents) * 100)) : null,
);

const pointedPercent = computed(() =>
    props.pointing === null || props.pointing.total_count === 0
        ? 100
        : shareOf(props.pointing.total_count - props.pointing.pending_count, props.pointing.total_count),
);

const accountShares = computed(() =>
    accounts.value.map((account) => ({
        ...account,
        percent: shareOf(account.balance_cents, totalBalanceCents.value),
    })),
);

const widgetsByKey = computed(() => Object.fromEntries(props.widgets.map((widget) => [widget.key, widget])));

/* Variation du jour d'un portefeuille : accent en hausse, encre en baisse, discret à l'arrêt. */
const dayChangeClass = (totals) =>
    totals.day_change_cents === 0 ? 'text-ink-muted' : totals.day_change_cents > 0 ? 'text-accent-soft' : 'text-ink';

const shows = (key) => widgetsByKey.value[key]?.enabled ?? false;
const titleOf = (key) => widgetsByKey.value[key]?.title ?? '';
const spanOf = (key) => widgetsByKey.value[key]?.span ?? 1;

function toggleWidget(key) {
    const enabledKeys = props.widgets.filter((widget) => widget.enabled).map((widget) => widget.key);

    router.patch(
        routes.widgets,
        {
            widgets: enabledKeys.includes(key)
                ? enabledKeys.filter((enabledKey) => enabledKey !== key)
                : [...enabledKeys, key],
        },
        { preserveScroll: true, preserveState: true },
    );
}

function lockApplication() {
    router.post(routes.lock);
}

const { open: openBugReport } = useBugReport();
</script>

<template>
    <Head title="Mes comptes" />

    <div class="max-w-[980px] lg:max-w-[1080px]">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="label-caps">{{ props.month_label }}</p>
                <h1 class="mt-0.5 text-2xl">
                    <span class="lg:hidden">Mes comptes</span>
                    <span class="hidden lg:inline">Vue d'ensemble</span>
                </h1>
            </div>

            <div class="mb-1.5 flex items-center gap-3 text-[18px] lg:hidden">
                <PrivacyToggle />
                <button
                    type="button"
                    class="cursor-pointer text-ink-muted transition-colors hover:text-accent-soft"
                    title="Personnaliser les widgets"
                    aria-label="Personnaliser les widgets"
                    :aria-expanded="isCustomizing"
                    @click="isCustomizing = !isCustomizing"
                >
                    <PhIcon name="ph-squares-four" />
                </button>
                <button
                    type="button"
                    class="cursor-pointer text-ink-muted transition-colors hover:text-accent-soft"
                    title="Signaler un bug"
                    aria-label="Signaler un bug"
                    @click="openBugReport"
                >
                    <PhIcon name="ph-bug" />
                </button>
                <button
                    type="button"
                    class="cursor-pointer text-ink-muted transition-colors hover:text-accent-soft"
                    title="Verrouiller"
                    aria-label="Verrouiller"
                    @click="lockApplication"
                >
                    <PhIcon name="ph-lock" />
                </button>
            </div>

            <button
                type="button"
                class="hidden cursor-pointer items-center gap-1.5 rounded-card border border-hairline px-3 py-1.5 text-[12px] text-ink-muted transition-colors hover:border-accent hover:text-accent-soft lg:flex"
                :aria-expanded="isCustomizing"
                @click="isCustomizing = !isCustomizing"
            >
                <PhIcon name="ph-squares-four" class="text-[14px]" />
                Personnaliser
            </button>
        </div>

        <!-- Invitations à des comptes joints : elles attendent une réponse ici. -->
        <div
            v-for="invitation in props.invitations"
            :key="invitation.id"
            class="mt-3 flex flex-wrap items-center gap-2.5 rounded-card border border-accent bg-surface p-3"
        >
            <PhIcon name="ph-users" class="text-[18px] text-accent-soft" />
            <p class="min-w-0 flex-1 text-[13px]">
                <span class="font-medium">{{ invitation.inviter_name }}</span> vous invite à rejoindre
                « {{ invitation.account_name }} »
            </p>
            <div class="flex shrink-0 gap-2">
                <button
                    type="button"
                    class="btn-outline px-3 py-1.5 text-[13px] lg:text-[12px]"
                    @click="router.post(invitation.accept_url)"
                >
                    Accepter
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-card border border-hairline px-3 py-1.5 text-[13px] text-ink-muted transition-colors hover:border-outline-muted lg:text-[12px]"
                    @click="router.delete(invitation.decline_url)"
                >
                    Refuser
                </button>
            </div>
        </div>

        <!-- Sélection des widgets : les mêmes partout — grille en desktop, pile en mobile. -->
        <div v-if="isCustomizing" class="mt-3 rounded-card bg-surface p-3">
            <p class="label-caps mb-2">Widgets affichés</p>
            <div class="flex flex-wrap gap-1.5">
                <Chip
                    v-for="widget in props.widgets"
                    :key="widget.key"
                    :selected="widget.enabled"
                    @click="toggleWidget(widget.key)"
                >
                    {{ widget.label }}
                </Chip>
            </div>
        </div>

        <!-- Mobile : le patrimoine reste en grand au-dessus des widgets. -->
        <div class="lg:hidden">
            <p class="mt-3 text-[30px] font-medium"><Amount :cents="totalBalanceCents" /></p>
            <p class="text-[13px] text-ink-muted">Patrimoine total</p>
        </div>

        <!-- Grille de quatre colonnes en desktop — chaque widget en occupant une ou
             deux —, colonne unique en mobile. -->
        <div class="mt-3.5 grid grid-cols-1 gap-2.5 lg:grid-cols-4">
            <!-- En mobile, le patrimoine trône déjà au-dessus de la pile : sa carte
                 ne s'affiche qu'en desktop. -->
            <WidgetCard
                v-if="shows(WIDGET.wealth)"
                :title="titleOf(WIDGET.wealth)"
                :span="spanOf(WIDGET.wealth)"
                class="max-lg:hidden"
            >
                <p class="mt-[5px] text-xl font-medium"><Amount :cents="totalBalanceCents" /></p>
                <p class="mt-0.5 text-[10px] text-ink-muted">
                    {{ accounts.length }} {{ accounts.length > 1 ? 'comptes' : 'compte' }}
                </p>
            </WidgetCard>

            <WidgetCard
                v-if="shows(WIDGET.monthlyFlow)"
                :title="titleOf(WIDGET.monthlyFlow)"
                :span="spanOf(WIDGET.monthlyFlow)"
            >
                <p class="mt-[5px] text-xl font-medium" :class="netCents >= 0 ? 'text-accent-soft' : 'text-ink'">
                    {{ signedMoney(netCents) }}
                </p>
                <p class="mt-0.5 text-[10px] text-ink-muted">
                    {{ signedMoney(props.income_cents) }} · {{ signedMoney(-props.expense_cents) }}
                </p>
            </WidgetCard>

            <WidgetCard
                v-if="shows(WIDGET.savingsRate)"
                :title="titleOf(WIDGET.savingsRate)"
                :span="spanOf(WIDGET.savingsRate)"
            >
                <p class="mt-[5px] text-xl font-medium">
                    {{ savingsRatePercent === null ? '—' : `${savingsRatePercent} %` }}
                </p>
                <Gauge :percent="savingsRatePercent ?? 0" label="Taux d'épargne du mois" class="mt-2" />
            </WidgetCard>

            <WidgetCard
                v-if="shows(WIDGET.pending) && props.pointing"
                :title="titleOf(WIDGET.pending)"
                :span="spanOf(WIDGET.pending)"
            >
                <p class="mt-[5px] text-xl font-medium text-accent-soft">{{ props.pointing.pending_count }}</p>
                <p class="mt-0.5 text-[10px] text-ink-muted">
                    sur {{ props.pointing.total_count }}
                    {{ props.pointing.total_count > 1 ? 'opérations' : 'opération' }} ce mois-ci
                </p>
                <Gauge :percent="pointedPercent" label="Opérations pointées ce mois-ci" class="mt-1.5" />
            </WidgetCard>

            <WidgetCard
                v-if="shows(WIDGET.dailyAverage) && props.daily_expense"
                :title="titleOf(WIDGET.dailyAverage)"
                :span="spanOf(WIDGET.dailyAverage)"
            >
                <p class="mt-[5px] text-xl font-medium">{{ money(props.daily_expense.average_cents) }}</p>
                <p class="mt-0.5 text-[10px] text-ink-muted">
                    Projection fin de mois : {{ signedMoney(-props.daily_expense.projected_cents) }}
                </p>
            </WidgetCard>

            <WidgetCard
                v-if="shows(WIDGET.recurringCharge) && props.recurring_charge"
                :title="titleOf(WIDGET.recurringCharge)"
                :span="spanOf(WIDGET.recurringCharge)"
            >
                <p class="mt-[5px] text-xl font-medium">
                    {{ signedMoney(-props.recurring_charge.charge_cents) }}
                </p>
                <p class="mt-0.5 text-[10px] text-ink-muted">
                    {{ props.recurring_charge.pending_count }} / {{ props.recurring_charge.total_count }}
                    à pointer ce mois-ci
                </p>
            </WidgetCard>

            <WidgetCard
                v-if="shows(WIDGET.credits) && props.credit_totals"
                :title="titleOf(WIDGET.credits)"
                :span="spanOf(WIDGET.credits)"
            >
                <p class="mt-[5px] text-xl font-medium"><Amount :cents="props.credit_totals.remaining_cents" /></p>
                <p class="mt-0.5 text-[10px] text-ink-muted">
                    {{ plainMoney(props.credit_totals.monthly_cents) }} / mois ·
                    {{ props.credit_totals.count }}
                    {{ props.credit_totals.count > 1 ? 'crédits en cours' : 'crédit en cours' }}
                </p>
            </WidgetCard>

            <!-- Comptes de marché : valeur du portefeuille et variation du jour,
                 lue dans la réévaluation datée d'aujourd'hui. -->
            <WidgetCard
                v-if="shows(WIDGET.crypto) && props.crypto_totals"
                :title="titleOf(WIDGET.crypto)"
                :span="spanOf(WIDGET.crypto)"
            >
                <p class="mt-[5px] text-xl font-medium"><Amount :cents="props.crypto_totals.value_cents" /></p>
                <p class="mt-0.5 text-[10px]" :class="dayChangeClass(props.crypto_totals)">
                    <template v-if="props.crypto_totals.day_change_cents !== 0">
                        {{ signedMoney(props.crypto_totals.day_change_cents) }} aujourd'hui
                    </template>
                    <template v-else>au dernier cours connu</template>
                </p>
            </WidgetCard>

            <WidgetCard
                v-if="shows(WIDGET.stockPlan) && props.pea_totals"
                :title="titleOf(WIDGET.stockPlan)"
                :span="spanOf(WIDGET.stockPlan)"
            >
                <p class="mt-[5px] text-xl font-medium"><Amount :cents="props.pea_totals.value_cents" /></p>
                <p class="mt-0.5 text-[10px]" :class="dayChangeClass(props.pea_totals)">
                    <template v-if="props.pea_totals.day_change_cents !== 0">
                        {{ signedMoney(props.pea_totals.day_change_cents) }} aujourd'hui
                    </template>
                    <template v-else>au dernier cours connu</template>
                </p>
            </WidgetCard>

            <WidgetCard
                v-if="shows(WIDGET.balanceHistory)"
                :title="titleOf(WIDGET.balanceHistory)"
                :span="spanOf(WIDGET.balanceHistory)"
            >
                <Sparkline :points="props.balance_history" class="mt-2 h-[42px] gap-[5px]" />
                <p class="mt-1.5 text-[10px] text-ink-muted">8 dernières semaines</p>
            </WidgetCard>

            <WidgetCard
                v-if="shows(WIDGET.tagSpending)"
                :title="titleOf(WIDGET.tagSpending)"
                :span="spanOf(WIDGET.tagSpending)"
            >
                <TagSpendingBars v-if="props.tag_spending.length > 0" :spending="props.tag_spending" class="mt-2" />
                <p v-else class="mt-2 text-[11px] text-ink-muted">Aucune dépense ce mois-ci.</p>
            </WidgetCard>

            <WidgetCard
                v-if="shows(WIDGET.topExpenses)"
                :title="titleOf(WIDGET.topExpenses)"
                :span="spanOf(WIDGET.topExpenses)"
            >
                <div v-if="props.top_expenses.length > 0" class="mt-1.5">
                    <div
                        v-for="expense in props.top_expenses"
                        :key="`${expense.label}-${expense.amount_cents}`"
                        class="flex items-center gap-2.5 py-[5px]"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[12px]">{{ expense.label }}</p>
                            <p class="truncate text-[10px] text-ink-muted">
                                {{ expense.account_name }} · {{ expense.tag ?? '—' }}
                            </p>
                        </div>
                        <Amount :cents="expense.amount_cents" signed class="shrink-0 text-[12px]" />
                    </div>
                </div>
                <p v-else class="mt-2 text-[11px] text-ink-muted">Aucune dépense ce mois-ci.</p>
            </WidgetCard>

            <WidgetCard
                v-if="shows(WIDGET.accountShare)"
                :title="titleOf(WIDGET.accountShare)"
                :span="spanOf(WIDGET.accountShare)"
            >
                <div class="mt-2 flex flex-col gap-[5px]">
                    <Link
                        v-for="account in accountShares"
                        :key="account.id"
                        :href="account.url"
                        class="flex items-center gap-2 rounded-xs transition-colors hover:bg-surface-hover"
                    >
                        <span class="w-[118px] shrink-0 truncate text-[11px]">{{ account.name }}</span>
                        <Gauge :percent="account.percent" :label="`Part de ${account.name}`" class="flex-1" />
                        <span class="w-[34px] shrink-0 text-right text-[10px] text-ink-muted">
                            {{ account.percent }} %
                        </span>
                        <Amount :cents="account.balance_cents" class="w-20 shrink-0 text-right text-[11px]" />
                    </Link>
                </div>
            </WidgetCard>
        </div>

        <!-- L'accueil mobile garde sa liste de comptes ; en desktop, la sidebar et le
             widget de répartition y donnent accès. -->
        <div class="lg:hidden">
            <p class="label-caps mt-3.5">Mes comptes</p>

            <div v-if="accounts.length === 0" class="mt-2 rounded-card bg-surface p-4 text-[15px] text-ink-muted">
                Aucun compte déclaré pour l'instant. Commencez par en déclarer un pour saisir et pointer vos
                opérations.
            </div>

            <div class="mt-2 flex flex-col gap-[7px]">
                <AccountCard v-for="account in accounts" :key="account.id" :account="account" />
            </div>

            <Link :href="routes.accountCreate" class="btn-outline mt-3 block w-full py-2.5 text-center text-[14px]">
                + Déclarer un compte
            </Link>
        </div>
    </div>
</template>
