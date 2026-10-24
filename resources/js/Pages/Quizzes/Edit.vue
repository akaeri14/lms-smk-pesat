<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    quiz: { type: Object, required: true },
    schoolClass: { type: Object, required: true },
});

const gameModeLabel = computed(() => ({
    live_individual: 'Live Host / Classic',
    assign: 'Assign (PR / Tugas)',
    team_battle: 'Team vs Team',
    steal_crown: 'Steal the Crown',
}[props.quiz.game_mode] || props.quiz.game_mode));

// Inisialisasi form dengan data kuis yang sudah ada
const form = useForm({
    title:            props.quiz.title,
    description:      props.quiz.description || '',
    duration_minutes: props.quiz.duration_minutes,
    max_attempts: props.quiz.max_attempts || null,
    quiz_mode: props.quiz.quiz_mode || 'live',
    game_mode: props.quiz.game_mode || 'live_individual',
    due_at: props.quiz.due_at ? props.quiz.due_at.slice(0, 16) : '',
    randomize_options: props.quiz.randomize_options ?? true,
    bgm: null,
    time_per_question: props.quiz.time_per_question || 30,
    enable_fifty_fifty: props.quiz.enable_fifty_fifty ?? true,
    enable_freeze_time: props.quiz.enable_freeze_time ?? true,
    questions: (props.quiz.questions || []).map((q) => ({
        id:            q.id,
        question_text: q.question_text,
        question_type: q.question_type || 'multiple_choice_single',
        points:        q.points,
        time_limit_seconds: q.time_limit_seconds || null,
        options: (q.options || []).map((o) => ({
            id:          o.id,
            option_text: o.option_text,
            is_correct:  o.is_correct,
        })),
    })),
});

const addQuestion = () => {
    form.questions.push({
        id: null,
        question_text: '',
        question_type: 'multiple_choice_single',
        points: 10,
        time_limit_seconds: null,
        options: [
            { id: null, option_text: '', is_correct: true },
            { id: null, option_text: '', is_correct: false },
            { id: null, option_text: '', is_correct: false },
            { id: null, option_text: '', is_correct: false },
        ],
    });
};

const removeQuestion = (qIndex) => {
    if (form.questions.length > 1) {
        form.questions.splice(qIndex, 1);
    }
};

const setCorrectOption = (qIndex, oIndex) => {
    if (form.questions[qIndex].question_type === 'multiple_choice_multiple') {
        form.questions[qIndex].options[oIndex].is_correct = !form.questions[qIndex].options[oIndex].is_correct;
        return;
    }
    form.questions[qIndex].options.forEach((opt, idx) => {
        opt.is_correct = idx === oIndex;
    });
};

const addOption = (qIndex) => {
    if (form.questions[qIndex].options.length < 6) {
        form.questions[qIndex].options.push({ id: null, option_text: '', is_correct: false });
    }
};

const removeOption = (qIndex, oIndex) => {
    if (form.questions[qIndex].options.length > 2) {
        const wasCorrect = form.questions[qIndex].options[oIndex].is_correct;
        form.questions[qIndex].options.splice(oIndex, 1);
        if (wasCorrect && form.questions[qIndex].options.length > 0) {
            form.questions[qIndex].options[0].is_correct = true;
        }
    }
};

const optionLabels = ['A', 'B', 'C', 'D', 'E', 'F'];

const submit = () => {
    form.put(route('quizzes.update', props.quiz.id), { forceFormData: true });
};
</script>

