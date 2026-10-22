<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    schoolClass: {
        type: Object,
        default: null,
    },
    classes: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    class_id: props.schoolClass?.id || props.classes?.[0]?.id || '',
    title: '',
    description: '',
    duration_minutes: 15,
    max_attempts: null,
    quiz_mode: 'live',
    due_at: '',
    randomize_options: true,
    bgm: null,
    time_per_question: 30,
    enable_fifty_fifty: true,
    enable_freeze_time: true,
    questions: [
        {
            question_text: '',
            question_type: 'multiple_choice_single',
            points: 10,
            time_limit_seconds: null,
            options: [
                { option_text: '', is_correct: true },
                { option_text: '', is_correct: false },
                { option_text: '', is_correct: false },
                { option_text: '', is_correct: false },
            ],
        },
    ],
});

const addQuestion = () => {
    form.questions.push({
        question_text: '',
        question_type: 'multiple_choice_single',
        points: 10,
        time_limit_seconds: null,
        options: [
            { option_text: '', is_correct: true },
            { option_text: '', is_correct: false },
            { option_text: '', is_correct: false },
            { option_text: '', is_correct: false },
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
        form.questions[qIndex].options.push({
            option_text: '',
            is_correct: false,
        });
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
const optionColorThemes = [
    { badge: 'bg-red-100 text-red-700 border-red-200', active: 'border-red-500 bg-red-50/40' },
    { badge: 'bg-blue-100 text-blue-700 border-blue-200', active: 'border-blue-500 bg-blue-50/40' },
    { badge: 'bg-amber-100 text-amber-700 border-amber-200', active: 'border-amber-500 bg-amber-50/40' },
    { badge: 'bg-emerald-100 text-emerald-700 border-emerald-200', active: 'border-emerald-500 bg-emerald-50/40' },
    { badge: 'bg-purple-100 text-purple-700 border-purple-200', active: 'border-purple-500 bg-purple-50/40' },
    { badge: 'bg-pink-100 text-pink-700 border-pink-200', active: 'border-pink-500 bg-pink-50/40' },
];

const submit = () => {
    form.post(route('quizzes.store'), { forceFormData: true });
};
</script>

<template>
    <Head title="Buat Game Kuis Baru" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="form.class_id ? route('classes.show', form.class_id) : route('quizzes.index')"
                    class="text-sm font-medium text-slate-400 hover:text-[#0E3BB0] transition"
                >
                    {{ schoolClass?.name || 'Daftar Kuis' }}
                </Link>
                <span class="text-slate-300">/</span>
                <span class="text-sm text-slate-400">Game Kuis</span>
                <span class="text-slate-300">/</span>
                <h2 class="text-xl font-bold text-slate-800">Buat Kuis Baru</h2>
            </div>
        </template>

        <form @submit.prevent="submit" class="mx-auto max-w-4xl space-y-6 pb-12">
            <!-- Pengaturan Dasar Kuis -->
            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-6 border-b border-slate-100 pb-4">
                    <span v-if="schoolClass" class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-[#0B2E8A]">
                        {{ schoolClass.name }} · {{ schoolClass.subject }}
                    </span>
                    <h3 class="mt-2 text-2xl font-extrabold text-slate-900">Informasi & Pengaturan Kuis</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Atur judul kuis, deskripsi, dan batas durasi waktu pengerjaan siswa.
                    </p>
                </div>

                <div class="space-y-4">
                    <!-- Pilihan Kelas jika ada lebih dari 1 kelas -->
                    <div v-if="classes.length > 0">
                        <label class="block text-sm font-bold text-slate-700">
                            Kelas Tujuan <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.class_id"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            required
                        >
                            <option v-for="c in classes" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.subject }})
                            </option>
                        </select>
                        <InputError :message="form.errors.class_id" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">
                            Judul Kuis <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="Contoh: Kuis Cepat Algoritma & Logika Dasar"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            required
                        />
                        <InputError :message="form.errors.title" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">
                            Deskripsi atau Petunjuk Kuis
                            <span class="text-xs font-normal text-slate-400">(Opsional)</span>
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            placeholder="Tuliskan petunjuk pengerjaan atau materi yang diujikan..."
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        ></textarea>
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>

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
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-xs font-bold text-slate-400">
                                Menit
                            </span>
                        </div>
                        <InputError :message="form.errors.duration_minutes" class="mt-1" />
                    </div>

                    <div class="w-full sm:w-1/2">
                        <label class="block text-sm font-bold text-slate-700">Batas Percobaan Siswa</label>
                        <select v-model="form.max_attempts" class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]">
                            <option :value="null">Tanpa batas percobaan</option>
                            <option :value="1">1 kali</option>
                            <option :value="2">2 kali</option>
                            <option :value="3">3 kali</option>
                            <option :value="5">5 kali</option>
                            <option :value="10">10 kali</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Jumlah ini dihitung per siswa.</p>
                        <InputError :message="form.errors.max_attempts" class="mt-1" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-bold text-slate-700">Mode Kuis</label>
                            <select v-model="form.quiz_mode" class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]">
                                <option value="live">Mulai Live Game</option>
                                <option value="assign">Assign / Berikan Tugas</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-400">Live dipandu host; Assign dapat dikerjakan mandiri.</p>
                        </div>
                        <div v-if="form.quiz_mode === 'assign'">
                            <label class="block text-sm font-bold text-slate-700">Tenggat Waktu <span class="text-red-500">*</span></label>
                            <input v-model="form.due_at" type="datetime-local" class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]" :required="form.quiz_mode === 'assign'" />
                            <InputError :message="form.errors.due_at" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-bold text-slate-700">Waktu per Soal</label>
                            <select v-model="form.time_per_question" class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]">
                                <option :value="15">15 detik</option>
                                <option :value="30">30 detik</option>
                                <option :value="45">45 detik</option>
                                <option :value="60">60 detik</option>
                            </select>
                            <InputError :message="form.errors.time_per_question" class="mt-1" />
                        </div>
                        <div class="flex flex-col justify-end gap-2 pb-1 text-sm text-slate-600">
                            <label class="inline-flex items-center gap-2"><input v-model="form.enable_fifty_fifty" type="checkbox" class="rounded border-slate-300 text-[#0E3BB0] focus:ring-[#0E3BB0]" /> Aktifkan bantuan 50:50</label>
                            <label class="inline-flex items-center gap-2"><input v-model="form.enable_freeze_time" type="checkbox" class="rounded border-slate-300 text-[#0E3BB0] focus:ring-[#0E3BB0]" /> Aktifkan Freeze Time 10 detik</label>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600"><input v-model="form.randomize_options" type="checkbox" class="rounded border-slate-300 text-[#0E3BB0] focus:ring-[#0E3BB0]" /> Acak pilihan untuk siswa</label>
                        <div><label class="block text-sm font-bold text-slate-700">Background Music (MP3)</label><input type="file" accept=".mp3,audio/mpeg" class="mt-1.5 block w-full text-xs text-slate-500" @change="form.bgm = $event.target.files[0] || null" /><InputError :message="form.errors.bgm" class="mt-1" /></div>
                    </div>
                </div>
            </div>

            <!-- Pembuat Soal Pilihan Ganda -->
            <div class="space-y-6">
                <div class="flex items-center justify-between px-1">
                    <div>
                        <h4 class="text-xl font-extrabold text-slate-800">Bank Soal Pilihan Ganda</h4>
                        <p class="text-xs text-slate-500">
                            Total {{ form.questions.length }} pertanyaan dibuat. Tandai salah satu pilihan sebagai kunci jawaban benar.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="addQuestion"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        <span>+ Tambah Soal</span>
                    </button>
                </div>

                <!-- Daftar Soal -->
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
                                title="Hapus pertanyaan ini"
                            >
                                Hapus Soal ✕
                            </button>
                        </div>
                    </div>

                    <!-- Teks Pertanyaan -->
                    <div class="mt-4">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-400">
                            Teks Soal / Pertanyaan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="question.question_text"
                            rows="2"
                            placeholder="Ketikkan teks pertanyaan di sini..."
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            required
                        ></textarea>
                    </div>

                    <div class="mt-4"><label class="block text-xs font-bold uppercase tracking-wider text-slate-400">Tipe Soal</label><select v-model="question.question_type" class="mt-1.5 rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"><option value="multiple_choice_single">Pilihan Ganda (satu jawaban)</option><option value="multiple_choice_multiple">Pilihan Ganda Kompleks (lebih dari satu)</option></select></div>

                    <!-- Opsi Jawaban Pilihan Ganda -->
                    <div class="mt-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Pilihan Jawaban (Klik tombol radio untuk memilih kunci jawaban benar):
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
                                    : 'border-slate-200 bg-white hover:border-slate-300'
                            ]"
                        >
                            <!-- Tombol Kunci Jawaban (Radio) -->
                            <button
                                type="button"
                                @click="setCorrectOption(qIndex, oIndex)"
                                :class="[
                                    'grid h-8 w-8 shrink-0 place-items-center rounded-lg text-xs font-bold transition shadow-sm',
                                    option.is_correct
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                ]"
                                title="Tandai sebagai kunci jawaban benar"
                            >
                                {{ option.is_correct ? '✓' : optionLabels[oIndex] }}
                            </button>

                            <!-- Input Teks Opsi -->
                            <input
                                v-model="option.option_text"
                                type="text"
                                :placeholder="`Teks pilihan ${optionLabels[oIndex]}...`"
                                class="block w-full border-0 bg-transparent p-0 text-sm focus:ring-0 text-slate-800 placeholder-slate-400"
                                required
                            />

                            <!-- Badge Kunci Jawaban -->
                            <span
                                v-if="option.is_correct"
                                class="shrink-0 rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800"
                            >
                                Kunci Benar
                            </span>

                            <!-- Hapus Opsi jika lebih dari 2 -->
                            <button
                                v-if="question.options.length > 2"
                                type="button"
                                @click="removeOption(qIndex, oIndex)"
                                class="shrink-0 px-2 text-xs font-bold text-slate-300 hover:text-red-500"
                                title="Hapus opsi ini"
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

            <!-- Tombol Aksi Bawah -->
            <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-white/60 backdrop-blur-sm p-4 rounded-2xl">
                <Link
                    :href="route('classes.show', schoolClass.id)"
                    class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-800"
                >
                    Batal
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#0E3BB0] px-7 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#0B2E8A] disabled:opacity-50"
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
                    <span>{{ form.processing ? 'Menyimpan Kuis...' : 'Publikasikan Game Kuis' }}</span>
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
