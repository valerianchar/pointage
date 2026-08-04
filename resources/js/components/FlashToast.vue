<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ToastProvider, ToastRoot, ToastTitle, ToastViewport } from 'reka-ui';

const page = usePage();
const isOpen = ref(false);
const message = ref('');

watch(
    () => page.props.flash?.success,
    (flashedMessage) => {
        if (!flashedMessage) {
            return;
        }

        message.value = flashedMessage;
        isOpen.value = true;
    },
    { immediate: true },
);
</script>

<template>
    <ToastProvider>
        <ToastRoot
            v-model:open="isOpen"
            :duration="3500"
            class="rounded-card border border-accent bg-surface px-4 py-2.5 text-[12px] text-accent-soft shadow-lg data-[state=closed]:opacity-0"
        >
            <ToastTitle>{{ message }}</ToastTitle>
        </ToastRoot>
        <ToastViewport
            class="fixed bottom-28 left-1/2 z-60 flex w-max max-w-[calc(100vw-2rem)] -translate-x-1/2 flex-col gap-2 lg:bottom-6 lg:left-auto lg:right-6 lg:translate-x-0"
        />
    </ToastProvider>
</template>
