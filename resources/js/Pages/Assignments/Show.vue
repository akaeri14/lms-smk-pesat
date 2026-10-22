<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    assignment: {
        type: Object,
        required: true,
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    submission: {
        type: Object,
        default: null,
    },
    submissions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash ?? {});

const deadlineFormatted = computed(() => {
    if (!props.assignment.deadline) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'full',
        timeStyle: 'short',
    }).format(new Date(props.assignment.deadline));
});

const isPastDeadline = computed(() => {
    if (!props.assignment.deadline) return false;
    return new Date() > new Date(props.assignment.deadline);
});

const attachmentName = computed(() => {
    return props.assignment.file_path?.split('/').pop();
});

const submissionFileName = computed(() => {
    return props.submission?.file_path?.split('/').pop();
});

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(dateString));
};

// ==========================================
// Form Pengumpulan Tugas Siswa
// ==========================================
const clientError = ref('');
const fileInput = ref(null);
const showReSubmitForm = ref(!props.submission);

const studentForm = useForm({
    file: null,
    notes: props.submission?.submission_text || '',
});

const onFileChange = (event) => {
    clientError.value = '';
    const file = event.target.files[0];
    if (!file) {
        studentForm.file = null;
        return;
    }

    // Validasi ukuran file di sisi client (10MB = 10 * 1024 * 1024 bytes)
    const maxSizeBytes = 10 * 1024 * 1024;
    if (file.size > maxSizeBytes) {
        clientError.value = 'Ukuran file terlalu besar. Maksimal 10MB (10.240 KB).';
        event.target.value = '';
        studentForm.file = null;
        return;
    }

    studentForm.file = file;
};

const submitAssignment = () => {
    clientError.value = '';

    if (!studentForm.file) {
        clientError.value = 'Silakan pilih file tugas untuk diunggah.';
        return;
    }

    studentForm.post(route('assignments.submissions.store', props.assignment.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (fileInput.value) {
                fileInput.value.value = '';
            }
            studentForm.reset('file');
            showReSubmitForm.value = false;
        },
    });
};

// ==========================================
// Form Penilaian Tugas Guru
// ==========================================
const gradingSubmissionId = ref(null);

const gradeForm = useForm({
    score: '',
    teacher_feedback: '',
});

const openGradingForm = (item) => {
    if (gradingSubmissionId.value === item.id) {
        gradingSubmissionId.value = null;
        return;
    }
    gradingSubmissionId.value = item.id;
    gradeForm.score = item.score !== null ? item.score : '';
    gradeForm.teacher_feedback = item.teacher_feedback || '';
    gradeForm.clearErrors();
};

const cancelGrading = () => {
    gradingSubmissionId.value = null;
    gradeForm.reset();
    gradeForm.clearErrors();
};

const submitGrade = (submissionId) => {
    gradeForm.patch(route('submissions.grade', submissionId), {
        preserveScroll: true,
        onSuccess: () => {
            gradingSubmissionId.value = null;
            gradeForm.reset();
        },
    });
};

// ==========================================
// Preview File Viewer (Modal)
// ==========================================
const previewModalOpen = ref(false);
const previewFilePath = ref('');
const previewStudentName = ref('');
const previewStatus = ref('');

const openPreview = (item) => {
    previewFilePath.value = item.file_path;
    previewStudentName.value = item.student?.name || 'Siswa';
    previewStatus.value = item.status || 'submitted';
    previewModalOpen.value = true;
};

const openSelfPreview = () => {
    if (!props.submission?.file_path) return;
    previewFilePath.value = props.submission.file_path;
    previewStudentName.value = user.value.name;
    previewStatus.value = props.submission.status || 'submitted';
    previewModalOpen.value = true;
};

const closePreview = () => {
    previewModalOpen.value = false;
    previewFilePath.value = '';
    previewStudentName.value = '';
};

const getFileExtension = (path) => {
    if (!path) return '';
    return path.split('.').pop()?.toLowerCase() || '';
};

