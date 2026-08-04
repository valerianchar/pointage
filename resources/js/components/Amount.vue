<script setup>
import { computed } from 'vue';
import { useMoney } from '../composables/useMoney';

const props = defineProps({
    cents: { type: Number, required: true },
    /** Une opération porte toujours son signe ; un solde, non. */
    signed: { type: Boolean, default: false },
});

const { money, signedMoney } = useMoney();

const formatted = computed(() => (props.signed ? signedMoney(props.cents) : money(props.cents)));
</script>

<template>
    <span>{{ formatted }}</span>
</template>