<template>
    <Head :title="`Edit Kuis: ${quiz.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('classes.show', schoolClass.id)"
                    class="text-sm font-medium text-slate-400 hover:text-[#0E3BB0] transition"
                >
                    {{ schoolClass.name }}
                </Link>
                <span class="text-slate-300">/</span>
                <Link
                    :href="route('quizzes.show', quiz.id)"
                    class="text-sm font-medium text-slate-400 hover:text-[#0E3BB0] transition"
                >
                    {{ quiz.title }}
                </Link>
                <span class="text-slate-300">/</span>
                <h2 class="text-lg font-bold text-slate-800">Edit Kuis</h2>
            </div>
        </template>

        <form @submit.prevent="submit" class="mx-auto max-w-4xl space-y-6 pb-12">
            <!-- Pengaturan Dasar Kuis -->
            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-6 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-[#0B2E8A]">
                            {{ schoolClass.name }} · {{ schoolClass.subject }}
                        </span>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-[#0E3BB0] border border-blue-200">
                            Mode: {{ gameModeLabel }}
                        </span>
                    </div>
                    <h3 class="mt-2 text-2xl font-extrabold text-slate-900">Edit Informasi Kuis</h3>
                    <p class="mt-1 text-xs text-slate-500">Perbarui judul, deskripsi, durasi, dan bank soal kuis ini.</p>
                </div>

                <div class="space-y-4">
                    <!-- Judul Kuis -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700">
                            Judul Kuis <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            required
                        />
                        <InputError :message="form.errors.title" class="mt-1" />
                    </div>

                    <!-- Deskripsi -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700">
                            Deskripsi <span class="text-xs font-normal text-slate-400">(Opsional)</span>
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>

                    <!-- Durasi -->
                    <div class="w-full sm:w-1/2">
                        <label class="block text-sm font-bold text-slate-700">
                            Durasi Pengerjaan (Menit) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative mt-1.5">
                            <input
                                v-model="form.duration_minutes"
                                type="number"
                                min="1"
                                max="180"
                                class="block w-full rounded-xl border-slate-300 pr-12 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                                required
                            />
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-xs font-bold text-slate-400">Menit</span>
                        </div>
                        <InputError :message="form.errors.duration_minutes" class="mt-1" />
                    </div>

                    <!-- Field Khusus Mode Assign (PR / Tugas) -->
                    <div
                        v-if="form.quiz_mode === 'assign' || form.game_mode === 'assign'"
                        class="grid gap-4 rounded-2xl border border-[#FFA800]/40 bg-[#FFA800]/5 p-4 sm:grid-cols-2"
                    >
                        <div>
                            <label class="block text-sm font-bold text-slate-700">
                                Tenggat Waktu (Due Date) <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.due_at"
                                type="datetime-local"
                                class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                                :required="form.quiz_mode === 'assign' || form.game_mode === 'assign'"
                            />
                            <InputError :message="form.errors.due_at" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700">
                                Batas Pengulangan (Attempt Limit)
                            </label>
                            <select
                                v-model="form.max_attempts"
                                class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            >
                                <option :value="null">Tanpa batas percobaan</option>
                                <option :value="1">1 kali</option>
                                <option :value="2">2 kali</option>
                                <option :value="3">3 kali</option>
                                <option :value="5">5 kali</option>
                                <option :value="10">10 kali</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-400">Jumlah percobaan per siswa.</p>
                            <InputError :message="form.errors.max_attempts" class="mt-1" />
                        </div>
                    </div>

                    <!-- Waktu per Soal & Opsi Gameplay -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-bold text-slate-700">Waktu per Soal</label>
                            <select
                                v-model="form.time_per_question"
                                class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            >
                                <option :value="15">15 detik</option>
                                <option :value="30">30 detik</option>
                                <option :value="45">45 detik</option>
                                <option :value="60">60 detik</option>
                            </select>
                            <InputError :message="form.errors.time_per_question" class="mt-1" />
                        </div>
                        <div class="flex flex-col justify-end gap-2.5 pb-1 text-sm text-slate-600">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-medium">
                                <input
                                    v-model="form.randomize_options"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-[#0E3BB0] focus:ring-[#0E3BB0]"
                                />
                                <span>Acak pilihan untuk siswa</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer font-medium">
                                <input
                                    v-model="form.enable_fifty_fifty"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-[#0E3BB0] focus:ring-[#0E3BB0]"
                                />
                                <span>Aktifkan bantuan 50:50</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer font-medium">
                                <input
                                    v-model="form.enable_freeze_time"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-[#0E3BB0] focus:ring-[#0E3BB0]"
                                />
                                <span>Aktifkan Freeze Time 10 detik</span>
                            </label>
                        </div>
                    </div>

                    <!-- Notifikasi Musik Background Global -->
                    <div class="flex items-center gap-2.5 rounded-xl border border-blue-100 bg-blue-50/70 p-3.5 text-xs text-blue-900">
                        <span class="text-base shrink-0">🎵</span>
                        <p class="leading-relaxed">
                            <strong>Musik Latar (BGM):</strong> Memutar otomatis playlist global yang dikelola Admin di menu <em>Atur Kuis</em> tanpa perlu input file lagi.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Bank Soal -->
            <div class="space-y-6">
                <div class="flex items-center justify-between px-1">
                    <div>
                        <h4 class="text-xl font-extrabold text-slate-800">Bank Soal Pilihan Ganda</h4>
                        <p class="text-xs text-slate-500">
                            Total {{ form.questions.length }} pertanyaan. Klik tombol radio untuk memilih kunci jawaban benar.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="addQuestion"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        + Tambah Soal
                    </button>
                </div>

                <div
                    v-for="(question, qIndex) in form.questions"
                    :key="qIndex"
                    class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8"
                >
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-2">
                            <span class="grid h-7 w-7 place-items-center rounded-lg bg-[#0E3BB0] text-xs font-extrabold text-white">
                                {{ qIndex + 1 }}
                            </span>
                            <span class="font-bold text-slate-800">Pertanyaan #{{ qIndex + 1 }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1.5">
                                <label class="text-xs font-bold text-slate-500">Poin:</label>
                                <input
                                    v-model="question.points"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="w-16 rounded-lg border-slate-200 py-1 text-center text-xs font-bold focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                                />
                            </div>
                            <select v-model="question.time_limit_seconds" class="w-32 rounded-lg border-slate-200 py-1 text-xs focus:border-[#0E3BB0] focus:ring-[#0E3BB0]">
                                <option :value="null">Default kuis</option>
                                <option :value="15">15 detik</option>
                                <option :value="30">30 detik</option>
                                <option :value="45">45 detik</option>
                                <option :value="60">60 detik</option>
                            </select>
                            <button
                                v-if="form.questions.length > 1"
                                type="button"
                                @click="removeQuestion(qIndex)"
                                class="text-xs font-bold text-red-500 hover:text-red-700"
                            >
                                Hapus Soal ✕
                            </button>
                        </div>
                    </div>

                    <!-- Teks Pertanyaan -->
                    <div class="mt-4">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-400">
                            Teks Soal <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="question.question_text"
                            rows="2"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            required
                        />
                    </div>

                    <div class="mt-4"><label class="block text-xs font-bold uppercase tracking-wider text-slate-400">Tipe Soal</label><select v-model="question.question_type" class="mt-1.5 rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"><option value="multiple_choice_single">Pilihan Ganda (satu jawaban)</option><option value="multiple_choice_multiple">Pilihan Ganda Kompleks (lebih dari satu)</option></select></div>

                    <!-- Opsi Jawaban -->
                    <div class="mt-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Pilihan Jawaban:
                            </label>
                            <button
                                v-if="question.options.length < 6"
                                type="button"
                                @click="addOption(qIndex)"
                                class="text-xs font-bold text-[#0E3BB0] hover:text-[#0B2E8A]"
                            >
                                + Tambah Opsi
                            </button>
                        </div>

                        <div
                            v-for="(option, oIndex) in question.options"
                            :key="oIndex"
                            :class="[
                                'flex items-center gap-3 rounded-xl border p-2.5 transition',
                                option.is_correct
                                    ? 'border-emerald-400 bg-emerald-50/60 ring-2 ring-emerald-400/20'
                                    : 'border-slate-200 bg-white hover:border-slate-300',
                            ]"
                        >
                            <button
                                type="button"
                                @click="setCorrectOption(qIndex, oIndex)"
                                :class="[
                                    'grid h-8 w-8 shrink-0 place-items-center rounded-lg text-xs font-bold transition shadow-sm',
                                    option.is_correct
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                                ]"
                            >
                                {{ option.is_correct ? '✓' : optionLabels[oIndex] }}
                            </button>

                            <input
                                v-model="option.option_text"
                                type="text"
                                :placeholder="`Teks pilihan ${optionLabels[oIndex]}...`"
                                class="block w-full border-0 bg-transparent p-0 text-sm focus:ring-0 text-slate-800 placeholder-slate-400"
                                required
                            />

                            <span
                                v-if="option.is_correct"
                                class="shrink-0 rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800"
                            >
                                Kunci Benar
                            </span>

                            <button
                                v-if="question.options.length > 2"
                                type="button"
                                @click="removeOption(qIndex, oIndex)"
                                class="shrink-0 px-2 text-xs font-bold text-slate-300 hover:text-red-500"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tombol Tambah Soal Bawah -->
                <button
                    type="button"
                    @click="addQuestion"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-5 text-sm font-bold text-slate-600 transition hover:border-blue-300 hover:bg-blue-50/40 hover:text-[#0E3BB0]"
                >
                    <span class="text-lg">+</span>
                    <span>Tambah Pertanyaan Soal Baru</span>
                </button>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 rounded-2xl border-t border-slate-200 bg-white/60 p-4 backdrop-blur-sm">
                <Link
                    :href="route('quizzes.show', quiz.id)"
                    class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-800"
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#0E3BB0] px-7 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#0B2E8A] disabled:opacity-50"
                >
                    <svg v-if="form.processing" class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                    </svg>
                    <span>{{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan Kuis' }}</span>
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
