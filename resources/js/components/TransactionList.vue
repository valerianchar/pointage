<script setup>
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
                <p class="truncate text-[13px]">{{ transaction.label }}</p>
                <p class="mt-px flex items-center gap-1.5 text-[10px] text-ink-muted">
                    <TagPill v-if="transaction.tag">{{ transaction.tag }}</TagPill>
                    <span>{{ transaction.date_label }}</span>
                    <PhIcon v-if="transaction.is_recurring" name="ph-arrows-clockwise" class="text-[11px]" />
                </p>
            </div>
            <Amount
                :cents="transaction.amount_cents"
                signed
                class="text-[13px]"
                :class="transaction.amount_cents > 0 ? 'text-accent-soft' : 'text-ink'"
            />
        </li>
    </ul>
</template>
