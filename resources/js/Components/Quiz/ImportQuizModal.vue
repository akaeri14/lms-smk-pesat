<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    classes: {
        type: Array,
        default: () => [],
    },
    defaultClassId: {
        type: [Number, String],
        default: null,
    },
    gameMode: {
        type: String,
        default: 'live_individual',
    },
});

const emit = defineEmits(['close', 'success']);

const gameModeLabel = computed(() => ({
    live_individual: 'Live Host / Classic',
    assign: 'Assign (PR / Tugas)',
    team_battle: 'Team vs Team',
    steal_crown: 'Steal the Crown',
}[props.gameMode] || props.gameMode));

const fileInput = ref(null);

const form = useForm({
    class_id: props.defaultClassId || (props.classes[0]?.id ?? ''),
    title: '',
    description: '',
    duration_minutes: 15,
    max_attempts: null,
    due_at: '',
    quiz_mode: props.gameMode === 'assign' ? 'assign' : 'live',
    game_mode: props.gameMode,
    file: null,
});

watch(() => props.gameMode, (mode) => {
    form.game_mode = mode;
    form.quiz_mode = mode === 'assign' ? 'assign' : 'live';
});

watch(
    () => props.defaultClassId,
    (newVal) => {
        if (newVal) {
            form.class_id = newVal;
        }
    }
);

watch(
    () => props.classes,
    (newClasses) => {
        if (!form.class_id && newClasses.length > 0) {
            form.class_id = newClasses[0].id;
        }
    }
);

const onFileChange = (e) => {
    form.file = e.target.files[0] || null;
};

const closeModal = () => {
    emit('close');
    form.reset();
    form.clearErrors();
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submitImport = () => {
    form.post(route('quizzes.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            emit('success');
        },
    });
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
    >
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
            @click="closeModal"
        ></div>

        <!-- Modal Dialog -->
        <div class="relative w-full max-w-xl rounded-2xl bg-white p-6 sm:p-8 shadow-2xl z-10 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-100 text-emerald-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Import Soal Kuis dari Excel</h3>
                        <p class="text-xs text-slate-500">Unggah file spreadsheet untuk membuat kuis secara otomatis.</p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="closeModal"
                    class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                >
                    ✕
                </button>
            </div>

            <!-- Banner Unduh Template -->
            <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <span class="text-xs font-bold text-slate-800">Format Template Spreadsheet</span>
                        <p class="text-[11px] text-slate-600 mt-0.5">
                            Mode: <strong>{{ gameModeLabel }}</strong>. Gunakan kolom Pertanyaan, Tipe Soal, Opsi A–D, Jawaban Benar, Durasi, dan Poin.
                        </p>
                    </div>
                    <a
                        :href="route('quizzes.template-excel', { game_mode: gameMode })"
                        download
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-white px-3.5 py-2 text-xs font-bold text-[#0B2E8A] shadow-sm border border-blue-200 hover:bg-blue-50 transition"
                    >
                        <span>Unduh Template (.xlsx)</span>
                    </a>
                </div>
            </div>

            <!-- Form Import -->
            <form @submit.prevent="submitImport" class="space-y-4">
                <!-- Pilihan Kelas -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Kelas Tujuan <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.class_id"
                        class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        required
                    >
                        <option value="" disabled>Pilih Kelas</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">
                            {{ c.name }} ({{ c.subject }})
                        </option>
                    </select>
                    <InputError :message="form.errors.class_id" class="mt-1" />
                </div>

                <!-- Judul Kuis -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Judul Kuis <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        placeholder="Contoh: Kuis Bab 2 - Jaringan Komputer"
                        class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        required
                    />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>

                <!-- Deskripsi & Durasi -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Durasi Pengerjaan (Menit)
                        </label>
                        <input
                            v-model="form.duration_minutes"
                            type="number"
                            min="1"
                            max="180"
                            placeholder="Default: 15"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        />
                        <InputError :message="form.errors.duration_minutes" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Deskripsi Singkat <span class="text-slate-400 font-normal">(Opsional)</span>
                        </label>
                        <input
                            v-model="form.description"
                            type="text"
                            placeholder="Catatan kuis..."
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>
                </div>

                <div v-if="gameMode === 'assign'" class="grid gap-4 rounded-xl border border-[#FFA800]/30 bg-[#FFA800]/5 p-4 sm:grid-cols-2">
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Tenggat Waktu</label><input v-model="form.due_at" type="datetime-local" class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]" required /><InputError :message="form.errors.due_at" class="mt-1" /></div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Batas Pengulangan</label><input v-model="form.max_attempts" type="number" min="1" max="20" placeholder="Contoh: 3" class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]" /><p class="mt-1 text-[11px] text-slate-500">Kosongkan jika tanpa batas.</p></div>
                </div>

                <!-- File Excel Upload -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Unggah File Spreadsheet (.xlsx / .csv) <span class="text-red-500">*</span>
                    </label>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        @change="onFileChange"
                        class="mt-1.5 block w-full rounded-xl border border-slate-300 text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2.5 file:text-xs file:font-bold file:text-emerald-700 hover:file:bg-emerald-100 transition focus:outline-none"
                        required
                    />
                    <InputError :message="form.errors.file" class="mt-1.5" />
                </div>

                <!-- Footer Buttons -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button
                        type="button"
                        @click="closeModal"
                        class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-800"
                    >
                        Batal
                    </button>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#0E3BB0] px-6 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#0B2E8A] disabled:opacity-50"
                    >
                        <svg
                            v-if="form.processing"
                            class="h-4 w-4 animate-spin text-white"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ form.processing ? 'Memproses File...' : 'Mulai Import Soal' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
