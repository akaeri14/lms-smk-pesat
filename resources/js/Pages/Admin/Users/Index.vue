<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    users:   { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

// =============================================
// Filter & Pencarian
// =============================================
const search = ref(props.filters.search || '');
const roleFilter = ref(props.filters.role || '');

let searchTimeout = null;
watch([search, roleFilter], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('admin.users.index'), { search: search.value, role: roleFilter.value }, {
            preserveState: true,
            replace: true,
        });
    }, 350);
});

const resetFilters = () => {
    search.value = '';
    roleFilter.value = '';
};

const isSyncing = ref(false);
const syncProfiling = () => {
    isSyncing.value = true;
    router.post(route('admin.users.sync-profiling'), {}, {
        preserveScroll: true,
        onFinish: () => { isSyncing.value = false; },
    });
};

// =============================================
// Modal Tambah User Baru
// =============================================
const showAddModal = ref(false);

const addForm = useForm({
    name:     '',
    email:    '',
    password: '',
    role:     'siswa',
    phone:    '',
});

const submitAdd = () => {
    addForm.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

// =============================================
// Modal Edit User
// =============================================
const showEditModal = ref(false);
const editingUser = ref(null);

const editForm = useForm({
    name:     '',
    email:    '',
    password: '',
    role:     '',
    phone:    '',
});

const openEditModal = (user) => {
    editingUser.value = user;
    editForm.name     = user.name;
    editForm.email    = user.email;
    editForm.password = '';
    editForm.role     = user.role;
    editForm.phone    = user.phone || '';
    editForm.clearErrors();
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    editingUser.value   = null;
    editForm.reset();
};

const submitEdit = () => {
    editForm.patch(route('admin.users.update', editingUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeEditModal();
        },
    });
};

// =============================================
// Konfirmasi Hapus User
// =============================================
const confirmDeleteUser = ref(null);

const openDeleteConfirm = (user) => {
    confirmDeleteUser.value = user;
};

const cancelDelete = () => {
    confirmDeleteUser.value = null;
};

const submitDelete = () => {
    if (!confirmDeleteUser.value) return;
    router.delete(route('admin.users.destroy', confirmDeleteUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            confirmDeleteUser.value = null;
        },
    });
};

// =============================================
// Helpers
// =============================================
const roleBadge = (role) => {
    if (role === 'admin') return { class: 'bg-red-100 text-red-800 border border-red-200', label: 'Admin' };
    if (role === 'guru')  return { class: 'bg-blue-100 text-blue-800 border border-blue-200', label: 'Guru' };
    return { class: 'bg-emerald-100 text-emerald-800 border border-emerald-200', label: 'Siswa' };
};

const formatDate = (d) => {
    if (!d) return '-';
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(d));
};
</script>

