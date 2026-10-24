<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImportQuizModal from '@/Components/Quiz/ImportQuizModal.vue';

const props = defineProps({
    quizzes: {
        type: Array,
        required: true,
    },
    classes: {
        type: Array,
        default: () => [],
    },
    canCreateQuiz: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash ?? {});

const showImportModal = ref(false);
const showModeSelector = ref(false);
const showCreateModal = ref(false);
const selectedAction = ref('create');
const selectedMode = ref('live_individual');
const importMode = ref('live_individual');

const form = useForm({
    class_id: props.classes?.[0]?.id || '',
    title: '',
    description: '',
    duration_minutes: 15,
    max_attempts: null,
    quiz_mode: 'live',
    game_mode: 'live_individual',
    due_at: '',
    time_per_question: 30,
    randomize_options: true,
    enable_fifty_fifty: true,
    enable_freeze_time: true,
});

const gameModes = [
    {
        value: 'live_individual',
        title: 'Live Host / Classic',
        description: 'Kuis interaktif real-time yang dipandu guru di kelas.',
        color: 'blue',
        icon: 'play',
    },
    {
        value: 'assign',
        title: 'Assign (PR / Tugas)',
        description: 'Kuis mandiri dengan tenggat waktu dan batas pengulangan.',
        color: 'gold',
        icon: 'clipboard',
    },
    {
        value: 'team_battle',
        title: 'Team vs Team',
        description: 'Pertarungan antar-tim dengan akumulasi skor kelompok.',
        color: 'green',
        icon: 'users',
    },
    {
        value: 'steal_crown',
        title: 'Steal the Crown',
        description: 'Mode kompetitif untuk merebut posisi Raja/Ratu kuis.',
        color: 'gold',
        icon: 'crown',
    },
];

const selectedModeDetails = computed(() => {
    return gameModes.find((m) => m.value === selectedMode.value) || gameModes[0];
});

const openModeSelector = (action = 'create') => {
    selectedAction.value = action;
    showModeSelector.value = true;
};

const onSelectMode = (mode) => {
    selectedMode.value = mode;
    form.game_mode = mode;
    form.quiz_mode = mode === 'assign' ? 'assign' : 'live';
    showModeSelector.value = false;

    if (selectedAction.value === 'import') {
        importMode.value = mode;
        showImportModal.value = true;
        return;
    }

    // Alur Buat Manual: buka modal form kuis yang membawa variabel game_mode tersebut
    showCreateModal.value = true;
};

const chooseMode = onSelectMode;

const submitCreateForm = () => {
    showCreateModal.value = false;
    const query = {
        game_mode: form.game_mode,
        quiz_mode: form.quiz_mode,
        time_per_question: form.time_per_question,
        randomize_options: form.randomize_options ? 1 : 0,
        enable_fifty_fifty: form.enable_fifty_fifty ? 1 : 0,
        enable_freeze_time: form.enable_freeze_time ? 1 : 0,
    };
    if (form.class_id) query.class_id = form.class_id;
    if (form.title) query.title = form.title;
    if (form.description) query.description = form.description;
    if (form.duration_minutes) query.duration_minutes = form.duration_minutes;
    if (form.game_mode === 'assign') {
        if (form.due_at) query.due_at = form.due_at;
        if (form.max_attempts) query.max_attempts = form.max_attempts;
    }

    router.visit(route('quizzes.create'), {
        data: query,
    });
};

const deleteQuiz = (quiz) => {
    if (!window.confirm(`Hapus kuis "${quiz.title}" beserta soal dan hasil pengerjaannya?`)) return;

    router.delete(route('quizzes.destroy', quiz.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Game Kuis" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between w-full">
                <h2 class="text-xl font-bold text-slate-800">Game Kuis</h2>

                <!-- Tombol Header untuk Guru & Admin -->
                <div v-if="canCreateQuiz || user.role === 'guru' || user.role === 'admin'" class="flex items-center gap-2.5">
                    <!-- Tombol Import Excel -->
                    <button
                        type="button"
                        @click="openModeSelector('import')"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50/80 px-4 py-2 text-xs font-bold text-emerald-800 shadow-sm transition hover:bg-emerald-100"
                    >
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Import Excel</span>
                    </button>

                    <!-- Tombol Buat Kuis Baru (Manual) -->
                    <button
                        type="button"
                        @click="openModeSelector('create')"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-[#0E3BB0] px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#0B2E8A]"
                    >
                        <span>+ Buat Kuis Baru</span>
                    </button>
                </div>
            </div>
        </template>

        <section class="mx-auto max-w-6xl space-y-6">
            <!-- Flash Message Banner -->
            <div
                v-if="flash.success"
                class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 shadow-sm"
            >
                <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ flash.success }}</span>
            </div>

            <div>
                <h3 class="text-2xl font-extrabold text-slate-900">Daftar Game Kuis Interaktif</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ user.role === 'guru' ? 'Kelola kuis, bank soal, dan pantau leaderboard hasil pengerjaan siswa.' : 'Uji wawasanmu, kumpulkan poin skor, dan raih posisi teratas di leaderboard kelas!' }}
                </p>
            </div>

            <div v-if="quizzes.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="quiz in quizzes"
                    :key="quiz.id"
                    class="flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-6 shadow-sm transition hover:border-blue-200 hover:shadow-md"
                >
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-[#0B2E8A]">
                                {{ quiz.school_class?.name }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-400">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9" stroke-width="1.8" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 7v5l3 2" /></svg>
                                {{ quiz.duration_minutes }}m
                            </span>
                        </div>

                        <h4 class="mt-3 text-lg font-extrabold text-slate-800 hover:text-[#0E3BB0] transition">
                            {{ quiz.title }}
                        </h4>

                        <p class="mt-1 line-clamp-2 text-xs text-slate-500">
                            {{ quiz.description || 'Kuis interaktif pilihan ganda ala game untuk menguji pemahaman materi.' }}
                        </p>

                        <div class="mt-4 flex items-center gap-3 text-xs text-slate-400">
                            <span>{{ quiz.questions_count ?? 0 }} Soal</span>
                            <span>•</span>
                            <span>{{ quiz.school_class?.subject }}</span>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-slate-100 pt-4">
                        <!-- Sisi Guru: Statistik Pengerjaan & Link Detail -->
                        <div v-if="user.role === 'guru' || user.role === 'admin'" class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-600">{{ quiz.attempts_count ?? 0 }} Siswa Selesai</span>
                            <div class="flex items-center gap-2">
                                <Link
                                    v-if="quiz.quiz_mode !== 'assign'"
                                    :href="route('quizzes.host', quiz.id)"
                                    class="rounded-lg bg-[#FFA800] px-2.5 py-1.5 text-xs font-bold text-slate-950 transition hover:bg-[#F59E0B]"
                                >
                                    Host
                                </Link>
                                <Link
                                    :href="route('quizzes.edit', quiz.id)"
                                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50 hover:text-[#0E3BB0]"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    @click="deleteQuiz(quiz)"
                                    class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-100"
                                >
                                    Hapus
                                </button>
                                <Link
                                    :href="route('quizzes.show', quiz.id)"
                                    class="inline-flex items-center gap-1 text-xs font-bold text-[#0E3BB0] hover:text-[#0B2E8A]"
                                >
                                    <span>Detail</span><span>→</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Sisi Siswa: Status Skor & Tombol Play -->
                        <div v-else-if="user.role === 'siswa'" class="flex items-center justify-between">
                            <div>
                                <span
                                    v-if="quiz.attempts?.[0]"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800"
                                >
                                    ✓ Skor: {{ quiz.attempts[0].score }}/100
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"
                                >
                                    Belum Dimainkan
                                </span>
                            </div>

                            <Link
                                :href="route('quizzes.play', quiz.id)"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-[#0E3BB0] px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#0B2E8A]"
                            >
                                <span>{{ quiz.attempts?.[0] ? 'Main Lagi' : 'Mainkan' }}</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-2xl border border-slate-100 bg-white p-12 text-center shadow-sm">
                <svg class="mx-auto h-12 w-12 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 3h6l1 4H8l1-4ZM7 7h10l2 12H5L7 7Z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 11h6M10 15h4" /></svg>
                <h4 class="mt-4 text-lg font-bold text-slate-800">Belum Ada Game Kuis</h4>
                <p class="mt-1 text-sm text-slate-500">
                    {{ user.role === 'guru' ? 'Buat kuis interaktif baru atau import dari file Excel.' : 'Belum ada game kuis yang tersedia untuk kelas Anda saat ini.' }}
                </p>
                <div v-if="user.role === 'guru' || user.role === 'admin'" class="mt-5 flex items-center justify-center gap-3">
                    <button
                        type="button"
                        @click="openModeSelector('import')"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-100"
                    >
                        <span>Import Excel</span>
                    </button>
                    <button
                        type="button"
                        @click="openModeSelector('create')"
                        class="rounded-xl bg-[#0E3BB0] px-4 py-2 text-xs font-bold text-white hover:bg-[#0B2E8A]"
                    >
                        + Buat Kuis Baru
                    </button>
                </div>
            </div>
        </section>

        <!-- Modal Pemilihan Mode Game -->
        <div v-if="showModeSelector" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" @click="showModeSelector = false"></div>
            <div class="relative w-full max-w-3xl rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-5">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-[#FFA800]">Game Mode Selector</p>
                        <h3 class="mt-1 text-2xl font-black text-slate-900">Pilih mode game terlebih dahulu</h3>
                        <p class="mt-1 text-sm text-slate-500">Pilih pengalaman kuis yang sesuai sebelum membuat atau mengimpor soal.</p>
                    </div>
                    <button type="button" @click="showModeSelector = false" class="grid h-9 w-9 place-items-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-800" aria-label="Tutup modal">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m6 6 12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <button v-for="mode in gameModes" :key="mode.value" type="button" @click="onSelectMode(mode.value)" class="group rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition duration-200 hover:-translate-y-1 hover:border-[#0E3BB0] hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-[#0E3BB0]/15">
                        <div class="flex items-start gap-4">
                            <div :class="['grid h-12 w-12 shrink-0 place-items-center rounded-2xl transition group-hover:scale-105', mode.color === 'blue' ? 'bg-[#0E3BB0]/10 text-[#0E3BB0]' : mode.color === 'green' ? 'bg-emerald-100 text-emerald-700' : 'bg-[#FFA800]/15 text-[#C77700]']">
                                <svg v-if="mode.icon === 'play'" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m8 5 11 7-11 7V5Z"/></svg>
                                <svg v-else-if="mode.icon === 'clipboard'" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5h6M8 3h8a2 2 0 0 1 2 2v15H6V5a2 2 0 0 1 2-2ZM9 10h6M9 14h6"/></svg>
                                <svg v-else-if="mode.icon === 'users'" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM17 8a3 3 0 0 1 0 6M21 20v-1a4 4 0 0 0-3-3.87"/></svg>
                                <svg v-else class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 7 3 3 3-6 3 6 3-6 3 6 3-3-2 12H5L3 7Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 19h14"/></svg>
                            </div>
                            <div>
                                <h4 class="font-black text-slate-900 group-hover:text-[#0E3BB0]">{{ mode.title }}</h4>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ mode.description }}</p>
                                <span class="mt-3 inline-flex text-[11px] font-black text-[#0E3BB0]">Pilih mode <span class="ml-1 transition group-hover:translate-x-1">→</span></span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Form Pembuatan Kuis Manual -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" @click="showCreateModal = false"></div>
            <div class="relative w-full max-w-xl rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-5">
                    <div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-[#0E3BB0]">
                            Mode: {{ selectedModeDetails?.title }}
                        </span>
                        <h3 class="mt-2 text-2xl font-black text-slate-900">Buat Game Kuis Baru</h3>
                        <p class="mt-1 text-xs text-slate-500">Lengkapi informasi dasar kuis sebelum menyusun pertanyaan & jawaban.</p>
                    </div>
                    <button
                        type="button"
                        @click="showCreateModal = false"
                        class="grid h-9 w-9 place-items-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-800"
                        aria-label="Tutup modal"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m6 6 12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitCreateForm" class="mt-6 space-y-4">
                    <!-- Kelas Tujuan -->
                    <div v-if="classes && classes.length > 0">
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
                    </div>

                    <!-- Judul Kuis -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Judul Kuis <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="Contoh: Kuis Cepat Algoritma & Logika Dasar"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            required
                        />
                    </div>

                    <!-- Deskripsi & Durasi -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Durasi Pengerjaan (Menit) <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.duration_minutes"
                                type="number"
                                min="1"
                                max="180"
                                placeholder="15"
                                class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Deskripsi Singkat <span class="text-slate-400 font-normal">(Opsional)</span>
                            </label>
                            <input
                                v-model="form.description"
                                type="text"
                                placeholder="Petunjuk pengerjaan..."
                                class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            />
                        </div>
                    </div>

                    <!-- Waktu per Soal & Opsi Gameplay -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Waktu per Soal
                            </label>
                            <select
                                v-model="form.time_per_question"
                                class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            >
                                <option :value="15">15 detik</option>
                                <option :value="30">30 detik</option>
                                <option :value="45">45 detik</option>
                                <option :value="60">60 detik</option>
                            </select>
                        </div>

                        <!-- Checkbox Opsi Gameplay -->
                        <div class="flex flex-col justify-end gap-2 text-xs font-semibold text-slate-600">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="form.randomize_options"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-[#0E3BB0] focus:ring-[#0E3BB0]"
                                />
                                <span>Acak pilihan untuk siswa</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="form.enable_fifty_fifty"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-[#0E3BB0] focus:ring-[#0E3BB0]"
                                />
                                <span>Aktifkan bantuan 50:50</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
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
                    <div class="flex items-center gap-2.5 rounded-xl border border-blue-100 bg-blue-50/70 p-3 text-xs text-blue-900">
                        <span class="text-base shrink-0">🎵</span>
                        <p class="leading-relaxed">
                            <strong>Musik Latar (BGM):</strong> Memutar otomatis playlist global yang dikelola Admin di menu <em>Atur Kuis</em>.
                        </p>
                    </div>

                    <!-- Field Tambahan jika mode Assign (PR / Tugas) -->
                    <div
                        v-if="selectedMode === 'assign' || form.game_mode === 'assign'"
                        class="grid gap-4 rounded-2xl border border-[#FFA800]/40 bg-[#FFA800]/5 p-4 sm:grid-cols-2"
                    >
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Tenggat Waktu (Due Date) <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.due_at"
                                type="datetime-local"
                                class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                                :required="selectedMode === 'assign' || form.game_mode === 'assign'"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Batas Pengulangan (Attempt Limit)
                            </label>
                            <input
                                v-model="form.max_attempts"
                                type="number"
                                min="1"
                                max="20"
                                placeholder="Contoh: 3"
                                class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            />
                            <p class="mt-1 text-[11px] text-slate-500">Kosongkan jika tanpa batas percobaan.</p>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="rounded-xl px-4 py-2.5 text-xs font-bold text-slate-500 hover:text-slate-800 transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#0E3BB0] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#0B2E8A]"
                        >
                            <span>Lanjut ke Penyusunan Soal</span>
                            <span aria-hidden="true">→</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Import Kuis dari Excel -->
        <ImportQuizModal
            :show="showImportModal"
            :classes="classes"
            :game-mode="importMode"
            @close="showImportModal = false"
        />
    </AuthenticatedLayout>
</template>
