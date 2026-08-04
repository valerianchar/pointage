<script setup>
import { Link } from '@inertiajs/vue3';
import { useNavigation } from '../composables/useNavigation';
import PhIcon from './PhIcon.vue';

const { items } = useNavigation();
</script>

<template>
    <!-- Le retrait bas suit l'encart sûr de l'appareil, sans descendre sous 26 px. -->
    <nav
        class="fixed inset-x-0 bottom-0 z-50 flex border-t border-hairline bg-chrome px-1.5 pt-2 pb-[max(1.625rem,env(safe-area-inset-bottom))] lg:hidden"
    >
        <Link
            v-for="item in items"
            :key="item.label"
            :href="item.href"
            class="flex flex-1 flex-col items-center gap-0.5"
            :class="item.isActive ? 'text-accent' : 'text-ink-muted'"
            :aria-current="item.isActive ? 'page' : undefined"
        >
            <PhIcon :name="item.icon" class="text-[21px]" />
            <span class="text-[9px]">{{ item.shortLabel ?? item.label }}</span>
        </Link>
    </nav>
</template>
