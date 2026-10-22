<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    profiling: {
        type: Object,
        default: () => ({ data: {}, synced_at: null }),
    },
});

const page = usePage();
const profilingData = computed(() => page.props.auth?.user?.profiling_data || {});
const profilingSyncedAt = computed(() => page.props.auth?.user?.profiling_synced_at || null);

const findProfilingValue = (keys, fallback = '-') => {
    for (const key of keys) {
        const value = key.split('.').reduce((current, part) => current?.[part], profilingData.value);
        if (value !== undefined && value !== null && value !== '') return value;
    }
    return fallback;
};

const formatProfilingValue = (value) => Array.isArray(value) ? value.join(', ') : value;
const learningStyle = computed(() => formatProfilingValue(findProfilingValue([
    'gaya_belajar', 'gayaBelajar', 'learning_style', 'learningStyle', 'analysis.gaya_belajar', 'hasil.gaya_belajar',
])));
const interests = computed(() => formatProfilingValue(findProfilingValue([
    'minat_bakat', 'minatBakat', 'interests', 'talents', 'analysis.minat_bakat', 'hasil.minat_bakat',
])));
const careerRecommendation = computed(() => formatProfilingValue(findProfilingValue([
    'rekomendasi_karir', 'rekomendasiKarir', 'career_recommendation', 'jurusan', 'recommendation', 'analysis.rekomendasi_karir', 'hasil.rekomendasi_karir',
])));
const formattedSyncDate = computed(() => profilingSyncedAt.value
    ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'full', timeStyle: 'short' }).format(new Date(profilingSyncedAt.value))
    : 'Belum pernah disinkronkan');
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Profile
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
                    <div class="flex flex-col justify-between gap-4 bg-gradient-to-r from-blue-50 to-white p-6 sm:flex-row sm:items-center sm:p-8">
                        <div>
                            <span class="text-xs font-black uppercase tracking-[0.18em] text-[#0E3BB0]">Profiling Siswa</span>
                            <h3 class="mt-2 text-xl font-extrabold text-slate-800">Hasil Analisis Profiling SMK Pesat</h3>
                            <p class="mt-1 text-sm text-slate-500">Kenali potensi diri dan temukan cara belajar yang paling sesuai.</p>
                        </div>
                        <span class="whitespace-nowrap rounded-full bg-blue-100 px-3 py-1.5 text-xs font-bold text-[#0B2E8A]">{{ learningStyle }}</span>
                    </div>
                    <div class="grid gap-4 border-t border-slate-100 p-6 sm:grid-cols-3 sm:p-8">
                        <div class="rounded-xl bg-blue-50/70 p-4"><p class="text-xs font-bold uppercase tracking-wide text-[#0B2E8A]">Gaya Belajar</p><p class="mt-2 text-lg font-extrabold capitalize text-slate-800">{{ learningStyle }}</p><p class="mt-1 text-xs text-slate-500">Visual, auditori, atau kinestetik</p></div>
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Minat & Bakat</p><p class="mt-2 text-sm font-bold leading-6 text-slate-800">{{ interests }}</p></div>
                        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Rekomendasi Karir / Jurusan</p><p class="mt-2 text-sm font-bold leading-6 text-slate-800">{{ careerRecommendation }}</p></div>
                    </div>
                    <p class="border-t border-slate-100 px-6 py-3 text-xs text-slate-400 sm:px-8">Terakhir disinkronkan: <span class="font-semibold text-slate-600">{{ formattedSyncDate }}</span></p>
                </section>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
