<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Chip from '../../components/Chip.vue';
import PhIcon from '../../components/PhIcon.vue';
import TagPill from '../../components/TagPill.vue';
import { routes } from '../../routes';

const props = defineProps({
    selected_account_id: { type: Number, default: null },
    type_note: { type: String, default: null },
    tags: { type: Array, required: true },
});

const page = usePage();
const accounts = computed(() => page.props.accounts);

const form = useForm({ account_id: props.selected_account_id, name: '' });

function selectAccount(accountId) {
    router.get(routes.tagsFor(accountId), {}, { preserveScroll: true, preserveState: false });
}

function addTag() {
    form.account_id = props.selected_account_id;
    form.post(routes.tags, { preserveScroll: true, onSuccess: () => form.reset('name') });
}

function deleteTag(tag) {
    router.delete(tag.url, { preserveScroll: true });
}

function operationsLabel(count) {
    return count > 1 ? `${count} opérations` : `${count} opération`;
}
</script>

<template>
    <Head title="Tags" />

    <div class="max-w-[640px]">
        <h1 class="text-xl lg:text-[22px]">Tags</h1>

        <p v-if="accounts.length === 0" class="mt-3 text-[13px] text-ink-muted">
            Déclarez d'abord un compte :
            <Link :href="routes.accountCreate" class="text-accent-soft">déclarer un compte</Link>.
        </p>

        <template v-else>
            <div class="mt-3 flex flex-wrap gap-[5px] lg:mt-4 lg:gap-1.5">
                <Chip
                    v-for="account in accounts"
                    :key="account.id"
                    :selected="account.id === props.selected_account_id"
                    @click="selectAccount(account.id)"
                >
                    {{ account.name }}
                </Chip>
            </div>

            <p class="mt-2.5 text-[10px] text-ink-muted lg:mt-3 lg:text-[11px]">{{ props.type_note }}</p>

            <ul class="mt-2.5">
                <li
                    v-for="tag in props.tags"
                    :key="tag.id"
                    class="flex items-center gap-2 border-b border-hairline-soft py-2 lg:gap-2.5 lg:py-[9px]"
                >
                    <TagPill>{{ tag.name }}</TagPill>
                    <span class="flex-1 text-[10px] text-ink-muted lg:text-[11px]">
                        {{ operationsLabel(tag.transactions_count) }}
                    </span>
                    <button
                        type="button"
                        class="cursor-pointer text-[13px] text-ink-muted transition-colors hover:text-accent-soft lg:text-[14px]"
                        :aria-label="`Supprimer le tag ${tag.name}`"
                        @click="deleteTag(tag)"
                    >
                        <PhIcon name="ph-x" />
                    </button>
                </li>
            </ul>

            <p v-if="props.tags.length === 0" class="mt-3 text-[13px] text-ink-muted">
                Plus aucun tag sur ce compte.
            </p>

            <form class="mt-3 flex gap-1.5 lg:mt-3.5 lg:gap-2" @submit.prevent="addTag">
                <input v-model="form.name" type="text" class="field min-w-0 flex-1 text-[13px]" placeholder="Nouveau tag" />
                <button type="submit" class="btn-outline shrink-0 px-3.5 text-[12px] lg:px-4" :disabled="form.processing">
                    Ajouter
                </button>
            </form>
            <p v-if="form.errors.name" class="mt-1.5 text-[11px] text-accent-soft">{{ form.errors.name }}</p>
        </template>
    </div>
</template>
