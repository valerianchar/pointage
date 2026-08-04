<script setup>
defineProps({
    name: { type: String, required: true },
});
</script>

<template>
    <i :class="['ph', name]" aria-hidden="true" />
</template>
