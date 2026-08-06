<script setup>
import { ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { useBugReport } from '../composables/useBugReport';
import { routes } from '../routes';
import PhIcon from './PhIcon.vue';

const page = usePage();
const { isOpen, close } = useBugReport();

const form = useForm({ subject: '', description: '' });

/** L'écran de confirmation remplace le formulaire jusqu'à la prochaine ouverture. */
const sent = ref(false);

watch(isOpen, (open) => {
    if (open) {
        sent.value = false;
        form.clearErrors();
    }
});

function send() {
    form.post(routes.bugReports, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            sent.value = true;
        },
    });
}

function statusClasses(report) {
    return report.status === 'resolu'
        ? 'border-accent text-accent-soft'
        : 'border-hairline text-ink-muted';
}
</script>

<template>
    <DialogRoot :open="isOpen" @update:open="(open) => !open && close()">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-80 bg-[rgba(10,11,20,0.6)]" />
            <!-- Mobile : feuille collée en bas de l'écran ; desktop : carte centrée. -->
            <DialogContent
                class="fixed inset-x-0 bottom-0 z-90 max-h-[78%] overflow-y-auto rounded-t-2xl border-t border-hairline bg-chrome p-4 pb-8 outline-none lg:inset-x-auto lg:top-1/2 lg:left-1/2 lg:bottom-auto lg:w-[460px] lg:max-h-[82vh] lg:-translate-x-1/2 lg:-translate-y-1/2 lg:rounded-card lg:border lg:p-5 lg:shadow-[0_24px_60px_rgba(0,0,0,0.5)]"
            >
                <div class="flex items-center gap-2 lg:gap-2.5">
                    <PhIcon name="ph-bug" class="text-[18px] text-accent lg:text-[19px]" />
                    <DialogTitle class="flex-1 text-[16px] font-medium lg:text-[16px]">Signaler un bug</DialogTitle>
                    <button
                        type="button"
                        class="cursor-pointer text-[16px] text-ink-muted transition-colors hover:text-accent-soft"
                        aria-label="Fermer"
                        @click="close"
                    >
                        <PhIcon name="ph-x" />
                    </button>
                </div>

                <template v-if="!sent">
                    <DialogDescription class="mt-[5px] text-[12px] text-ink-muted lg:mt-1.5 lg:text-[11px]">
                        <span class="lg:hidden">Envoyé par e-mail au mainteneur de l'application avec votre description.</span>
                        <span class="hidden lg:inline">
                            Votre signalement est envoyé par e-mail au mainteneur de l'application avec la
                            description ci-dessous.
                        </span>
                    </DialogDescription>

                    <p class="label-caps mt-3 mb-[5px] lg:mt-3.5">Sujet</p>
                    <input
                        v-model="form.subject"
                        type="text"
                        class="field"
                        placeholder="Ex. Le pointage ne s'enregistre pas"
                    />
                    <p v-if="form.errors.subject" class="mt-1.5 text-[13px] text-accent-soft">
                        {{ form.errors.subject }}
                    </p>

                    <p class="label-caps mt-[11px] mb-[5px] lg:mt-3">Description du bug</p>
                    <textarea
                        v-model="form.description"
                        :rows="4"
                        class="field resize-y lg:min-h-[110px]"
                        :placeholder="'Décrivez ce qui s\'est passé et comment le reproduire…'"
                    />
                    <p v-if="form.errors.description" class="mt-1.5 text-[13px] text-accent-soft">
                        {{ form.errors.description }}
                    </p>

                    <button
                        type="button"
                        class="btn-outline mt-3 flex w-full items-center justify-center gap-2 py-2.5 text-[15px] lg:mt-3.5"
                        :disabled="form.processing"
                        @click="send"
                    >
                        <PhIcon name="ph-paper-plane-tilt" class="text-[16px]" />
                        Envoyer au mainteneur
                    </button>
                </template>

                <div v-else class="mt-3 flex items-start gap-2.5 rounded-card bg-surface p-3 lg:mt-3.5 lg:p-3.5">
                    <PhIcon name="ph-check-circle" class="mt-px text-[18px] text-accent lg:text-[19px]" />
                    <div>
                        <p class="text-[15px] font-medium">Signalement envoyé</p>
                        <p class="mt-0.5 text-[12px] text-ink-muted lg:text-[11px]">
                            Un e-mail avec votre description a été envoyé au mainteneur. Vous serez notifié de la
                            résolution.
                        </p>
                    </div>
                </div>

                <template v-if="page.props.bug_reports.length > 0">
                    <p class="label-caps mt-3.5 mb-[3px] lg:mt-[18px] lg:mb-1">Mes signalements</p>
                    <ul>
                        <li
                            v-for="report in page.props.bug_reports"
                            :key="report.id"
                            class="flex items-center gap-2.5 border-b border-hairline-soft py-2"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[14px]">{{ report.subject }}</p>
                                <p class="mt-px text-[11px] text-ink-muted lg:text-[10px]">{{ report.date_label }}</p>
                            </div>
                            <span
                                class="shrink-0 rounded-pill border px-2 py-0.5 text-[12px] lg:px-[9px]"
                                :class="statusClasses(report)"
                            >
                                {{ report.status_label }}
                            </span>
                        </li>
                    </ul>
                </template>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
