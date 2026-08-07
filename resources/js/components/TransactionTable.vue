<script setup>
import { Link } from '@inertiajs/vue3';
import Amount from './Amount.vue';
import PhIcon from './PhIcon.vue';
import PointingToggle from './PointingToggle.vue';
import TagPill from './TagPill.vue';

const props = defineProps({
    transactions: { type: Array, required: true },
});
</script>

<template>
    <div class="hidden overflow-x-auto lg:block">
        <!-- table-fixed : les largeurs de colonnes de la maquette sont respectées
             au lieu d'être élargies par le contenu des en-têtes. -->
        <table class="w-full min-w-[640px] table-fixed border-collapse text-left">
            <thead>
                <tr class="border-b border-hairline text-[10px] tracking-[0.06em] text-ink-faint uppercase">
                    <th scope="col" class="w-14 py-2 font-normal">Pointé</th>
                    <th scope="col" class="py-2 font-normal">Libellé</th>
                    <th scope="col" class="w-[130px] py-2 font-normal">Tag</th>
                    <th scope="col" class="w-[90px] py-2 font-normal">Date</th>
                    <th scope="col" class="w-[110px] py-2 text-right font-normal">Montant</th>
                    <th scope="col" class="w-11 py-2"><span class="sr-only">Modifier</span></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="transaction in props.transactions"
                    :key="transaction.id"
                    class="border-b border-hairline-soft transition-opacity"
                    :class="transaction.is_pointed ? 'opacity-55' : 'opacity-100'"
                >
                    <td class="py-2.5">
                        <PointingToggle
                            :pointed="transaction.is_pointed"
                            :url="transaction.pointing_url"
                            :label="transaction.label"
                        />
                    </td>
                    <td class="py-2.5 text-[13px]">
                        <span class="flex items-center gap-[7px]">
                            {{ transaction.label }}
                            <PhIcon
                                v-if="transaction.is_recurring"
                                name="ph-arrows-clockwise"
                                class="text-[12px] text-ink-muted"
                            />
                            <PhIcon
                                v-if="transaction.is_revaluation"
                                name="ph-scales"
                                class="text-[12px] text-ink-muted"
                                title="Réévaluation de marché"
                            />
                            <PhIcon
                                v-if="transaction.is_upcoming"
                                name="ph-clock-countdown"
                                class="text-[12px] text-accent-soft"
                                title="À venir — comptée dans la projection, pas dans le solde du jour"
                            />
                        </span>
                    </td>
                    <td class="py-2.5">
                        <TagPill v-if="transaction.tag">{{ transaction.tag }}</TagPill>
                    </td>
                    <td class="py-2.5 text-[12px] text-ink-muted">{{ transaction.date_label }}</td>
                    <td class="py-2.5 text-right text-[13px]">
                        <Amount
                            :cents="transaction.amount_cents"
                            signed
                            :class="transaction.amount_cents > 0 ? 'text-accent-soft' : 'text-ink'"
                        />
                    </td>
                    <td class="py-2.5 text-right">
                        <Link
                            :href="transaction.edit_url"
                            class="text-[14px] text-ink-muted transition-colors hover:text-accent-soft"
                            :aria-label="`Modifier ${transaction.label}`"
                        >
                            <PhIcon name="ph-pencil-simple" />
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
