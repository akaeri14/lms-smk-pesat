<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ImportQuizModal from '@/Components/Quiz/ImportQuizModal.vue';

const props = defineProps({
    schoolClass: {
        type: Object,
        required: true,
    },
    canManage: Boolean,
    canCreateAssignment: Boolean,
    canCreateQuiz: Boolean,
    profilingSummary: {
        type: Object,
        default: null,
    },
    gradebook: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const showImportModal = ref(false);

const activeTab = ref('overview');
const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'profiling', label: 'Profiling Kelas' },
    { id: 'grades', label: 'Nilai' },
    { id: 'materials', label: 'Materi' },
    { id: 'assignments', label: 'Tugas' },
    { id: 'quizzes', label: 'Game Kuis' },
    { id: 'members', label: 'Anggota' },
];

const upcomingAssignments = computed(() => props.schoolClass.assignments ?? []);
const classQuizzes = computed(() => props.schoolClass.quizzes ?? []);
const gradebookRows = computed(() => props.gradebook?.rows ?? []);
const formatDate = (value) => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const formatDueDate = (value) => {
    if (!value) return 'Tanpa tenggat';
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value));
};

const gradeCellLabel = (cell, item) => {
    if (!cell || cell.status === 'pending') return `- / ${item.max_score}`;
    if (cell.status === 'missing') return 'Terlambat / Missing';
    if (cell.status === 'submitted') return 'Diserahkan';
    return `${cell.score} / ${item.max_score}`;
};

const gradeCellClass = (cell) => {
    if (!cell || cell.status === 'pending') return 'text-slate-400';
    if (cell.status === 'missing') return 'font-bold text-rose-600';
    if (cell.status === 'submitted') return 'font-semibold text-amber-600';
    return 'font-bold text-slate-800';
};

const getStudentSubmission = (assignment) => {
    return assignment.submissions?.[0] || null;
};

const getStudentStatusBadge = (assignment) => {
    const sub = getStudentSubmission(assignment);
    if (!sub) {
        return {
            label: 'Belum Dikerjakan',
            class: 'bg-rose-50 text-rose-700 border border-rose-200/80',
            dotClass: 'bg-rose-500',
        };
    }
    if (sub.status === 'graded') {
        return {
            label: `Selesai & Dinilai (Nilai: ${sub.score}/${assignment.max_score})`,
            class: 'bg-blue-50 text-blue-700 border border-blue-200',
            dotClass: 'bg-blue-500',
        };
    }
    if (sub.status === 'late') {
        return {
            label: 'Sudah Mengumpulkan (Terlambat)',
            class: 'bg-amber-50 text-amber-700 border border-amber-200',
            dotClass: 'bg-amber-500',
        };
    }
    return {
        label: 'Sudah Mengumpulkan',
        class: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        dotClass: 'bg-emerald-500',
    };
};
</script>

