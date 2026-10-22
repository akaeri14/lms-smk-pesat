<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    assignments: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

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

const deleteAssignment = (assignment) => {
    if (!window.confirm(`Hapus tugas "${assignment.title}"? Data pengumpulan siswa juga akan ikut terhapus.`)) return;

    router.delete(route('assignments.destroy', assignment.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Tugas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold text-slate-800">Tugas</h2>
        </template>

        <section class="mx-auto max-w-5xl">
            <div class="mb-7">
                <h3 class="text-2xl font-bold text-slate-800">Daftar Tugas</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ user.role === 'guru' ? 'Kelola dan periksa tugas yang telah Anda buat untuk kelas.' : 'Pantau dan kerjakan semua tugas dari kelas yang Anda ikuti.' }}
                </p>
            </div>

            <div v-if="assignments.length" class="space-y-4">
                <Link
                    v-for="assignment in assignments"
                    :key="assignment.id"
                    :href="route('assignments.show', assignment.id)"
                    class="group flex flex-col justify-between gap-4 rounded-xl border border-slate-100 bg-white p-5 shadow-sm transition hover:border-orange-200 hover:shadow-md sm:flex-row sm:items-center"
                >
                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-orange-50 px-2.5 py-0.5 text-xs font-bold text-[#E95521]">
                                {{ assignment.school_class?.name }}
                            </span>
                            <span v-if="assignment.school_class?.subject" class="text-xs text-slate-400">
                                · {{ assignment.school_class.subject }}
                            </span>
                        </div>

                        <h4 class="text-base font-bold text-slate-800 group-hover:text-[#FF6B35] transition">
                            {{ assignment.title }}
                        </h4>

                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                            <span>Tenggat: <strong class="text-slate-700">{{ formatDate(assignment.deadline) }}</strong></span>
                            <span>·</span>
                            <span>Maks. <strong>{{ assignment.max_score }} poin</strong></span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Indikator Pengumpulan untuk Guru / Admin -->
                        <div v-if="user.role === 'guru' || user.role === 'admin'" class="flex items-center">
                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-orange-100 bg-orange-50/70 px-3 py-1.5 text-xs font-bold text-[#E95521]">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <span>{{ assignment.submissions_count ?? 0 }} / {{ assignment.school_class?.students_count ?? 0 }} Siswa Mengumpulkan</span>
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

                        <template v-if="user.role === 'guru' || user.role === 'admin'">
                            <Link
                                :href="route('assignments.edit', assignment.id)"
                                @click.stop
                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50 hover:text-[#FF6B35]"
                            >
                                Edit
                            </Link>
                            <button
                                type="button"
                                @click.stop.prevent="deleteAssignment(assignment)"
                                class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-100"
                            >
                                Hapus
                            </button>
                        </template>

                        <span class="hidden text-slate-300 group-hover:text-[#FF6B35] transition sm:inline">
                            →
                        </span>
                    </div>
                </Link>
            </div>

            <div v-else class="rounded-xl border border-slate-100 bg-white p-12 text-center shadow-sm">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h4 class="mt-4 font-bold text-slate-800">Tidak ada tugas</h4>
                <p class="mt-1 text-sm text-slate-500">
                    {{ user.role === 'guru' ? 'Anda belum membuat tugas untuk kelas manapun.' : 'Belum ada tugas yang diberikan untuk kelas Anda.' }}
                </p>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
