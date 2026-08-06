<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ToastProvider, ToastRoot, ToastTitle, ToastViewport } from 'reka-ui';

const page = usePage();
const isOpen = ref(false);
const message = ref('');
const isError = ref(false);

/**
 * La clé change à chaque message : la notification est remontée, si bien que deux
 * messages identiques à la suite rouvrent bien la notification.
 */
const shownCount = ref(0);

function show(text, asError) {
    message.value = text;
    isError.value = asError;
    shownCount.value += 1;
    isOpen.value = true;
}

watch(
    () => [page.props.flash?.success, page.props.flash?.error],
    ([success, error]) => {
        if (error) {
            show(error, true);
        } else if (success) {
            show(success, false);
        }
    },
    { immediate: true },
);

/*
 * Un formulaire incomplet répond par des erreurs de validation, affichées sous
 * chaque champ — mais parfois hors écran au moment où l'on appuie sur le
 * bouton. La notification reprend la première pour qu'on sache toujours
 * pourquoi rien ne s'est passé.
 */
watch(
    () => page.props.errors,
    (errors) => {
        const messages = Object.values(errors ?? {});

        const others = messages.length - 1;

        if (messages.length === 1) {
            show(messages[0], true);
        } else if (messages.length > 1) {
            show(`${messages[0]} (+${others} ${others > 1 ? 'autres champs' : 'autre champ'})`, true);
        }
    },
);
</script>

<template>
    <ToastProvider>
        <ToastRoot
            :key="shownCount"
            v-model:open="isOpen"
            :duration="isError ? 6000 : 3500"
            class="rounded-card border bg-surface px-4 py-2.5 text-[14px] shadow-lg data-[state=closed]:opacity-0"
            :class="isError ? 'border-ink-muted text-ink' : 'border-accent text-accent-soft'"
        >
            <ToastTitle>{{ message }}</ToastTitle>
        </ToastRoot>
        <ToastViewport
            class="fixed bottom-28 left-1/2 z-60 flex w-max max-w-[calc(100vw-2rem)] -translate-x-1/2 flex-col gap-2 lg:right-6 lg:bottom-6 lg:left-auto lg:translate-x-0"
        />
    </ToastProvider>
</template>
