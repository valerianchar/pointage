<script setup>
import { ref } from 'vue';
import { routes } from '../routes';

/*
 * Champ d'actif avec recherche par nom : on tape « amundi msci world », le
 * serveur interroge le fournisseur de cours du type de compte et propose les
 * actifs correspondants — choisir en remplit l'identifiant exact. La saisie
 * directe d'un identifiant connu (« bitcoin », « EWLD.PA ») marche toujours.
 */
const props = defineProps({
    modelValue: { type: String, required: true },
    /** Type du compte : le serveur en déduit le fournisseur à interroger. */
    accountType: { type: String, required: true },
    placeholder: { type: String, default: '' },
    ariaLabel: { type: String, default: 'Actif recherché par nom ou identifiant' },
    inputClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const results = ref([]);
/** Actif choisi dans la liste — son nom reste affiché sous le champ. */
const chosen = ref(null);

let debounce = null;
let lastQuery = '';

function onInput(event) {
    const value = event.target.value;
    emit('update:modelValue', value);
    chosen.value = null;

    clearTimeout(debounce);
    const query = value.trim();

    if (query.length < 2) {
        results.value = [];
        return;
    }

    debounce = setTimeout(() => search(query), 300);
}

async function search(query) {
    lastQuery = query;

    try {
        const response = await fetch(
            `${routes.assetSearch}?type=${encodeURIComponent(props.accountType)}&q=${encodeURIComponent(query)}`,
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            return;
        }

        const found = await response.json();

        // Une réponse lente ne doit pas écraser celle d'une frappe plus récente.
        if (query === lastQuery) {
            results.value = found;
        }
    } catch {
        /* Recherche en panne : la saisie directe reste possible. */
    }
}

function choose(result) {
    chosen.value = result;
    results.value = [];
    emit('update:modelValue', result.asset_id);
}

/* Au clavier comme au doigt, le mousedown précède le blur : on choisit avant que la liste ne se ferme. */
function closeSoon() {
    setTimeout(() => {
        results.value = [];
    }, 150);
}
</script>

<template>
    <div class="relative min-w-0">
        <input
            :value="props.modelValue"
            type="text"
            class="field w-full"
            :class="props.inputClass"
            :placeholder="props.placeholder"
            autocomplete="off"
            :aria-label="props.ariaLabel"
            role="combobox"
            :aria-expanded="results.length > 0"
            @input="onInput"
            @blur="closeSoon"
        />

        <div
            v-if="results.length > 0"
            class="absolute top-full right-0 left-0 z-30 mt-1 overflow-hidden rounded-card border border-hairline bg-chrome"
            role="listbox"
        >
            <button
                v-for="result in results"
                :key="result.asset_id"
                type="button"
                role="option"
                class="flex w-full cursor-pointer items-baseline gap-2 px-3 py-2 text-left transition-colors hover:bg-surface-hover"
                @mousedown.prevent="choose(result)"
            >
                <span class="min-w-0 flex-1 truncate text-[13px]">{{ result.label }}</span>
                <span class="shrink-0 text-[11px] text-ink-muted">
                    {{ result.asset_id }}<template v-if="result.detail"> · {{ result.detail }}</template>
                </span>
            </button>
        </div>

        <p v-if="chosen" class="mt-1 truncate text-[11px] text-ink-muted">{{ chosen.label }}</p>
    </div>
</template>
