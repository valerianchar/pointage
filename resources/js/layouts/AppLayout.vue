<script setup>
import AppSidebar from '../components/AppSidebar.vue';
import AppTabBar from '../components/AppTabBar.vue';
import FlashToast from '../components/FlashToast.vue';
</script>

<template>
    <div class="flex min-h-dvh">
        <AppSidebar />

        <!--
            Mobile : contenu pleine largeur, dégagé sous la tab bar fixe.
            Desktop : sidebar figée à gauche, contenu à 36/44 px de marge.
        -->
        <main class="min-w-0 flex-1 px-4 pt-4 pb-32 lg:px-11 lg:pt-9 lg:pb-15">
            <slot />
        </main>
    </div>

    <AppTabBar />
    <FlashToast />
</template>
