<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AccountCard from '../components/AccountCard.vue';
import Amount from '../components/Amount.vue';
import Gauge from '../components/Gauge.vue';
import PhIcon from '../components/PhIcon.vue';
import PrivacyToggle from '../components/PrivacyToggle.vue';
import Sparkline from '../components/Sparkline.vue';
import { shareOf } from '../money';
import { routes } from '../routes';

const props = defineProps({
    month_label: { type: String, required: true },
    income_cents: { type: Number, required: true },
    expense_cents: { type: Number, required: true },
    balance_history: { type: Array, required: true },
});

const page = usePage();

const accounts = computed(() => page.props.accounts);
const totalBalanceCents = computed(() =>
    accounts.value.reduce((total, account) => total + account.balance_cents, 0),
);

// Les deux barres se comparent entre elles : la plus haute occupe toute la piste.
const busiestFlowCents = computed(() => Math.max(props.income_cents, props.expense_cents, 1));
const incomePercent = computed(() => shareOf(props.income_cents, busiestFlowCents.value));
const expensePercent = computed(() => shareOf(props.expense_cents, busiestFlowCents.value));

function lockApplication() {
    router.post(routes.lock);
}
</script>

<template>
    <Head title="Mes comptes" />

    <div class="max-w-[980px]">
        <div class="flex items-start justify-between">
            <div>
                <p class="label-caps">{{ props.month_label }}</p>
                <h1 class="mt-0.5 text-2xl lg:text-[26px]">
                    <span class="lg:hidden">Mes comptes</span>
                    <span class="hidden lg:inline">Vue d'ensemble</span>
                </h1>
            </div>
            <div class="mt-1.5 flex items-center gap-3 text-[18px] lg:hidden">
                <PrivacyToggle />
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
        </div>

        <!-- Mobile : patrimoine en grand, puis une carte qui rassemble flux et évolution. -->
        <div class="lg:hidden">
            <p class="mt-3 text-[30px] font-medium"><Amount :cents="totalBalanceCents" /></p>
            <p class="text-[11px] text-ink-muted">Patrimoine total</p>

            <div class="mt-3.5 rounded-card bg-surface p-3">
                <div class="flex justify-between text-[11px]">
                    <span class="text-ink-muted">Ajouts du mois</span>
                    <Amount :cents="props.income_cents" signed class="text-accent-soft" />
                </div>
                <Gauge :percent="incomePercent" label="Ajouts du mois" class="my-[5px]" />

                <div class="mt-2.5 flex justify-between text-[11px]">
                    <span class="text-ink-muted">Dépenses du mois</span>
                    <Amount :cents="-props.expense_cents" signed />
                </div>
                <Gauge
                    :percent="expensePercent"
                    label="Dépenses du mois"
                    bar-class="bg-gauge-neutral"
                    class="mt-[5px] mb-3"
                />

                <Sparkline :points="props.balance_history" />
                <p class="mt-1.5 text-[10px] text-ink-muted">Évolution du solde — 8 dernières semaines</p>
            </div>
        </div>

        <!-- Desktop : trois cartes de statistiques, puis le graphe sur toute la largeur. -->
        <div class="hidden lg:block">
            <div class="mt-[22px] grid grid-cols-3 gap-3.5">
                <div class="rounded-card bg-surface p-4">
                    <p class="text-[11px] text-ink-muted">Patrimoine total</p>
                    <p class="mt-1.5 text-2xl font-medium"><Amount :cents="totalBalanceCents" /></p>
                </div>
                <div class="rounded-card bg-surface p-4">
                    <p class="text-[11px] text-ink-muted">Ajouts du mois</p>
                    <p class="mt-1.5 text-2xl font-medium text-accent-soft">
                        <Amount :cents="props.income_cents" signed />
                    </p>
                    <Gauge :percent="incomePercent" label="Ajouts du mois" class="mt-2.5" />
                </div>
                <div class="rounded-card bg-surface p-4">
                    <p class="text-[11px] text-ink-muted">Dépenses du mois</p>
                    <p class="mt-1.5 text-2xl font-medium"><Amount :cents="-props.expense_cents" signed /></p>
                    <Gauge
                        :percent="expensePercent"
                        label="Dépenses du mois"
                        bar-class="bg-gauge-neutral"
                        class="mt-2.5"
                    />
                </div>
            </div>

            <div class="mt-3.5 rounded-card bg-surface p-4">
                <Sparkline :points="props.balance_history" />
                <p class="mt-2 text-[11px] text-ink-muted">Évolution du solde — 8 dernières semaines</p>
            </div>
        </div>

        <p class="label-caps mt-3.5 lg:mt-[26px] lg:mb-2.5">Mes comptes</p>

        <div v-if="accounts.length === 0" class="mt-2 rounded-card bg-surface p-4 text-[13px] text-ink-muted">
            Aucun compte déclaré pour l'instant. Commencez par en déclarer un pour saisir et pointer vos opérations.
        </div>

        <div class="mt-2 flex flex-col gap-[7px] lg:mt-0 lg:grid lg:grid-cols-2 lg:gap-3.5">
            <AccountCard v-for="account in accounts" :key="account.id" :account="account" />
        </div>

        <Link
            :href="routes.accountCreate"
            class="btn-outline mt-3 block w-full py-2.5 text-center text-[12px] lg:hidden"
        >
            + Déclarer un compte
        </Link>
    </div>
</template>