const isPdf = computed(() => getFileExtension(previewFilePath.value) === 'pdf');
const isImage = computed(() => ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'].includes(getFileExtension(previewFilePath.value)));
const previewFileName = computed(() => previewFilePath.value.split('/').pop() || 'berkas');

// ==========================================
// Hapus Tugas (Guru / Admin)
// ==========================================
const showDeleteConfirm = ref(false);
const isDeleting = ref(false);

const deleteAssignment = () => {
    isDeleting.value = true;
    router.delete(route('assignments.destroy', props.assignment.id), {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteConfirm.value = false;
        },
    });
};
</script>

<template>
    <Head :title="assignment.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('classes.show', assignment.class_id)"
                    class="text-sm font-medium text-slate-400 transition hover:text-[#FF6B35]"
                >
                    {{ assignment.school_class?.name || 'Kelas' }}
                </Link>
                <span class="text-slate-300">/</span>
                <span class="text-sm text-slate-400">Tugas</span>
                <span class="text-slate-300">/</span>
                <h2 class="max-w-xs truncate text-lg font-bold text-slate-800 sm:max-w-md">
                    {{ assignment.title }}
                </h2>
            </div>
        </template>

        <section class="mx-auto max-w-4xl space-y-6">
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

            <!-- Detail Tugas Card -->
            <div class="rounded-xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-start">
                    <div class="space-y-3">
                        <span class="inline-block rounded-full bg-orange-50 px-3 py-1 text-xs font-bold text-[#E95521]">
                            {{ assignment.school_class?.subject || 'Mata Pelajaran' }}
                        </span>
                        <h1 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">
                            {{ assignment.title }}
                        </h1>
                        <p class="text-sm text-slate-500">
                            Dibuat oleh <strong class="text-slate-700">{{ assignment.creator?.name || 'Pengajar' }}</strong>
                        </p>
                    </div>

                    <!-- Kotak Info Deadline & Nilai -->
                    <div class="w-full shrink-0 rounded-xl bg-slate-50 p-5 sm:w-72">
                        <div class="mb-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Batas Pengumpulan</span>
                            <p class="mt-1 text-sm font-bold" :class="isPastDeadline ? 'text-red-500' : 'text-[#E95521]'">
                                {{ deadlineFormatted }}
                            </p>
                            <span
                                v-if="isPastDeadline"
                                class="mt-1 inline-block rounded bg-red-100 px-2 py-0.5 text-xs font-bold text-red-600"
                            >
                                Batas waktu telah lewat
                            </span>
                        </div>
                        <div class="border-t border-slate-200/60 pt-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Nilai Maksimum</span>
                            <p class="mt-1 text-base font-extrabold text-slate-800">
                                {{ assignment.max_score }} Poin
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Instruksi Tugas -->
                <div class="mt-8 border-t border-slate-100 pt-7">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">Instruksi Tugas</h3>
                    <div class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700">
                        {{ assignment.description }}
                    </div>
                </div>

                <!-- Lampiran Tugas dari Guru (jika ada) -->
                <div v-if="assignment.file_path" class="mt-6">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Lampiran Materi Tugas</h4>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <a
                            :href="`/storage/${assignment.file_path}`"
                            target="_blank"
                            class="inline-flex items-center gap-2 rounded-xl border border-orange-100 bg-orange-50/70 px-4 py-2.5 text-sm font-semibold text-[#E95521] transition hover:bg-orange-100"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Lihat Lampiran: {{ attachmentName }}</span>
                        </a>
                        <a
                            :href="`/storage/${assignment.file_path}`"
                            download
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-100"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>Unduh</span>
                        </a>
                    </div>
                </div>

                <!-- Aksi pengelolaan tugas untuk Guru/Admin -->
                <div v-if="canManage" class="mt-7 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
                    <Link
                        :href="route('assignments.edit', assignment.id)"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-[#FF6B35]"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span>Edit Tugas</span>
                    </Link>
                    <button
                        type="button"
                        @click="showDeleteConfirm = true"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-100"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Hapus Tugas</span>
                    </button>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- AREA KHUSUS SISWA: Status & Form Pengumpulan Tugas            -->
            <!-- ============================================================== -->
            <div
                v-if="user?.role === 'siswa'"
                class="rounded-xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-5">
                    <div>
                        <h3 class="text-xl font-extrabold text-slate-800">Pengumpulan Tugas Siswa</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Unggah berkas jawaban tugas Anda dalam format PDF, Word, atau ZIP.
                        </p>
                    </div>

                    <!-- Status Pill -->
                    <div v-if="submission">
                        <span
                            v-if="submission.status === 'graded'"
                            class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-800"
                        >
                            ✓ Dinilai: {{ submission.score }}/{{ assignment.max_score }}
                        </span>
                        <span
                            v-else-if="submission.status === 'late'"
                            class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800"
                        >
                            ⚠ Dikumpulkan (Terlambat)
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700"
                        >
                            ✓ Sudah Dikumpulkan
                        </span>
                    </div>
                </div>

                <!-- Jika Siswa Sudah Pernah Mengumpulkan -->
                <div v-if="submission" class="mt-6 space-y-4">
                    <div
                        :class="[
                            'rounded-xl border p-5',
                            submission.status === 'graded'
                                ? 'border-blue-200 bg-blue-50/40'
                                : submission.status === 'late'
                                ? 'border-amber-200 bg-amber-50/60'
                                : 'border-emerald-200 bg-emerald-50/50'
                        ]"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-bold text-slate-800">
                                    Status:
                                    <span
                                        :class="
                                            submission.status === 'graded'
                                                ? 'text-blue-700'
                                                : submission.status === 'late'
                                                ? 'text-amber-700'
                                                : 'text-emerald-700'
                                        "
                                    >
                                        {{
                                            submission.status === 'graded'
                                                ? 'Selesai & Dinilai'
                                                : submission.status === 'late'
                                                ? 'Dikumpulkan Terlambat'
                                                : 'Tugas Sudah Terkirim'
                                        }}
                                    </span>
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Waktu kirim: {{ formatDateTime(submission.submitted_at) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    @click="openSelfPreview"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-[#FF6B35]"
                                >
                                    <svg class="h-4 w-4 text-[#FF6B35]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Lihat Berkas</span>
                                </button>

                                <a
                                    v-if="submission.file_path"
                                    :href="`/storage/${submission.file_path}`"
                                    download
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50"
                                >
                                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span>Unduh</span>
                                </a>
                            </div>
                        </div>

                        <!-- Nilai & Masukan Guru jika sudah dinilai -->
                        <div v-if="submission.status === 'graded'" class="mt-4 rounded-lg border border-blue-200/80 bg-white p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Nilai yang Diperoleh</span>
                                <span class="text-lg font-extrabold text-blue-700">
                                    {{ submission.score }} / {{ assignment.max_score }} Poin
                                </span>
                            </div>
                            <div v-if="submission.teacher_feedback" class="mt-3 border-t border-slate-100 pt-2.5">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Masukan / Catatan Guru:</span>
                                <p class="mt-1 text-sm text-slate-800 whitespace-pre-line">{{ submission.teacher_feedback }}</p>
                            </div>
                        </div>

                        <!-- Catatan Siswa jika ada -->
                        <div v-if="submission.submission_text" class="mt-4 border-t border-slate-200/60 pt-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Catatan Anda:</span>
                            <p class="mt-1 text-xs text-slate-700 italic">"{{ submission.submission_text }}"</p>
                        </div>
                    </div>

                    <!-- Tombol untuk membuka form kirim ulang -->
                    <div class="pt-2">
                        <button
                            type="button"
                            @click="showReSubmitForm = !showReSubmitForm"
                            class="text-xs font-bold text-[#FF6B35] underline hover:text-[#E95521]"
                        >
                            {{ showReSubmitForm ? 'Tutup form perbaruan tugas ▲' : 'Perbarui atau kirim ulang tugas ▼' }}
                        </button>
                    </div>
                </div>

                <!-- Form Pengumpulan Tugas -->
                <form
                    v-if="showReSubmitForm || !submission"
                    @submit.prevent="submitAssignment"
                    class="mt-6 space-y-5"
                >
                    <!-- Input File -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700">
                            Unggah Berkas Jawaban
                            <span class="text-xs font-normal text-slate-400">
                                (Format: PDF, Word, ZIP · Maks. 10MB)
                            </span>
                        </label>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".pdf,.doc,.docx,.zip"
                            @change="onFileChange"
                            class="mt-2 block w-full rounded-xl border border-slate-300 text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-orange-50 file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-[#FF6B35] transition hover:file:bg-orange-100 focus:outline-none"
                            required
                        />
                        <p class="mt-1 text-xs text-slate-400">
                            Pastikan ukuran berkas tidak melebihi 10MB agar proses upload berjalan optimal.
                        </p>
                        <InputError :message="clientError || studentForm.errors.file" class="mt-1.5" />
                    </div>

                    <!-- Input Catatan Siswa -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700">
                            Catatan Tambahan
                            <span class="text-xs font-normal text-slate-400">(Opsional)</span>
                        </label>
                        <textarea
                            v-model="studentForm.notes"
                            rows="3"
                            class="mt-2 block w-full rounded-xl border-slate-300 text-sm text-slate-800 placeholder-slate-400 focus:border-[#FF6B35] focus:ring-[#FF6B35]"
                            placeholder="Tuliskan keterangan tambahan atau catatan untuk pengajar..."
                        ></textarea>
                        <InputError :message="studentForm.errors.notes || studentForm.errors.submission_text" class="mt-1.5" />
                    </div>

                    <!-- Action Button -->
                    <div class="flex items-center gap-3 pt-2">
                        <button
                            type="submit"
                            :disabled="studentForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#FF6B35] px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#E95521] disabled:opacity-50"
                        >
                            <svg
                                v-if="studentForm.processing"
                                class="h-4 w-4 animate-spin text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ studentForm.processing ? 'Mengirim...' : (submission ? 'Perbarui Pengumpulan Tugas' : 'Kirim Tugas') }}</span>
                        </button>

                        <button
                            v-if="submission && showReSubmitForm"
                            type="button"
                            @click="showReSubmitForm = false"
                            class="px-4 py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-700"
                        >
                            Batal
                        </button>
                    </div>
                </form>
            </div>

            <!-- ============================================================== -->
            <!-- AREA PENGAJAR / ADMIN: Rekap Pengumpulan & Pemeriksaan Tugas   -->
            <!-- ============================================================== -->
            <div
                v-if="canManage"
                class="rounded-xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8"
            >
                <div class="flex flex-col justify-between gap-2 border-b border-slate-100 pb-5 sm:flex-row sm:items-center">
                    <div>
                        <h3 class="text-xl font-extrabold text-slate-800">Pemeriksaan Tugas Siswa</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Total <strong>{{ submissions.length }}</strong> siswa telah mengumpulkan tugas ini.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            Nilai Maks: {{ assignment.max_score }}
                        </span>
                    </div>
                </div>

                <div v-if="submissions.length" class="mt-4 divide-y divide-slate-100">
                    <div
                        v-for="item in submissions"
                        :key="item.id"
                        class="py-5"
                    >
                        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                            <!-- Informasi Siswa & Waktu Kirim -->
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <span class="text-base font-bold text-slate-900">{{ item.student?.name }}</span>
                                    <span class="text-xs text-slate-400">({{ item.student?.email }})</span>
                                    <span
                                        :class="[
                                            'rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase',
                                            item.status === 'graded'
                                                ? 'bg-blue-100 text-blue-800'
                                                : item.status === 'late'
                                                ? 'bg-amber-100 text-amber-800'
                                                : 'bg-emerald-100 text-emerald-800'
                                        ]"
                                    >
                                        {{
                                            item.status === 'graded'
                                                ? 'Sudah Dinilai'
                                                : item.status === 'late'
                                                ? 'Terlambat'
                                                : 'Terkirim'
                                        }}
                                    </span>
                                </div>

                                <p class="text-xs text-slate-500">
                                    Dikumpulkan pada {{ formatDateTime(item.submitted_at) }}
                                </p>

                                <div v-if="item.submission_text" class="mt-2 rounded-lg bg-slate-50 p-3 text-xs text-slate-700">
                                    <span class="font-bold text-slate-500">Catatan Siswa:</span>
                                    <p class="mt-0.5 italic">"{{ item.submission_text }}"</p>
                                </div>

                                <!-- Ringkasan Nilai & Masukan Guru jika sudah dinilai -->
                                <div v-if="item.status === 'graded' && gradingSubmissionId !== item.id" class="mt-2.5 flex flex-wrap items-center gap-3 text-xs">
                                    <span class="rounded-md bg-blue-50 px-2.5 py-1 font-bold text-blue-700 border border-blue-200/80">
                                        Nilai: {{ item.score }} / {{ assignment.max_score }}
                                    </span>
                                    <span v-if="item.teacher_feedback" class="text-slate-600">
                                        Masukan: <em>"{{ item.teacher_feedback }}"</em>
                                    </span>
                                </div>
                            </div>

                            <!-- Tombol Aksi: Preview Berkas & Beri Nilai -->
                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Tombol Preview / Lihat File Langsung di Browser -->
                                <button
                                    v-if="item.file_path"
                                    type="button"
                                    @click="openPreview(item)"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-orange-200 bg-orange-50/80 px-3 py-1.5 text-xs font-bold text-[#E95521] transition hover:bg-orange-100"
                                    title="Lihat file di browser tanpa paksa unduh"
                                >
                                    <svg class="h-3.5 w-3.5 text-[#FF6B35]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Lihat Berkas</span>
                                </button>

                                <!-- Tombol Unduh Berkas -->
                                <a
                                    v-if="item.file_path"
                                    :href="`/storage/${item.file_path}`"
                                    download
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                                    title="Unduh berkas ke perangkat"
                                >
                                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span>Unduh</span>
                                </a>

                                <!-- Tombol Penilaian (Beri / Ubah Nilai) -->
                                <button
                                    type="button"
                                    @click="openGradingForm(item)"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-[#FF6B35] bg-[#FF6B35] px-3 py-1.5 text-xs font-bold text-white transition hover:bg-[#E95521]"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    <span>{{ item.status === 'graded' ? 'Ubah Nilai' : 'Beri Nilai' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Panel Form Penilaian Guru (Expandable Inline) -->
                        <div
                            v-if="gradingSubmissionId === item.id"
                            class="mt-4 rounded-xl border border-orange-200 bg-orange-50/40 p-5 transition"
                        >
                            <h4 class="text-sm font-bold text-slate-800">
                                Form Penilaian: {{ item.student?.name }}
                            </h4>

                            <form @submit.prevent="submitGrade(item.id)" class="mt-4 space-y-4">
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700">
                                            Nilai (0 - {{ assignment.max_score }}) <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="gradeForm.score"
                                            type="number"
                                            min="0"
                                            :max="assignment.max_score"
                                            placeholder="Contoh: 85"
                                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-[#FF6B35] focus:ring-[#FF6B35]"
                                            required
                                        />
                                        <InputError :message="gradeForm.errors.score" class="mt-1" />
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-bold text-slate-700">
                                            Masukan / Catatan Guru <span class="text-slate-400 font-normal">(Opsional)</span>
                                        </label>
                                        <textarea
                                            v-model="gradeForm.teacher_feedback"
                                            rows="2"
                                            placeholder="Tuliskan catatan evaluasi atau saran perbaikan untuk siswa..."
                                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-[#FF6B35] focus:ring-[#FF6B35]"
                                        ></textarea>
                                        <InputError :message="gradeForm.errors.teacher_feedback" class="mt-1" />
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 pt-1">
                                    <button
                                        type="submit"
                                        :disabled="gradeForm.processing"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#FF6B35] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#E95521] disabled:opacity-50"
                                    >
                                        <svg
                                            v-if="gradeForm.processing"
                                            class="h-3.5 w-3.5 animate-spin"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                        >
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        <span>{{ gradeForm.processing ? 'Menyimpan...' : 'Simpan Nilai' }}</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="cancelGrading"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-6 rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">
                    Belum ada siswa yang mengumpulkan tugas ini.
                </div>
            </div>
        </section>

        <!-- ============================================================== -->
        <!-- MODAL FILE PREVIEW VIEWER (PDF / Gambar / Dokumen)              -->
        <!-- ============================================================== -->
        <div
            v-if="previewModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
        >
            <!-- Backdrop -->
            <div
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
                @click="closePreview"
            ></div>

            <!-- Modal Content -->
            <div class="relative flex max-h-[92vh] w-full max-w-5xl flex-col rounded-2xl bg-white shadow-2xl overflow-hidden z-10">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                    <div class="flex items-center gap-3 truncate">
                        <div class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-orange-100 text-[#E95521]">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="truncate">
                            <h3 class="truncate text-base font-bold text-slate-800">
                                Berkas Tugas: {{ previewStudentName }}
                            </h3>
                            <p class="truncate text-xs text-slate-500">{{ previewFileName }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a
                            :href="`/storage/${previewFilePath}`"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
                            title="Buka file langsung di tab baru"
                        >
                            <span>Tab Baru</span>
                            <span class="text-xs">↗</span>
                        </a>

                        <a
                            :href="`/storage/${previewFilePath}`"
                            download
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
                            title="Unduh file"
                        >
                            <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>Unduh</span>
                        </a>

                        <button
                            type="button"
                            @click="closePreview"
                            class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-200 hover:text-slate-600 transition"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <!-- Modal Body: Viewer -->
                <div class="relative flex-1 overflow-auto bg-slate-100 p-4 min-h-[50vh]">
                    <!-- Preview PDF -->
                    <iframe
                        v-if="isPdf"
                        :src="`/storage/${previewFilePath}#toolbar=1&navpanes=0`"
                        class="h-[70vh] w-full rounded-xl border border-slate-200 bg-white shadow-inner"
                        title="Viewer PDF Berkas Siswa"
                    ></iframe>

                    <!-- Preview Gambar (JPG / PNG / WebP) -->
                    <div v-else-if="isImage" class="flex h-[70vh] items-center justify-center">
                        <img
                            :src="`/storage/${previewFilePath}`"
                            alt="Preview Berkas"
                            class="max-h-full max-w-full rounded-xl object-contain shadow-sm"
                        />
                    </div>

                    <!-- Dokumen Format Lain (Word .docx / .doc / .zip) -->
                    <div v-else class="flex h-[55vh] flex-col items-center justify-center p-8 text-center">
                        <div class="grid h-16 w-16 place-items-center rounded-2xl bg-orange-100 text-[#E95521]">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h4 class="mt-4 text-lg font-bold text-slate-800">
                            Berkas Dokumen ({{ getFileExtension(previewFilePath).toUpperCase() }})
                        </h4>
                        <p class="mt-2 max-w-md text-sm text-slate-500">
                            Format berkas ini tidak dapat dipreview secara langsung di dalam peramban. Anda dapat membuka atau mengunduhnya untuk memeriksa tugas.
                        </p>
                        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                            <a
                                :href="`/storage/${previewFilePath}`"
                                target="_blank"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50"
                            >
                                <span>Buka di Tab Baru ↗</span>
                            </a>
                            <a
                                :href="`/storage/${previewFilePath}`"
                                download
                                class="inline-flex items-center gap-2 rounded-xl bg-[#FF6B35] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#E95521]"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>Unduh Berkas</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between border-t border-slate-100 bg-white px-6 py-3.5">
                    <span class="text-xs text-slate-400">
                        LMS SMK Pesat · Penampil Berkas Tugas
                    </span>
                    <button
                        type="button"
                        @click="closePreview"
                        class="rounded-lg bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
