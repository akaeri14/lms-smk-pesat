<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    baseUrl: { type: String, required: true },
    tokens: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const showTokenModal = ref(false);
const toast = ref('');
const apiToken = computed(() => props.tokens.find((token) => token.kind === 'api'));
const ssoToken = computed(() => props.tokens.find((token) => token.kind === 'sso'));
const tokenCards = computed(() => [
    { kind: 'api', label: 'API Token', token: apiToken.value },
    { kind: 'sso', label: 'SSO Secret Token', token: ssoToken.value },
]);

const tokenForm = useForm({
    name: 'Web Profiling SMK Pesat',
    type: 'api',
});

const createToken = () => {
    tokenForm.post(route('admin.api-docs.tokens.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showTokenModal.value = false;
            tokenForm.reset('name');
        },
    });
};

const copyToken = async (token) => {
    if (!token?.plain_token) return;

    await navigator.clipboard.writeText(token.plain_token);
    toast.value = 'Token berhasil disalin!';
    window.setTimeout(() => {
        toast.value = '';
    }, 2500);
};

const revokeToken = (token) => {
    if (!token) return;

    const routeName = token.kind === 'sso'
        ? 'admin.api-docs.tokens.sso.destroy'
        : 'admin.api-docs.tokens.api.destroy';

    router.delete(route(routeName, token.id), { preserveScroll: true });
};

const endpoints = [
    {
        method: 'GET',
        path: '/api/v1/classes',
        title: 'Kelas & Anggota',
        description: 'Mengambil daftar kelas beserta pengajar dan siswa yang terdaftar.',
        response: `{
  "data": [{
    "id": 2,
    "code": "CEDEFP",
    "name": "XII TKJ",
    "subject": "Jaringan Komputer",
    "teacher": { "id": 9, "name": "Budi Santoso", "email": "budi@smkpesat.sch.id" },
    "students_count": 24,
    "students": [{ "id": 21, "name": "Siswa Pesat", "email": "siswa@smkpesat.sch.id" }]
  }]
}`,
    },
    {
        method: 'GET',
        path: '/api/v1/classes/{class_id}/assignments',
        title: 'Tugas & Tenggat',
        description: 'Mengambil daftar tugas dalam kelas termasuk deadline dan nilai maksimal.',
        response: `{
  "data": [{
    "id": 41,
    "title": "Praktik HTML Dasar",
    "deadline": "2026-10-30T23:59:00+07:00",
    "max_score": 100,
    "submissions_count": 18,
    "creator": { "id": 9, "name": "Budi Santoso" }
  }]
}`,
    },
    {
        method: 'GET',
        path: '/api/v1/assignments/{id}/submissions',
        title: 'Pengumpulan & Nilai Tugas',
        description: 'Mengambil status pengerjaan, nilai, waktu pengumpulan, dan feedback guru.',
        response: `{
  "data": {
    "assignment": { "id": 41, "max_score": 100 },
    "submissions": [{
      "student": { "id": 21, "name": "Siswa Pesat", "email": "siswa@smkpesat.sch.id" },
      "status": "graded",
      "score": 85,
      "submitted_at": "2026-10-29T13:20:00+07:00",
      "teacher_feedback": "Baik, tambahkan validasi form."
    }]
  }
}`,
    },
    {
        method: 'GET',
        path: '/api/v1/classes/{class_id}/grades',
        title: 'Gradebook / Nilai',
        description: 'Mengambil matriks rekap nilai tugas dan kuis untuk seluruh siswa kelas.',
        response: `{
  "data": {
    "class": { "id": 2, "name": "XII TKJ" },
    "items": [{ "key": "assignment-41", "title": "Praktik HTML Dasar", "max_score": 100 }],
    "rows": [{
      "student": { "id": 21, "name": "Siswa Pesat", "email": "siswa@smkpesat.sch.id" },
      "grades": { "assignment-41": { "score": 85, "status": "graded" } }
    }]
  }
}`,
    },
    {
        method: 'GET',
        path: '/api/v1/students',
        title: 'Siswa & Profil',
        description: 'Mengambil data siswa terdaftar beserta data profiling yang sudah disinkronkan.',
        response: `{
  "data": [{
    "id": 21,
    "name": "Siswa Pesat",
    "email": "siswa@smkpesat.sch.id",
    "profiling_external_id": "PROF-001",
    "profiling_data": { "gaya_belajar": "visual" },
    "profiling_synced_at": "2026-10-22T10:00:00+07:00"
  }]
}`,
    },
];
</script>

