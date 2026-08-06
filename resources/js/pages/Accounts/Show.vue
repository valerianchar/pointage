<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Amount from '../../components/Amount.vue';
import CreditSummaryCard from '../../components/CreditSummaryCard.vue';
import Gauge from '../../components/Gauge.vue';
import PhIcon from '../../components/PhIcon.vue';
import TagSpendingBars from '../../components/TagSpendingBars.vue';
import TransactionList from '../../components/TransactionList.vue';
import TransactionTable from '../../components/TransactionTable.vue';
import { shareOf } from '../../money';
import { routes } from '../../routes';

const props = defineProps({
    account: { type: Object, required: true },
    month_label: { type: String, required: true },
    transactions: { type: Array, required: true },
    tag_spending: { type: Array, required: true },
    credits: { type: Array, required: true },
    add_url: { type: String, required: true },
});

const pendingCount = computed(() => props.transactions.filter((transaction) => !transaction.is_pointed).length);
const pointedCount = computed(() => props.transactions.length - pendingCount.value);

const pointingPercent = computed(() =>
    props.transactions.length === 0 ? 100 : shareOf(pointedCount.value, props.transactions.length),
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

        <div class="mt-2.5 grid gap-3.5 lg:mt-5 lg:grid-cols-2">
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
            </div>

            <!-- La maquette pose ces barres à même le fond sur mobile, en carte sur desktop. -->
            <div v-if="props.tag_spending.length > 0" class="lg:rounded-card lg:bg-surface lg:p-4">
                <p class="label-caps mb-1.5 lg:mb-2 lg:text-[11px] lg:normal-case lg:tracking-normal">
                    Dépenses par tag
                </p>
                <TagSpendingBars :spending="props.tag_spending" />
            </div>
        </div>

        <template v-if="props.credits.length > 0">
            <p class="label-caps mt-4 mb-1.5 lg:mt-[22px] lg:mb-2">Crédits sur ce compte</p>
            <div class="flex flex-col gap-[7px] lg:grid lg:grid-cols-2 lg:gap-3.5">
                <CreditSummaryCard v-for="credit in props.credits" :key="credit.id" :credit="credit" />
            </div>
        </template>

        <div class="mt-4 flex items-baseline justify-between lg:mt-[26px]">
            <p class="label-caps">Opérations — {{ props.month_label.toLowerCase() }}</p>
            <Link :href="props.add_url" class="text-[13px] text-accent-soft lg:text-[12px]">+ Ajouter</Link>
        </div>

        <p v-if="props.transactions.length === 0" class="mt-3 text-[15px] text-ink-muted">
            Aucune opération ce mois-ci.
        </p>

        <TransactionList :transactions="props.transactions" class="mt-0.5" />
        <TransactionTable :transactions="props.transactions" class="mt-1" />
    </div>
</template>
