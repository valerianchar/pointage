<script setup>
import { Link } from '@inertiajs/vue3';
import Amount from './Amount.vue';
import PhIcon from './PhIcon.vue';

const props = defineProps({
    account: { type: Object, required: true },
});
</script>

<template>
    <Link
        :href="props.account.url"
        class="flex items-center gap-2.5 rounded-card bg-surface px-3 py-2.5 transition-colors hover:bg-surface-hover lg:gap-3 lg:px-4 lg:py-[15px]"
    >
        <PhIcon :name="props.account.icon" class="text-[19px] text-accent lg:text-[22px]" />
        <span class="min-w-0 flex-1">
            <span class="block truncate text-[13px] font-medium lg:text-[14px]">{{ props.account.name }}</span>
            <span class="block text-[10px] text-ink-muted lg:text-[11px]">{{ props.account.type_label }}</span>
        </span>
        <span class="text-right">
            <Amount :cents="props.account.balance_cents" class="block text-[13px] lg:text-[15px]" />
            <span v-if="props.account.pending_count > 0" class="block text-[10px] text-accent-soft lg:text-[11px]">
                {{ props.account.pending_count }} à pointer
            </span>
        </span>
    </Link>
</template>
