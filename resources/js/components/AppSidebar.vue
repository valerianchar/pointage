<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { useNavigation } from '../composables/useNavigation';
import { routes } from '../routes';
import Amount from './Amount.vue';
import PhIcon from './PhIcon.vue';
import PrivacyToggle from './PrivacyToggle.vue';

const page = usePage();
const { items, accounts } = useNavigation();

function signOut() {
    router.post(routes.logout);
}
</script>

<template>
    <aside
        class="sticky top-0 hidden h-dvh w-[248px] shrink-0 flex-col border-r border-hairline-soft bg-chrome px-3.5 py-[22px] lg:flex"
    >
        <Link :href="routes.dashboard" class="flex items-center gap-2.5 px-2 text-ink">
            <PhIcon name="ph-wallet" class="text-[22px] text-accent" />
            <span class="text-base font-medium tracking-[-0.015em]">Pointage</span>
        </Link>

        <nav class="mt-6 flex flex-col gap-0.5">
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                class="flex items-center gap-2.5 rounded-card px-2.5 py-2 transition-colors hover:bg-surface"
                :class="item.isActive ? 'bg-surface text-accent-soft' : 'text-ink-muted'"
                :aria-current="item.isActive ? 'page' : undefined"
            >
                <PhIcon :name="item.icon" class="text-[17px]" />
                <span class="text-[13px]">{{ item.label }}</span>
            </Link>
        </nav>

        <p class="label-caps mt-[22px] mb-2 px-2.5">Comptes</p>
        <div class="flex flex-col gap-0.5 overflow-y-auto">
            <Link
                v-for="account in accounts"
                :key="account.id"
                :href="account.url"
                class="flex items-center gap-2.5 rounded-card px-2.5 py-[7px] transition-colors hover:bg-surface"
            >
                <PhIcon :name="account.icon" class="text-[15px] text-accent" />
                <span class="min-w-0 flex-1 truncate text-[12px]">{{ account.name }}</span>
                <Amount :cents="account.balance_cents" class="text-[11px] text-ink-muted" />
            </Link>
        </div>

        <Link
            :href="routes.accountCreate"
            class="mt-1 flex items-center gap-2.5 rounded-card px-2.5 py-[7px] text-[12px] text-accent-soft transition-colors hover:bg-surface"
        >
            <PhIcon name="ph-plus" class="text-[15px]" />
            Déclarer un compte
        </Link>

        <div class="flex-1" />

        <div class="flex items-center gap-2.5 border-t border-hairline-soft px-2.5 pt-2.5">
            <span
                class="flex size-[30px] shrink-0 items-center justify-center rounded-full bg-accent-surface text-[11px] text-accent-soft"
            >
                {{ page.props.auth.user.initials }}
            </span>
            <span class="min-w-0 flex-1 truncate text-[12px]">{{ page.props.auth.user.name }}</span>
            <PrivacyToggle class="text-[17px]" />
            <button
                type="button"
                class="cursor-pointer text-[17px] text-ink-muted transition-colors hover:text-accent-soft"
                title="Se déconnecter"
                aria-label="Se déconnecter"
                @click="signOut"
            >
                <PhIcon name="ph-sign-out" />
            </button>
        </div>
    </aside>
</template>
