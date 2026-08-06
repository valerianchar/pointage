<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Amount from '../../components/Amount.vue';
import Chip from '../../components/Chip.vue';
import CreditProgress from '../../components/CreditProgress.vue';
import FormField from '../../components/FormField.vue';
import PhIcon from '../../components/PhIcon.vue';
import { useMoney } from '../../composables/useMoney';
import { routes } from '../../routes';

const props = defineProps({
    credits: { type: Array, required: true },
});

const page = usePage();
const { plainMoney } = useMoney();

const accounts = computed(() => page.props.accounts);

const form = useForm({
    name: '',
    account_id: accounts.value[0]?.id ?? null,
    borrowed: '',
    remaining: '',
    monthly: '',
    term_months: '',
    payment_day: '',
});

function monthlyLabel(credit) {
    return `${plainMoney(credit.monthly_cents)} / mois`;
}

function debitDaySuffix(credit) {
    return credit.payment_day === null ? '' : ` · prélevé le ${credit.payment_day}`;
}

/**
 * Durée déclarée, mensualités restantes au rythme actuel, prochaine échéance.
 * Les crédits déclarés avant l'arrivée de ces champs n'affichent que ce qu'ils ont.
 */
function scheduleLabel(credit) {
    return [
        credit.term_label ? `sur ${credit.term_label}` : null,
        credit.next_payment_label ? `prochaine le ${credit.next_payment_label}` : null,
        credit.remaining_instalments === null ? null : `≈ ${credit.remaining_instalments} mensualités restantes`,
    ]
        .filter(Boolean)
        .join(' · ');
}

function declareCredit() {
    form.post(routes.credits, {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'borrowed', 'remaining', 'monthly', 'term_months', 'payment_day'),
    });
}

