<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({ open: { type: Boolean, default: false } });
const emit = defineEmits(['close']);

const page = usePage();
const user = computed(() => page.props.auth?.user);

const navigation = [
    { label: 'Dashboard', route: 'dashboard', icon: 'M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10.5Z' },
    { label: 'Kelas Saya', route: 'classes.index', icon: 'M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Z M4 19h16v3H6.5A2.5 2.5 0 0 1 4 19Z' },
    { label: 'Tugas', route: 'assignments.index', icon: 'M9 5h6m-3-2v4m-6 1h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a1 1 0 0 1-2-2V10a2 2 0 0 1 2-2Z' },
    { label: 'Game Kuis', route: 'quizzes.index', icon: 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Kehadiran', icon: 'M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z' },
];

const adminNavigation = [
    { label: 'Manajemen User', route: 'admin.users.index', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
];

const isActive = (item) => item.route && route().current(item.route);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden" @click="emit('close')" />
    <aside :class="open ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:static lg:translate-x-0">
        <div class="flex h-20 items-center justify-between border-b border-slate-100 px-6">
            <Link :href="route('dashboard')" class="flex items-center gap-3" @click="emit('close')">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#FF6B35] text-lg font-black text-white">P</span>
                <span><span class="block text-sm font-extrabold tracking-tight text-slate-800">SMK PESAT</span><span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-[#FF6B35]">Learning Space</span></span>
            </Link>
            <button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 lg:hidden" type="button" aria-label="Tutup menu" @click="emit('close')"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 py-7">
            <!-- Menu Utama -->
            <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Menu utama</p>
            <div class="space-y-1">
                <component
                    :is="item.route ? Link : 'span'"
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.route ? route(item.route) : undefined"
                    :class="[
                        isActive(item) ? 'border-[#FF6B35] bg-orange-50 text-[#E95521]' : 'border-transparent text-slate-500 hover:bg-slate-50 hover:text-slate-800',
                        !item.route ? 'cursor-not-allowed opacity-50' : ''
                    ]"
                    class="flex items-center gap-3 border-l-2 px-3 py-3 text-sm font-semibold transition"
                    @click="item.route && emit('close')"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path :d="item.icon" stroke-linecap="round" stroke-linejoin="round" /></svg>{{ item.label }}
                </component>
            </div>

            <!-- Menu Admin -->
            <template v-if="user?.role === 'admin'">
                <p class="mb-3 mt-6 px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-red-400">Administrasi</p>
                <div class="space-y-1">
                    <Link
                        v-for="item in adminNavigation"
                        :key="item.label"
                        :href="route(item.route)"
                        :class="isActive(item) ? 'border-red-500 bg-red-50 text-red-700' : 'border-transparent text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                        class="flex items-center gap-3 border-l-2 px-3 py-3 text-sm font-semibold transition"
                        @click="emit('close')"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path :d="item.icon" stroke-linecap="round" stroke-linejoin="round" /></svg>{{ item.label }}
                    </Link>
                </div>
            </template>
        </nav>

        <div class="m-4 rounded-xl bg-slate-50 p-4 text-xs leading-5 text-slate-500"><p class="font-bold text-slate-700">Belajar lebih terarah.</p><p class="mt-1">Kelola kelas dan aktivitas belajar Anda dalam satu tempat.</p></div>
    </aside>
</template>
