<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
                        @click="showImportModal = true"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50/80 px-4 py-2 text-xs font-bold text-emerald-800 shadow-sm transition hover:bg-emerald-100"
                    >
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Import Excel</span>
                    </button>

                    <!-- Tombol Buat Kuis Baru (Manual) -->
                    <Link
                        :href="route('quizzes.create')"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-[#0E3BB0] px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#0B2E8A]"
                    >
                        <span>+ Buat Kuis Baru</span>
                    </Link>
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
                        @click="showImportModal = true"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-100"
                    >
                        <span>Import Excel</span>
                    </button>
                    <Link
                        :href="route('quizzes.create')"
                        class="rounded-xl bg-[#0E3BB0] px-4 py-2 text-xs font-bold text-white hover:bg-[#0B2E8A]"
                    >
                        + Buat Kuis Baru
                    </Link>
                </div>
            </div>
        </section>

        <!-- Modal Import Kuis dari Excel -->
        <ImportQuizModal
            :show="showImportModal"
            :classes="classes"
            @close="showImportModal = false"
        />
    </AuthenticatedLayout>
</template>