function deleteCredit(credit) {
    router.delete(credit.url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Crédits" />

    <div class="max-w-[720px]">
        <h1 class="text-xl lg:text-[22px]">Crédits</h1>
        <p class="mt-1 text-[13px] text-ink-muted lg:mt-1.5 lg:text-[12px]">
            Déclarez vos crédits par compte et suivez le capital restant.
        </p>

        <p v-if="accounts.length === 0" class="mt-3 text-[15px] text-ink-muted">
            Déclarez d'abord un compte :
            <Link :href="routes.accountCreate" class="text-accent-soft">déclarer un compte</Link>.
        </p>

        <template v-else>
            <p v-if="props.credits.length === 0" class="mt-4 text-[15px] text-ink-muted">
                Aucun crédit déclaré. Renseignez-en un ci-dessous pour suivre son remboursement.
            </p>

            <div class="mt-3 flex flex-col gap-[7px] lg:mt-3.5 lg:gap-2">
                <div
                    v-for="credit in props.credits"
                    :key="credit.id"
                    class="rounded-card bg-surface px-3 py-2.5 lg:px-4 lg:py-3"
                >
                    <div class="flex items-center gap-2.5 lg:gap-3">
                        <!-- L'icône n'apparaît qu'en desktop, comme sur la maquette. -->
                        <PhIcon name="ph-hand-coins" class="hidden text-[18px] text-accent lg:block" />
                        <p class="min-w-0 flex-1 truncate text-[15px] font-medium">{{ credit.name }}</p>
                        <div class="shrink-0 text-right">
                            <Amount :cents="credit.remaining_cents" class="block text-[15px] lg:text-[14px]" />
                            <span class="block text-[11px] text-ink-muted lg:text-[10px]">
                                <span class="lg:hidden">restant dû</span>
                                <span class="hidden lg:inline">capital restant</span>
                            </span>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 cursor-pointer text-[15px] text-ink-muted transition-colors hover:text-accent-soft lg:text-[14px]"
                            :aria-label="`Supprimer le crédit ${credit.name}`"
                            @click="deleteCredit(credit)"
                        >
                            <PhIcon name="ph-x" />
                        </button>
                    </div>

                    <!-- Compte, échéance et durée prennent toute la largeur de la carte :
                         serrées à côté du nom, elles se replieraient sur mobile. -->
                    <p class="mt-1 text-[12px] text-ink-muted lg:text-[11px]">
                        {{ credit.account_name }} · {{ monthlyLabel(credit) }}{{ debitDaySuffix(credit) }}
                    </p>
                    <p v-if="scheduleLabel(credit)" class="text-[12px] text-ink-faint">
                        {{ scheduleLabel(credit) }}
                    </p>

                    <CreditProgress :credit="credit" class="mt-2 lg:mt-2.5" />
                </div>
            </div>

            <!-- Le formulaire est posé à même le fond sur mobile, en carte sur desktop. -->
            <form class="mt-4 lg:mt-[18px] lg:rounded-card lg:bg-surface lg:p-4" @submit.prevent="declareCredit">
                <p class="label-caps mb-1.5 lg:mb-3">Déclarer un crédit</p>

                <div class="grid grid-cols-2 gap-1.5 lg:gap-3.5">
                    <FormField label="Nom" :error="form.errors.name" label-desktop-only class="col-span-2 lg:col-span-1 lg:order-1">
                        <input
                            v-model="form.name"
                            type="text"
                            class="field lg:bg-page!"
                            placeholder="Nom — ex. Prêt auto"
                        />
                    </FormField>

                    <FormField
                        label="Mensualité"
                        :error="form.errors.monthly"
                        label-desktop-only
                        class="order-6 col-span-2 lg:order-2 lg:col-span-1"
                    >
                        <input
                            v-model="form.monthly"
                            type="text"
                            inputmode="decimal"
                            class="field lg:bg-page!"
                            placeholder="Mensualité — 0,00 €"
                        />
                    </FormField>

                    <FormField
                        label="Capital emprunté"
                        :error="form.errors.borrowed"
                        label-desktop-only
                        class="order-2 lg:order-3"
                    >
                        <input
                            v-model="form.borrowed"
                            type="text"
                            inputmode="decimal"
                            class="field lg:bg-page!"
                            placeholder="Capital emprunté"
                        />
                    </FormField>

                    <FormField
                        label="Capital restant"
                        :error="form.errors.remaining"
                        label-desktop-only
                        class="order-3 lg:order-4"
                    >
                        <input
                            v-model="form.remaining"
                            type="text"
                            inputmode="decimal"
                            class="field lg:bg-page!"
                            placeholder="Capital restant"
                        />
                    </FormField>

                    <FormField
                        label="Durée (mois)"
                        :error="form.errors.term_months"
                        label-desktop-only
                        class="order-4 lg:order-5"
                    >
                        <input
                            v-model="form.term_months"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            max="600"
                            class="field lg:bg-page!"
                            placeholder="Durée — ex. 60 mois"
                        />
                    </FormField>

                    <FormField
                        label="Jour de prélèvement"
                        :error="form.errors.payment_day"
                        label-desktop-only
                        class="order-5 lg:order-6"
                    >
                        <input
                            v-model="form.payment_day"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            max="31"
                            class="field lg:bg-page!"
                            placeholder="Prélevé le — ex. 5"
                        />
                    </FormField>
                </div>

                <p class="label-caps mt-3 mb-1.5 lg:mt-3.5">Compte</p>
                <div class="flex flex-wrap gap-[5px] lg:gap-1.5">
                    <Chip
                        v-for="account in accounts"
                        :key="account.id"
                        :selected="account.id === form.account_id"
                        @click="form.account_id = account.id"
                    >
                        {{ account.name }}
                    </Chip>
                </div>
                <p v-if="form.errors.account_id" class="mt-1.5 text-[13px] text-accent-soft">
                    {{ form.errors.account_id }}
                </p>

                <button
                    type="submit"
                    class="btn-outline mt-3.5 w-full py-2.5 text-[15px] lg:mt-4"
                    :disabled="form.processing"
                >
                    Déclarer le crédit
                </button>
            </form>
        </template>
    </div>
</template>
