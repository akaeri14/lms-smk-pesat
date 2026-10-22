<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    role: { type: String, required: true },
    stats: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    recentSubmissions: { type: Array, default: () => [] },
    upcomingAssignments: { type: Array, default: () => [] },
    recentClasses: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const greeting = computed(() => props.role === 'guru'
    ? 'Kelola kelas dan pantau perkembangan siswa Anda.'
    : props.role === 'siswa'
        ? 'Lanjutkan perjalanan belajar dan selesaikan tugas tepat waktu.'
        : 'Pantau aktivitas dan perkembangan sistem LMS SMK Pesat.');

const formatDate = (value, withTime = false) => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        ...(withTime ? { timeStyle: 'short' } : {}),
    }).format(new Date(value));
};

const toneClasses = {
    orange: 'bg-orange-50 text-[#E95521] ring-orange-100',
    blue: 'bg-blue-50 text-blue-700 ring-blue-100',
    red: 'bg-rose-50 text-rose-700 ring-rose-100',
    purple: 'bg-violet-50 text-violet-700 ring-violet-100',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
};
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <div><h2 class="text-xl font-bold text-slate-800">Dashboard</h2><p class="mt-0.5 text-xs text-slate-500">Ringkasan aktivitas belajar LMS SMK Pesat.</p></div>
        </template>

        <section class="mx-auto max-w-7xl space-y-7">
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-[#FF6B35] to-[#F08A5D] p-6 text-white shadow-sm sm:p-8">
                <div class="relative z-10"><p class="text-sm font-medium text-orange-50">Selamat datang kembali,</p><h1 class="mt-1 text-2xl font-black sm:text-3xl">{{ user.name || 'Pengguna LMS' }} 👋</h1><p class="mt-2 text-sm text-orange-50">{{ greeting }}</p></div>
                <div class="absolute -right-8 -top-12 h-44 w-44 rounded-full border-[22px] border-white/10" /><div class="absolute -bottom-20 right-24 h-48 w-48 rounded-full border-[28px] border-white/10" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div v-for="stat in stats" :key="stat.label" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ stat.label }}</p><p class="mt-2 text-3xl font-black text-slate-800">{{ stat.value }}</p></div><div :class="[toneClasses[stat.tone] || toneClasses.orange, 'grid h-11 w-11 shrink-0 place-items-center rounded-xl ring-1']"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path v-if="stat.icon === 'quiz'" d="M14.752 11.168 11.555 9.036A1 1 0 0 0 10 9.868v4.264a1 1 0 0 0 1.555.832l3.197-2.132a1 1 0 0 0 0-1.664ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke-linecap="round" stroke-linejoin="round" /><path v-else-if="['students', 'users', 'teacher'].includes(stat.icon)" d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" stroke-linecap="round" stroke-linejoin="round" /><path v-else d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16ZM4 19h16v3H6.5A2.5 2.5 0 0 1 4 19Z" stroke-linecap="round" stroke-linejoin="round" /></svg></div></div>
                </div>
            </div>

            <template v-if="role === 'guru'">
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><div><h3 class="text-lg font-extrabold text-slate-800">Kelas Aktif</h3><p class="mt-1 text-xs text-slate-500">Kelas yang sedang Anda ampu.</p></div><Link :href="route('classes.index')" class="text-xs font-bold text-[#FF6B35]">Lihat semua →</Link></div><div v-if="classes.length" class="mt-5 divide-y divide-slate-100"><Link v-for="item in classes" :key="item.id" :href="route('classes.show', item.id)" class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0 hover:bg-orange-50/40"><div><p class="text-sm font-bold text-slate-800">{{ item.name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ item.subject }} · {{ item.code }}</p></div><span class="whitespace-nowrap text-xs font-semibold text-slate-500">{{ item.students_count }} siswa</span></Link></div><p v-else class="mt-6 rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">Belum ada kelas aktif.</p></div>
                    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><h3 class="text-lg font-extrabold text-slate-800">Perlu Diperiksa</h3><p class="mt-1 text-xs text-slate-500">Pengumpulan terbaru yang belum dinilai.</p><div v-if="recentSubmissions.length" class="mt-5 divide-y divide-slate-100"><Link v-for="item in recentSubmissions" :key="item.id" :href="route('assignments.show', item.assignment.id)" class="block py-3 first:pt-0 last:pb-0 hover:bg-orange-50/40"><div class="flex items-center justify-between gap-3"><p class="text-sm font-bold text-slate-800">{{ item.student?.name }}</p><span class="text-xs text-slate-400">{{ formatDate(item.submitted_at, true) }}</span></div><p class="mt-1 text-xs text-slate-500">{{ item.assignment?.title }} · {{ item.assignment?.school_class?.name }}</p></Link></div><p v-else class="mt-6 rounded-xl bg-emerald-50 p-5 text-center text-sm text-emerald-700">Semua pengumpulan sudah diperiksa. 🎉</p></div>
                </div>
            </template>

            <template v-else-if="role === 'siswa'">
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><div><h3 class="text-lg font-extrabold text-slate-800">Tugas Mendatang</h3><p class="mt-1 text-xs text-slate-500">Prioritas tugas yang perlu segera diselesaikan.</p></div><Link :href="route('assignments.index')" class="text-xs font-bold text-[#FF6B35]">Lihat semua →</Link></div><div v-if="upcomingAssignments.length" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><Link v-for="item in upcomingAssignments" :key="item.id" :href="route('assignments.show', item.id)" class="rounded-xl border border-slate-100 p-4 transition hover:border-orange-200 hover:bg-orange-50/30"><div class="flex items-start justify-between gap-2"><span class="rounded-full bg-orange-50 px-2 py-1 text-[10px] font-bold text-[#E95521]">{{ item.school_class?.name }}</span><span class="text-[11px] font-bold text-rose-600">{{ formatDate(item.deadline) }}</span></div><h4 class="mt-3 line-clamp-2 text-sm font-bold text-slate-800">{{ item.title }}</h4><p class="mt-2 text-xs text-slate-500">Maks. {{ item.max_score }} poin</p></Link></div><p v-else class="mt-6 rounded-xl bg-emerald-50 p-5 text-center text-sm text-emerald-700">Tidak ada tugas mendatang. Saatnya istirahat sejenak! ✨</p></div>
            </template>

            <template v-else>
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><div><h3 class="text-lg font-extrabold text-slate-800">Kelas Terbaru</h3><p class="mt-1 text-xs text-slate-500">Aktivitas kelas terbaru di sekolah.</p></div><Link :href="route('classes.index')" class="text-xs font-bold text-[#FF6B35]">Kelola kelas →</Link></div><div v-if="recentClasses.length" class="mt-5 overflow-x-auto"><table class="w-full min-w-[620px] text-left text-sm"><thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400"><tr><th class="pb-3 font-bold">Kelas</th><th class="pb-3 font-bold">Pengajar</th><th class="pb-3 font-bold">Siswa</th><th class="pb-3 font-bold">Status</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in recentClasses" :key="item.id"><td class="py-3"><Link :href="route('classes.show', item.id)" class="font-bold text-slate-800 hover:text-[#FF6B35]">{{ item.name }}</Link><p class="mt-0.5 text-xs text-slate-500">{{ item.subject }} · {{ item.academic_year }}</p></td><td class="py-3 text-slate-600">{{ item.teacher?.name || '-' }}</td><td class="py-3 text-slate-600">{{ item.students_count }}</td><td class="py-3"><span :class="item.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'" class="rounded-full px-2.5 py-1 text-[11px] font-bold">{{ item.status === 'active' ? 'Aktif' : 'Arsip' }}</span></td></tr></tbody></table></div><p v-else class="mt-6 rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">Belum ada kelas di sistem.</p></div>
            </template>
        </section>
    </AuthenticatedLayout>
</template>
