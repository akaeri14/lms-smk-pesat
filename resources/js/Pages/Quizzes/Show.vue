<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    quiz: {
        type: Object,
        required: true,
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    leaderboard: {
        type: Array,
        default: () => [],
    },
    myAttempt: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash ?? {});

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(dateString));
};

// Konfirmasi hapus kuis
const showDeleteConfirm = ref(false);
const isDeleting = ref(false);

const deleteQuiz = () => {
    isDeleting.value = true;
    router.delete(route('quizzes.destroy', props.quiz.id), {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteConfirm.value = false;
        },
    });
};
</script>

<template>
    <Head :title="quiz.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('classes.show', quiz.class_id)"
                    class="text-sm font-medium text-slate-400 hover:text-[#0E3BB0] transition"
                >
                    {{ quiz.school_class?.name || 'Kelas' }}
                </Link>
                <span class="text-slate-300">/</span>
                <span class="text-sm text-slate-400">Game Kuis</span>
                <span class="text-slate-300">/</span>
                <h2 class="max-w-xs truncate text-lg font-bold text-slate-800 sm:max-w-md">
                    {{ quiz.title }}
                </h2>
            </div>
        </template>

        <section class="mx-auto max-w-4xl space-y-6">
            <!-- Flash Banner -->
            <div
                v-if="flash.success"
                class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 shadow-sm"
            >
                <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ flash.success }}</span>
            </div>

            <!-- Header Kuis Card -->
            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-start">
                    <div class="space-y-3">
                        <span class="inline-block rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-[#0B2E8A]">
                            {{ quiz.school_class?.subject || 'Mata Pelajaran' }}
                        </span>
                        <h1 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">
                            {{ quiz.title }}
                        </h1>
                        <p class="text-sm text-slate-500">
                            Dibuat oleh <strong class="text-slate-700">{{ quiz.creator?.name || 'Pengajar' }}</strong>
                        </p>
                    </div>

                    <div class="w-full shrink-0 rounded-xl bg-slate-50 p-5 sm:w-64 space-y-3">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Durasi Game</span>
                            <p class="text-lg font-extrabold text-slate-800">{{ quiz.duration_minutes }} Menit</p>
                        </div>
                        <div class="border-t border-slate-200/60 pt-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Soal</span>
                            <p class="text-base font-extrabold text-slate-800">{{ quiz.questions_count }} Pertanyaan</p>
                        </div>
                    </div>
                </div>

                <div v-if="quiz.description" class="mt-6 border-t border-slate-100 pt-5">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Deskripsi Kuis</h4>
                    <p class="mt-2 text-sm text-slate-700 whitespace-pre-line">{{ quiz.description }}</p>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <Link
                        :href="route('quizzes.play', quiz.id)"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#0E3BB0] px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#0B2E8A]"
                    >
                        <span>Mainkan Game Kuis</span>
                    </Link>

                    <!-- Tombol Edit & Hapus Khusus Guru/Admin -->
                    <template v-if="canManage">
                        <Link
                            :href="route('quizzes.edit', quiz.id)"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-[#0E3BB0]"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>Edit Kuis</span>
                        </Link>
                        <button
                            type="button"
                            @click="showDeleteConfirm = true"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-100"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span>Hapus Kuis</span>
                        </button>
                    </template>

                    <Link
                        :href="route('classes.show', quiz.class_id)"
                        class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    >
                        Kembali ke Kelas
                    </Link>
                </div>
            </div>

            <!-- Leaderboard / Rekap Hasil Siswa -->
            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-xl font-extrabold text-slate-800">Papan Peringkat & Rekap Nilai</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Total {{ leaderboard.length }} pengerjaan tercatat dari siswa di kelas.
                        </p>
                    </div>
                </div>

                <div v-if="leaderboard.length" class="mt-4 divide-y divide-slate-100">
                    <div
                        v-for="(item, rank) in leaderboard"
                        :key="item.id"
                        class="flex items-center justify-between py-4"
                    >
                        <div class="flex items-center gap-3.5">
                            <span
                                :class="[
                                    'grid h-8 w-8 place-items-center rounded-xl text-xs font-black',
                                    rank === 0 ? 'bg-amber-100 text-amber-800 border border-amber-300' :
                                    rank === 1 ? 'bg-slate-200 text-slate-700 border border-slate-300' :
                                    rank === 2 ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'bg-slate-50 text-slate-500'
                                ]"
                            >
                                {{ rank + 1 }}
                            </span>

                            <div>
                                <p class="text-sm font-bold text-slate-800">{{ item.user?.name }}</p>
                                <p class="text-xs text-slate-400">
                                    {{ item.total_correct }} / {{ item.total_questions }} Benar · {{ formatDateTime(item.completed_at) }}
                                </p>
                            </div>
                        </div>

                        <div class="text-right">
                            <span class="text-lg font-black text-[#0E3BB0]">{{ item.score }}</span>
                            <span class="text-xs font-bold text-slate-400"> / 100</span>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-6 rounded-xl bg-slate-50 p-8 text-center text-xs text-slate-500">
                    Belum ada siswa yang menyelesaikan kuis ini.
                </div>
            </div>
        </section>
    </AuthenticatedLayout>

    <!-- Modal Konfirmasi Hapus Kuis -->
    <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showDeleteConfirm = false" />
        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl z-10 text-center">
            <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-full bg-red-100">
                <svg class="h-7 w-7 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800">Hapus Kuis Ini?</h3>
            <p class="mt-2 text-sm text-slate-600">
                Kuis <strong>{{ quiz.title }}</strong> beserta semua soal dan data pengerjaan siswa akan dihapus secara permanen.
            </p>
            <div class="mt-6 flex items-center justify-center gap-3">
                <button
                    type="button"
                    @click="showDeleteConfirm = false"
                    class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                >
                    Batal
                </button>
                <button
                    type="button"
                    :disabled="isDeleting"
                    @click="deleteQuiz"
                    class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 disabled:opacity-50"
                >
                    {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus Kuis' }}
                </button>
            </div>
        </div>
    </div>
</template>
