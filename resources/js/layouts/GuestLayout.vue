<script setup>
import AppLogo from '../components/AppLogo.vue';
import FlashToast from '../components/FlashToast.vue';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
});
</script>

<template>
    <div
        class="flex min-h-dvh justify-center px-7 pt-[calc(env(safe-area-inset-top)+4rem)] lg:items-center lg:px-10 lg:pt-0"
    >
        <div class="flex w-full max-w-[380px] flex-col items-center pb-16">
            <AppLogo />
            <h1 class="mt-4 text-2xl lg:text-[26px]">{{ props.title }}</h1>
            <p class="mt-1 text-center text-[14px] text-ink-muted lg:text-[13px]">{{ props.subtitle }}</p>

            <div class="mt-9 w-full lg:mt-8">
                <slot />
            </div>

            <div v-if="$slots.footer" class="mt-6 text-[13px] text-ink-muted lg:text-[12px]">
                <slot name="footer" />
            </div>
        </div>
    </div>

    <!-- Les écrans invités doivent aussi pouvoir dire qu'une session a expiré. -->
    <FlashToast />
</template>
