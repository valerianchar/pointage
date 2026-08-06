<script setup>
import { onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '../components/AppSidebar.vue';
import AppTabBar from '../components/AppTabBar.vue';
import BugReportDialog from '../components/BugReportDialog.vue';
import FlashToast from '../components/FlashToast.vue';
import MustPointDialog from '../components/MustPointDialog.vue';
import PointingDueBanner from '../components/PointingDueBanner.vue';
import { connectRealtime } from '../realtime';

const page = usePage();

/* Les écrans authentifiés s'abonnent au canal privé de l'utilisateur. */
onMounted(() => connectRealtime(page.props.broadcast));
</script>

<template>
    <div class="flex min-h-dvh">
        <AppSidebar />

        <!--
            Mobile : contenu pleine largeur, dégagé sous la tab bar fixe.
            Desktop : sidebar figée à gauche, contenu à 36/44 px de marge.
        -->
        <!-- L'app installée passe sous la barre de statut du téléphone
             (viewport-fit=cover) : le haut du contenu s'en écarte. -->
        <main
            class="min-w-0 flex-1 px-4 pt-[calc(env(safe-area-inset-top)+1rem)] pb-32 lg:px-11 lg:pt-9 lg:pb-15"
        >
            <PointingDueBanner />
            <slot />
        </main>
    </div>

    <AppTabBar />
    <MustPointDialog />
    <BugReportDialog />
    <FlashToast />
</template>