<template>
    <Head :title="schoolClass.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('classes.index')" class="text-sm text-slate-400 hover:text-[#0E3BB0]">
                    Kelas
                </Link>
                <span class="text-slate-300">/</span>
                <h2 class="text-xl font-bold text-slate-800">{{ schoolClass.name }}</h2>
            </div>
        </template>

        <section class="mx-auto max-w-7xl">
            <!-- Header Banner Kelas -->
            <div class="overflow-hidden rounded-xl bg-gradient-to-r from-[#0E3BB0] to-[#1D4ED8] p-7 text-white shadow-sm">
                <div class="flex flex-col justify-between gap-4 sm:flex-row">
                    <div>
                        <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-bold tracking-wider">
                            {{ schoolClass.code }}
                        </span>
                        <h3 class="mt-4 text-2xl font-extrabold">{{ schoolClass.name }}</h3>
                        <p class="mt-1 text-blue-100">{{ schoolClass.subject }} · {{ schoolClass.academic_year }}</p>
                    </div>
                    <div class="text-sm text-blue-100">
                        <p>Pengajar</p>
                        <p class="mt-1 font-bold text-white">{{ schoolClass.teacher.name }}</p>
                    </div>
                </div>
            </div>

            <!-- Navigasi Tab -->
            <div class="mt-6 border-b border-slate-200">
                <nav class="flex gap-5 overflow-x-auto">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        :class="activeTab === tab.id ? 'border-[#0E3BB0] text-[#0B2E8A]' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        class="border-b-2 px-1 pb-3 text-sm font-bold transition"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <!-- Tab: Overview -->
            <div v-if="activeTab === 'overview'" class="mt-6 grid gap-6 lg:grid-cols-3">
                <div class="rounded-xl bg-white p-6 shadow-sm lg:col-span-2">
                    <h4 class="font-bold text-slate-800">Tentang kelas</h4>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">
                        {{ schoolClass.description || 'Pengajar belum menambahkan deskripsi.' }}
                    </p>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Anggota kelas</p>
                    <p class="mt-2 text-3xl font-extrabold text-slate-800">{{ schoolClass.students_count }}</p>
                    <button class="mt-3 text-sm font-bold text-[#0B2E8A]" @click="activeTab = 'members'">
                        Lihat anggota →
                    </button>
                </div>
            </div>

            <!-- Tab: Ringkasan Profiling Kelas -->
            <div v-if="activeTab === 'profiling'" class="mt-6 rounded-xl bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div>
                        <span class="text-xs font-black uppercase tracking-[0.18em] text-[#0E3BB0]">Analisis Siswa</span>
                        <h4 class="mt-2 text-xl font-extrabold text-slate-800">Ringkasan Profiling Kelas</h4>
                        <p class="mt-1 text-sm text-slate-500">Distribusi gaya belajar siswa berdasarkan data Profiling SMK Pesat.</p>
                    </div>
                    <span v-if="profilingSummary" class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-bold text-[#0B2E8A]">{{ profilingSummary.profiled_students }} / {{ profilingSummary.total_students }} terprofil</span>
                </div>

                <div v-if="profilingSummary?.profiled_students" class="mt-8 space-y-5">
                    <div v-for="item in profilingSummary.distribution" :key="item.key">
                        <div class="mb-2 flex items-center justify-between text-sm"><span class="font-bold capitalize text-slate-700">{{ item.label }}</span><span class="font-black text-slate-800">{{ item.percentage }}%</span></div>
                        <div class="h-3 overflow-hidden rounded-full bg-slate-100"><div :class="item.key === 'visual' ? 'bg-[#0E3BB0]' : item.key === 'auditori' ? 'bg-blue-500' : 'bg-emerald-500'" class="h-full rounded-full transition-all" :style="{ width: `${item.percentage}%` }" /></div>
                        <p class="mt-1 text-xs text-slate-400">{{ item.count }} siswa</p>
                    </div>
                </div>
                <div v-else class="mt-8 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center"><svg class="mx-auto h-9 w-9 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V5m0 14h16M8 16v-5m4 5V7m4 9v-8" /></svg><p class="mt-3 text-sm font-bold text-slate-700">Belum ada data profiling siswa</p><p class="mt-1 text-xs text-slate-500">Jalankan sinkronisasi Profiling API atau pastikan siswa sudah memiliki hasil analisis.</p></div>
            </div>

            <!-- Tab: Gradebook -->
            <div v-if="activeTab === 'grades'" class="mt-6 space-y-5">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
                    <div>
                        <span class="text-xs font-black uppercase tracking-[0.18em] text-[#0E3BB0]">Gradebook Kelas</span>
                        <h4 class="mt-2 text-xl font-extrabold text-slate-800">Nilai Siswa</h4>
                        <p class="mt-1 text-sm text-slate-500">Ringkasan nilai tugas dan kuis dalam satu tabel.</p>
                    </div>
                    <span class="text-xs font-semibold text-slate-400">{{ gradebook?.students_count ?? 0 }} siswa · {{ gradebook?.items?.length ?? 0 }} aktivitas</span>
                </div>

                <div v-if="gradebook?.items?.length && gradebookRows.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-max border-collapse text-left text-sm">
                            <thead class="bg-slate-50">
                                <tr class="border-b border-slate-200">
                                    <th class="sticky left-0 z-20 min-w-[230px] border-r border-slate-200 bg-slate-50 px-5 py-4 align-bottom">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500">Siswa</span>
                                    </th>
                                    <th v-for="item in gradebook.items" :key="item.key" class="min-w-[155px] px-4 py-4 align-bottom">
                                        <div class="max-w-[155px] truncate font-bold text-slate-800" :title="item.title">{{ item.title }}</div>
                                        <div class="mt-1 text-[11px] font-medium text-slate-400">{{ formatDueDate(item.due_at) }}</div>
                                        <div class="mt-1 text-[11px] font-bold text-[#0B2E8A]">out of {{ item.max_score }}</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-blue-100 bg-blue-50/70">
                                    <td class="sticky left-0 z-10 border-r border-blue-100 bg-blue-50/95 px-5 py-4 font-black text-[#0B2E8A]">Class Average</td>
                                    <td v-for="item in gradebook.items" :key="`average-${item.key}`" class="px-4 py-4 font-black text-[#0B2E8A]">
                                        {{ gradebook.averages[item.key] === null ? '-' : `${gradebook.averages[item.key]} / ${item.max_score}` }}
                                    </td>
                                </tr>
                                <tr v-for="row in gradebookRows" :key="row.student.id" class="border-b border-slate-100 last:border-0 hover:bg-slate-50/70">
                                    <td class="sticky left-0 z-10 border-r border-slate-100 bg-white px-5 py-4">
                                        <div class="font-bold text-slate-800">{{ row.student.name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ row.student.email }}</div>
                                    </td>
                                    <td v-for="item in gradebook.items" :key="`${row.student.id}-${item.key}`" class="px-4 py-4 align-middle">
                                        <span :class="gradeCellClass(row.grades[item.key])" class="whitespace-nowrap text-xs">
                                            {{ gradeCellLabel(row.grades[item.key], item) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap gap-x-5 gap-y-2 border-t border-slate-100 px-5 py-3 text-[11px] font-medium text-slate-500">
                        <span><strong class="text-slate-800">Angka</strong> sudah dinilai</span>
                        <span><strong class="text-amber-600">Diserahkan</strong> menunggu penilaian</span>
                        <span><strong class="text-rose-600">Terlambat / Missing</strong> melewati tenggat</span>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed border-slate-200 bg-white p-10 text-center shadow-sm">
                    <svg class="mx-auto h-10 w-10 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V5m0 14h16M8 16v-5m4 5V7m4 9v-8" /></svg>
                    <h5 class="mt-3 font-bold text-slate-800">Belum ada data nilai</h5>
                    <p class="mt-1 text-sm text-slate-500">Tambahkan tugas atau kuis untuk mulai menggunakan gradebook kelas.</p>
                </div>
            </div>

            <!-- Tab: Materi -->
            <div v-if="activeTab === 'materials'" class="mt-6 rounded-xl bg-white p-10 text-center shadow-sm">
                <h4 class="font-bold text-slate-800">Belum ada materi</h4>
                <p class="mt-1 text-sm text-slate-500">Materi kelas akan ditampilkan di sini.</p>
            </div>

            <!-- Tab: Tugas -->
            <div v-if="activeTab === 'assignments'" class="mt-6">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h4 class="font-bold text-slate-800">Daftar Tugas Kelas</h4>
                        <p class="text-xs text-slate-500">Total {{ upcomingAssignments.length }} tugas tersedia</p>
                    </div>
                    <Link
                        v-if="canCreateAssignment"
                        :href="route('classes.assignments.create', schoolClass.id)"
                        class="rounded-lg bg-[#0E3BB0] px-4 py-2 text-sm font-bold text-white transition hover:bg-[#0B2E8A]"
                    >
                        + Buat tugas
                    </Link>
                </div>

                <div v-if="upcomingAssignments.length" class="space-y-3">
                    <Link
                        v-for="assignment in upcomingAssignments"
                        :key="assignment.id"
                        :href="route('assignments.show', assignment.id)"
                        class="group flex flex-col justify-between gap-4 rounded-xl border border-slate-100 bg-white p-5 shadow-sm transition hover:border-blue-200 hover:shadow-md sm:flex-row sm:items-center"
                    >
                        <div class="space-y-1">
                            <h5 class="font-bold text-slate-800 group-hover:text-[#0E3BB0] transition">
                                {{ assignment.title }}
                            </h5>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                <span>Batas waktu: <strong class="text-slate-700">{{ formatDate(assignment.deadline) }}</strong></span>
                                <span>·</span>
                                <span>Maks. <strong>{{ assignment.max_score }} poin</strong></span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Indikator Pengumpulan untuk Guru / Admin -->
                            <div v-if="user.role === 'guru' || user.role === 'admin'">
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-blue-100 bg-blue-50/70 px-3 py-1.5 text-xs font-bold text-[#0B2E8A]">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                    <span>{{ assignment.submissions_count ?? 0 }} / {{ schoolClass.students_count }} Siswa Mengumpulkan</span>
                                </span>
                            </div>

                            <!-- Indikator Status Tugas di Sisi Siswa -->
                            <div v-else-if="user.role === 'siswa'">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold',
                                        getStudentStatusBadge(assignment).class
                                    ]"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="getStudentStatusBadge(assignment).dotClass"></span>
                                    {{ getStudentStatusBadge(assignment).label }}
                                </span>
                            </div>

                            <span class="hidden text-slate-300 group-hover:text-[#0E3BB0] transition sm:inline">
                                →
                            </span>
                        </div>
                    </Link>
                </div>

                <div v-else class="rounded-xl border border-slate-100 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                    Belum ada tugas untuk kelas ini.
                </div>
            </div>

            <!-- Tab: Game Kuis Interaktif -->
            <div v-if="activeTab === 'quizzes'" class="mt-6">
                <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h4 class="text-lg font-bold text-slate-800">Game Kuis Interaktif Kelas</h4>
                        <p class="text-xs text-slate-500">Uji pengetahuan dengan gameplay seru berhadiah skor dan leaderboard real-time.</p>
                    </div>
                    <Link
                        v-if="canCreateQuiz"
                        :href="route('classes.quizzes.create', schoolClass.id)"
                        class="inline-flex items-center gap-2 rounded-lg bg-[#0E3BB0] px-4 py-2 text-sm font-bold text-white transition hover:bg-[#0B2E8A]"
                    >
                        <span>+ Buat Kuis Baru</span>
                    </Link>
                </div>

                <div v-if="classQuizzes.length" class="grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="quiz in classQuizzes"
                        :key="quiz.id"
                        class="flex flex-col justify-between rounded-xl border border-slate-100 bg-white p-6 shadow-sm transition hover:border-blue-200 hover:shadow-md"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-[#0B2E8A]">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9" stroke-width="1.8" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 7v5l3 2" /></svg>
                                    {{ quiz.duration_minutes }} Menit
                                </span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                                    {{ quiz.questions_count ?? 0 }} Soal
                                </span>
                            </div>

                            <h5 class="mt-3 text-lg font-extrabold text-slate-800">
                                {{ quiz.title }}
                            </h5>
                            <p class="mt-1 line-clamp-2 text-xs text-slate-500">
                                {{ quiz.description || 'Kuis pilihan ganda interaktif untuk melatih pemahaman materi.' }}
                            </p>
                        </div>

                        <div class="mt-5 border-t border-slate-100 pt-4">
                            <!-- Sisi Guru: Statistik Attempt & Leaderboard Link -->
                            <div v-if="user.role === 'guru' || user.role === 'admin'" class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-500">
                                    {{ quiz.attempts_count ?? 0 }} Siswa Telah Mengerjakan
                                </span>
                                <Link
                                    v-if="quiz.quiz_mode !== 'assign'"
                                    :href="route('quizzes.host', quiz.id)"
                                    class="inline-flex items-center gap-1 rounded-lg bg-[#FFA800] px-2.5 py-1.5 text-xs font-bold text-slate-950 hover:bg-[#F59E0B]"
                                >
                                    Host
                                </Link>
                                <Link
                                    :href="route('quizzes.show', quiz.id)"
                                    class="inline-flex items-center gap-1 text-xs font-bold text-[#0E3BB0] hover:text-[#0B2E8A]"
                                >
                                    <span>Leaderboard & Detail</span>
                                    <span>→</span>
                                </Link>
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
                                        Belum Dikerjakan
                                    </span>
                                </div>

                                <Link
                                    :href="route('quizzes.play', quiz.id)"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#0E3BB0] px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#0B2E8A]"
                                >
                                    <span>{{ quiz.attempts?.[0] ? 'Main Lagi' : 'Mulai Main' }}</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-xl border border-slate-100 bg-white p-12 text-center text-sm text-slate-500 shadow-sm">
                    <svg class="mx-auto h-10 w-10 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 3h6l1 4H8l1-4ZM7 7h10l2 12H5L7 7Z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 11h6M10 15h4" /></svg>
                    <h5 class="mt-3 font-bold text-slate-800">Belum Ada Kuis di Kelas Ini</h5>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ canCreateQuiz ? 'Klik tombol "+ Buat Kuis Baru" di atas untuk menambahkan kuis pertama Anda.' : 'Pengajar belum membuat game kuis untuk kelas ini.' }}
                    </p>
                </div>
            </div>

            <!-- Tab: Anggota -->
            <div v-if="activeTab === 'members'" class="mt-6 rounded-xl bg-white shadow-sm">
                <div class="border-b border-slate-100 p-5">
                    <h4 class="font-bold text-slate-800">Anggota kelas</h4>
                </div>
                <div class="divide-y divide-slate-100">
                    <div v-for="student in schoolClass.students" :key="student.id" class="flex items-center gap-3 p-5">
                        <img v-if="student.avatar" :src="student.avatar" :alt="student.name" class="h-10 w-10 rounded-full object-cover" />
                        <span v-else class="grid h-10 w-10 place-items-center rounded-full bg-blue-100 text-sm font-bold text-[#0B2E8A]">
                            {{ student.name.slice(0, 1) }}
                        </span>
                        <div>
                            <p class="text-sm font-bold text-slate-800">{{ student.name }}</p>
                            <p class="text-xs text-slate-500">{{ student.email }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
