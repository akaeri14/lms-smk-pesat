<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    schoolClass: {
        type: Object,
        required: true,
    },
    canManage: Boolean,
    canCreateAssignment: Boolean,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const activeTab = ref('overview');
const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'materials', label: 'Materi' },
    { id: 'assignments', label: 'Tugas' },
    { id: 'members', label: 'Anggota' },
];

const upcomingAssignments = computed(() => props.schoolClass.assignments ?? []);
const formatDate = (value) => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
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
                <Link :href="route('classes.index')" class="text-sm text-slate-400 hover:text-[#FF6B35]">
                    Kelas
                </Link>
                <span class="text-slate-300">/</span>
                <h2 class="text-xl font-bold text-slate-800">{{ schoolClass.name }}</h2>
            </div>
        </template>

        <section class="mx-auto max-w-7xl">
            <!-- Header Banner Kelas -->
            <div class="overflow-hidden rounded-xl bg-gradient-to-br from-[#FF6B35] to-[#FF5722] p-7 text-white shadow-sm">
                <div class="flex flex-col justify-between gap-4 sm:flex-row">
                    <div>
                        <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-bold tracking-wider">
                            {{ schoolClass.code }}
                        </span>
                        <h3 class="mt-4 text-2xl font-extrabold">{{ schoolClass.name }}</h3>
                        <p class="mt-1 text-orange-100">{{ schoolClass.subject }} · {{ schoolClass.academic_year }}</p>
                    </div>
                    <div class="text-sm text-orange-100">
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
                        :class="activeTab === tab.id ? 'border-[#FF6B35] text-[#E95521]' : 'border-transparent text-slate-500 hover:text-slate-700'"
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
                    <button class="mt-3 text-sm font-bold text-[#E95521]" @click="activeTab = 'members'">
                        Lihat anggota →
                    </button>
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
                        class="rounded-lg bg-[#FF6B35] px-4 py-2 text-sm font-bold text-white transition hover:bg-[#E95521]"
                    >
                        + Buat tugas
                    </Link>
                </div>

                <div v-if="upcomingAssignments.length" class="space-y-3">
                    <Link
                        v-for="assignment in upcomingAssignments"
                        :key="assignment.id"
                        :href="route('assignments.show', assignment.id)"
                        class="group flex flex-col justify-between gap-4 rounded-xl border border-slate-100 bg-white p-5 shadow-sm transition hover:border-orange-200 hover:shadow-md sm:flex-row sm:items-center"
                    >
                        <div class="space-y-1">
                            <h5 class="font-bold text-slate-800 group-hover:text-[#FF6B35] transition">
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
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-orange-100 bg-orange-50/70 px-3 py-1.5 text-xs font-bold text-[#E95521]">
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

                            <span class="hidden text-slate-300 group-hover:text-[#FF6B35] transition sm:inline">
                                →
                            </span>
                        </div>
                    </Link>
                </div>

                <div v-else class="rounded-xl border border-slate-100 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                    Belum ada tugas untuk kelas ini.
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
                        <span v-else class="grid h-10 w-10 place-items-center rounded-full bg-orange-100 text-sm font-bold text-[#E95521]">
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