<template>
    <Head title="Manajemen Pengguna" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between w-full">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">Manajemen Pengguna</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Kelola semua akun pengguna dalam sistem LMS SMK Pesat.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        :disabled="isSyncing"
                        @click="syncProfiling"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-bold text-[#0B2E8A] shadow-sm transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg class="h-4 w-4" :class="isSyncing ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M5.1 9A7 7 0 0 1 17 5.3L20 8M19 15a7 7 0 0 1-11.9 3.7L4 16" /></svg>
                        <span>{{ isSyncing ? 'Sinkronisasi...' : 'Sync API Profiling' }}</span>
                    </button>
                    <button
                        type="button"
                        @click="showAddModal = true"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-[#0E3BB0] px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#0B2E8A]"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        <span>Tambah Pengguna</span>
                    </button>
                </div>
            </div>
        </template>

        <section class="mx-auto max-w-7xl space-y-6">
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
            <div
                v-if="flash.error"
                class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800 shadow-sm"
            >
                <span>{{ flash.error }}</span>
            </div>

            <!-- Filter Bar -->
            <div class="flex flex-col gap-3 rounded-xl border border-slate-100 bg-white p-4 shadow-sm sm:flex-row sm:items-center">
                <!-- Search -->
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari nama atau email pengguna..."
                        class="block w-full rounded-xl border-slate-300 pl-9 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                    />
                </div>

                <!-- Role Filter -->
                <select
                    v-model="roleFilter"
                    class="rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0] sm:w-40"
                >
                    <option value="">Semua Role</option>
                    <option value="admin">Admin</option>
                    <option value="guru">Guru</option>
                    <option value="siswa">Siswa</option>
                </select>

                <!-- Reset -->
                <button
                    v-if="search || roleFilter"
                    type="button"
                    @click="resetFilters"
                    class="text-xs font-bold text-slate-400 hover:text-slate-700 whitespace-nowrap"
                >
                    Reset Filter ✕
                </button>
            </div>

            <!-- Tabel Pengguna -->
            <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                            <th class="px-6 py-4">Nama</th>
                            <th class="px-4 py-4 hidden sm:table-cell">Email</th>
                            <th class="px-4 py-4">Role</th>
                            <th class="px-4 py-4 hidden lg:table-cell">Bergabung</th>
                            <th class="px-4 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="group transition hover:bg-slate-50/60"
                        >
                            <!-- Nama + Avatar -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-blue-100 text-sm font-extrabold text-[#0B2E8A]">
                                        {{ user.name.slice(0, 1).toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800">{{ user.name }}</p>
                                        <p class="text-xs text-slate-500 sm:hidden">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Email -->
                            <td class="px-4 py-4 text-slate-600 hidden sm:table-cell">{{ user.email }}</td>

                            <!-- Role Badge -->
                            <td class="px-4 py-4">
                                <span
                                    :class="['inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold', roleBadge(user.role).class]"
                                >
                                    {{ roleBadge(user.role).label }}
                                </span>
                            </td>

                            <!-- Bergabung -->
                            <td class="px-4 py-4 text-xs text-slate-500 hidden lg:table-cell">
                                {{ formatDate(user.created_at) }}
                            </td>

                            <!-- Aksi -->
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        @click="openEditModal(user)"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-[#0E3BB0]"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span>Edit</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="openDeleteConfirm(user)"
                                        class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700 shadow-sm transition hover:bg-red-100"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        <span>Hapus</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty state -->
                        <tr v-if="!users.data.length">
                            <td colspan="5" class="px-6 py-16 text-center text-sm text-slate-400">
                                <svg class="mx-auto mb-3 h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Tidak ada pengguna yang ditemukan.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="users.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-6 py-4 text-xs text-slate-500">
                    <span>
                        Menampilkan {{ users.from }}–{{ users.to }} dari {{ users.total }} pengguna
                    </span>
                    <div class="flex items-center gap-1">
                        <Link
                            v-if="users.prev_page_url"
                            :href="users.prev_page_url"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 font-bold hover:bg-slate-50"
                        >
                            ← Sebelumnya
                        </Link>
                        <Link
                            v-if="users.next_page_url"
                            :href="users.next_page_url"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 font-bold hover:bg-slate-50"
                        >
                            Berikutnya →
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>

    <!-- ======================================= -->
    <!-- MODAL: Tambah Pengguna Baru              -->
    <!-- ======================================= -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAddModal = false" />
        <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl sm:p-8 space-y-5 z-10">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-800">Tambah Pengguna Baru</h3>
                <button type="button" @click="showAddModal = false" class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100">✕</button>
            </div>

            <form @submit.prevent="submitAdd" class="space-y-4">
                <!-- Nama -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="addForm.name"
                        type="text"
                        class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        required
                    />
                    <InputError :message="addForm.errors.name" class="mt-1" />
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="addForm.email"
                        type="email"
                        class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        required
                    />
                    <InputError :message="addForm.errors.email" class="mt-1" />
                </div>

                <!-- Password & Phone -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Kata Sandi <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="addForm.password"
                            type="password"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            required
                            minlength="8"
                        />
                        <InputError :message="addForm.errors.password" class="mt-1" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">No. Telepon</label>
                        <input
                            v-model="addForm.phone"
                            type="text"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        />
                        <InputError :message="addForm.errors.phone" class="mt-1" />
                    </div>
                </div>

                <!-- Role -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Peran (Role) <span class="text-red-500">*</span>
                    </label>
                    <div class="mt-1.5 flex gap-2">
                        <button
                            v-for="r in ['siswa', 'guru', 'admin']"
                            :key="r"
                            type="button"
                            @click="addForm.role = r"
                            :class="[
                                'flex-1 rounded-xl border py-2 text-xs font-bold capitalize transition',
                                addForm.role === r
                                    ? 'border-[#0E3BB0] bg-blue-50 text-[#0B2E8A]'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
                            ]"
                        >
                            {{ r.charAt(0).toUpperCase() + r.slice(1) }}
                        </button>
                    </div>
                    <InputError :message="addForm.errors.role" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="showAddModal = false" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700">
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="addForm.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#0E3BB0] px-6 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#0B2E8A] disabled:opacity-50"
                    >
                        <svg v-if="addForm.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                        </svg>
                        <span>{{ addForm.processing ? 'Menyimpan...' : 'Tambah Pengguna' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================= -->
    <!-- MODAL: Edit Pengguna                     -->
    <!-- ======================================= -->
    <div v-if="showEditModal && editingUser" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeEditModal" />
        <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl sm:p-8 space-y-5 z-10">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-800">Edit Pengguna</h3>
                <button type="button" @click="closeEditModal" class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100">✕</button>
            </div>

            <form @submit.prevent="submitEdit" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="editForm.name"
                        type="text"
                        class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        required
                    />
                    <InputError :message="editForm.errors.name" class="mt-1" />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="editForm.email"
                        type="email"
                        class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        required
                    />
                    <InputError :message="editForm.errors.email" class="mt-1" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Kata Sandi Baru <span class="font-normal text-slate-400">(Kosongkan jika tidak diubah)</span>
                        </label>
                        <input
                            v-model="editForm.password"
                            type="password"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                            minlength="8"
                        />
                        <InputError :message="editForm.errors.password" class="mt-1" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">No. Telepon</label>
                        <input
                            v-model="editForm.phone"
                            type="text"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]"
                        />
                        <InputError :message="editForm.errors.phone" class="mt-1" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Peran (Role) <span class="text-red-500">*</span>
                    </label>
                    <div class="mt-1.5 flex gap-2">
                        <button
                            v-for="r in ['siswa', 'guru', 'admin']"
                            :key="r"
                            type="button"
                            @click="editForm.role = r"
                            :class="[
                                'flex-1 rounded-xl border py-2 text-xs font-bold capitalize transition',
                                editForm.role === r
                                    ? 'border-[#0E3BB0] bg-blue-50 text-[#0B2E8A]'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
                            ]"
                        >
                            {{ r.charAt(0).toUpperCase() + r.slice(1) }}
                        </button>
                    </div>
                    <InputError :message="editForm.errors.role" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="closeEditModal" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700">
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="editForm.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#0E3BB0] px-6 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#0B2E8A] disabled:opacity-50"
                    >
                        <svg v-if="editForm.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                        </svg>
                        <span>{{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================= -->
    <!-- DIALOG: Konfirmasi Hapus User            -->
    <!-- ======================================= -->
    <div v-if="confirmDeleteUser" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="cancelDelete" />
        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl z-10">
            <div class="text-center">
                <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-full bg-red-100">
                    <svg class="h-7 w-7 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800">Hapus Pengguna?</h3>
                <p class="mt-2 text-sm text-slate-600">
                    Akun <strong>{{ confirmDeleteUser.name }}</strong> akan dihapus secara permanen dari sistem dan tidak dapat dikembalikan.
                </p>
            </div>
            <div class="mt-6 flex items-center justify-center gap-3">
                <button type="button" @click="cancelDelete" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50">
                    Batal
                </button>
                <button type="button" @click="submitDelete" class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700">
                    Ya, Hapus
                </button>
            </div>
        </div>
    </div>
</template>
