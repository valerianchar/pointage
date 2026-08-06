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
    <ul class="lg:hidden">
        <li
            v-for="transaction in props.transactions"
            :key="transaction.id"
            class="flex items-center gap-2.5 border-b border-hairline-soft py-[9px] transition-opacity"
            :class="transaction.is_pointed ? 'opacity-55' : 'opacity-100'"
        >
            <PointingToggle
                :pointed="transaction.is_pointed"
                :url="transaction.pointing_url"
                :label="transaction.label"
            />
            <div class="min-w-0 flex-1">
                <p class="truncate text-[15px]">{{ transaction.label }}</p>
                <p class="mt-px flex items-center gap-1.5 text-[12px] text-ink-muted">
                    <TagPill v-if="transaction.tag">{{ transaction.tag }}</TagPill>
                    <span>{{ transaction.date_label }}</span>
                    <PhIcon v-if="transaction.is_recurring" name="ph-arrows-clockwise" class="text-[13px]" />
                    <PhIcon
                        v-if="transaction.is_revaluation"
                        name="ph-scales"
                        class="text-[13px]"
                        title="Réévaluation de marché"
                    />
                </p>
            </div>
            <Amount
                :cents="transaction.amount_cents"
                signed
                class="text-[15px]"
                :class="transaction.amount_cents > 0 ? 'text-accent-soft' : 'text-ink'"
            />
            <Link
                :href="transaction.edit_url"
                class="shrink-0 text-[15px] text-ink-muted transition-colors hover:text-accent-soft"
                :aria-label="`Modifier ${transaction.label}`"
            >
                <PhIcon name="ph-pencil-simple" />
            </Link>
        </li>
    </ul>
</template>