<template>
    <Head title="API Documentation" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-medium text-slate-400">Integrasi Sistem</p>
                <h2 class="text-xl font-bold text-slate-800">API Documentation</h2>
            </div>
        </template>

        <section class="mx-auto max-w-7xl space-y-6">
            <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-[#0E3BB0] to-[#1D4ED8] p-6 text-white shadow-sm sm:p-8">
                <div class="max-w-3xl">
                    <span class="inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-wider">XPRO Integration</span>
                    <h1 class="mt-4 text-2xl font-black sm:text-3xl">LMS Data API & SSO</h1>
                    <p class="mt-3 text-sm leading-6 text-blue-100">Dokumentasi resmi untuk tim pengembang API Profiling SMK Pesat dalam membaca data kelas, tugas, nilai, siswa, dan menghubungkan login lintas aplikasi.</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="space-y-6">
                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-extrabold text-slate-800">Konfigurasi Request</h3>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Base URL</p><code class="mt-2 block break-all text-sm font-bold text-[#0E3BB0]">{{ baseUrl }}</code></div>
                            <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Header</p><code class="mt-2 block text-sm font-bold text-slate-700">Authorization: Bearer {token}</code></div>
                        </div>
                        <pre class="mt-4 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs leading-6 text-slate-200"><code>Accept: application/json
Content-Type: application/json
Authorization: Bearer &lt;PERSONAL_ACCESS_TOKEN&gt;</code></pre>
                        <p class="mt-3 text-xs leading-5 text-slate-500">Semua endpoint mengembalikan JSON. Simpan token dengan aman dan jangan menaruhnya di source code publik atau log aplikasi.</p>
                    </section>

                    <section class="space-y-4">
                        <div><h3 class="text-lg font-extrabold text-slate-800">Endpoint LMS Data Integration</h3><p class="mt-1 text-sm text-slate-500">Gunakan token dengan ability <code class="font-bold text-[#0E3BB0]">api:read</code>.</p></div>
                        <article v-for="endpoint in endpoints" :key="endpoint.path" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex flex-col gap-2 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-center gap-3"><span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-black text-[#0E3BB0]">{{ endpoint.method }}</span><code class="break-all text-sm font-bold text-slate-800">{{ endpoint.path }}</code></div><span class="text-xs font-bold text-slate-400">{{ endpoint.title }}</span></div>
                            <div class="p-5"><p class="text-sm leading-6 text-slate-600">{{ endpoint.description }}</p><p class="mt-4 text-xs font-black uppercase tracking-wide text-slate-400">Contoh Response</p><pre class="mt-2 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs leading-5 text-emerald-300"><code>{{ endpoint.response }}</code></pre></div>
                        </article>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <span class="text-xs font-black uppercase tracking-[0.18em] text-[#FFA800]">Single Sign-On</span>
                        <h3 class="mt-2 text-lg font-extrabold text-slate-800">Alur SSO Profiling ke LMS</h3>
                        <ol class="mt-5 space-y-4 text-sm leading-6 text-slate-600">
                            <li><strong class="text-slate-800">1. Generate token:</strong> Admin membuat token bertipe <code>sso</code> di panel API Documentation. Token berlaku 10 menit dan hanya dapat digunakan sekali.</li>
                            <li><strong class="text-slate-800">2. Redirect user:</strong> Aplikasi Profiling mengarahkan browser ke <code class="break-all text-[#0E3BB0]">{{ baseUrl }}/sso/authorize?token={sso_token}</code>.</li>
                            <li><strong class="text-slate-800">3. Verifikasi server:</strong> LMS memvalidasi token Sanctum, ability <code>sso:login</code>, masa berlaku, lalu membuat sesi login user terkait.</li>
                            <li><strong class="text-slate-800">4. Consume token:</strong> Token dihapus setelah berhasil digunakan dan user diarahkan ke Dashboard LMS.</li>
                        </ol>
                        <div class="mt-5 rounded-xl border border-[#FFA800]/30 bg-[#FFA800]/10 p-4 text-xs leading-5 text-slate-700"><strong>Catatan keamanan:</strong> Kirim token hanya melalui HTTPS, jangan menaruh token di URL yang disimpan permanen, dan gunakan token SSO khusus satu kali.</div>
                    </section>
                </div>

                <aside class="space-y-5">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3"><div><h3 class="font-extrabold text-slate-800">Token Management</h3><p class="mt-1 text-xs leading-5 text-slate-500">Buat token API atau token SSO untuk integrasi resmi.</p></div><button type="button" class="rounded-lg bg-[#0E3BB0] px-3 py-2 text-xs font-bold text-white hover:bg-[#0B2E8A]" @click="showTokenModal = true">Buat Token</button></div>
                        <p v-if="flash.success" class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">{{ flash.success }}</p>
                        <div class="mt-5 space-y-3">
                            <div v-for="card in tokenCards" :key="card.kind" class="rounded-xl border border-slate-100 p-3">
                                <div class="flex items-center justify-between gap-2"><div><p class="text-xs font-black uppercase tracking-wide text-slate-500">{{ card.label }}</p><p class="mt-1 truncate text-xs font-bold text-slate-700">{{ card.token?.name || 'Belum dibuat' }}</p></div><button v-if="card.token" type="button" class="text-[11px] font-bold text-rose-600 hover:text-rose-800" @click="revokeToken(card.token)">Cabut</button></div>
                                <div v-if="card.token?.plain_token" class="mt-3 flex items-center gap-2"><code class="min-w-0 flex-1 truncate rounded-lg bg-slate-50 px-2.5 py-2 text-[11px] text-slate-700" :title="card.token.plain_token">{{ card.token.plain_token }}</code><button type="button" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-[#FFA800] px-2.5 py-2 text-[11px] font-bold text-slate-950 hover:bg-[#E69A00]" @click="copyToken(card.token)"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="9" y="9" width="11" height="11" rx="2" stroke-width="1.8" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>Copy Token</button></div>
                                <p v-else-if="card.token" class="mt-3 rounded-lg bg-slate-50 px-2.5 py-2 text-[11px] text-slate-500">Plaintext tidak tersimpan. Cabut token ini lalu buat token baru.</p>
                                <p v-else class="mt-3 rounded-lg bg-slate-50 px-2.5 py-2 text-[11px] font-semibold text-slate-500">Belum dibuat. Klik tombol Buat Token untuk membuatnya.</p>
                                <p v-if="card.token" class="mt-2 text-[11px] text-slate-400">{{ card.token.abilities.join(', ') }} · {{ card.token.expires_at ? new Date(card.token.expires_at).toLocaleDateString('id-ID') : 'Tanpa kedaluwarsa' }}</p>
                            </div>
                        </div>
                    </section>
                    <section class="rounded-2xl border border-blue-100 bg-blue-50/60 p-5"><h3 class="font-extrabold text-[#0B2E8A]">Ringkasan SSO</h3><p class="mt-2 text-xs leading-5 text-slate-600">Token SSO dibuat khusus untuk satu redirect login dan otomatis dicabut setelah digunakan.</p><code class="mt-3 block break-all rounded-lg bg-white p-3 text-[11px] text-[#0E3BB0]">GET /sso/authorize?token={sso_token}</code></section>
                </aside>
            </div>
        </section>

        <div v-if="toast" class="fixed right-5 top-5 z-[60] rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white shadow-xl">{{ toast }}</div>

        <div v-if="showTokenModal" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" @click.self="showTokenModal = false">
            <form class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" @submit.prevent="createToken"><div class="flex items-center justify-between"><h3 class="text-lg font-extrabold text-slate-800">Buat Access Token</h3><button type="button" class="text-slate-400" @click="showTokenModal = false">×</button></div><label class="mt-5 block text-sm font-bold text-slate-700">Nama token<input v-model="tokenForm.name" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]" required /></label><label class="mt-4 block text-sm font-bold text-slate-700">Jenis token<select v-model="tokenForm.type" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"><option value="api">API Read Token — 90 hari</option><option value="sso">SSO One-Time Token — 10 menit</option></select></label><div class="mt-6 flex justify-end gap-3"><button type="button" class="rounded-xl px-4 py-2 text-sm font-bold text-slate-500" @click="showTokenModal = false">Batal</button><button type="submit" class="rounded-xl bg-[#0E3BB0] px-5 py-2 text-sm font-bold text-white hover:bg-[#0B2E8A]" :disabled="tokenForm.processing">Generate Token</button></div></form>
        </div>
    </AuthenticatedLayout>
</template>
