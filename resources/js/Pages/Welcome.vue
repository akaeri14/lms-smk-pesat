<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    canLogin: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
});

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
</script>

<template>
    <Head title="SMK Pesat Learning Space" />

    <div class="min-h-screen overflow-hidden bg-slate-50 text-slate-800 selection:bg-blue-200 selection:text-blue-950">
        <header class="relative z-10 border-b border-slate-200/70 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8 lg:px-10">
                <Link href="/" class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-[#0E3BB0] text-lg font-black text-white shadow-lg shadow-blue-200">P</span>
                    <span><span class="block text-sm font-black tracking-tight text-slate-900 sm:text-base">SMK Pesat</span><span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-[#0E3BB0]">Learning Space</span></span>
                </Link>

                <nav class="flex items-center gap-2 sm:gap-3">
                    <template v-if="isAuthenticated">
                        <Link :href="route('dashboard')" class="inline-flex items-center gap-2 rounded-xl bg-[#0E3BB0] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#0B2E8A] sm:px-5 sm:text-sm">
                            Ke Dashboard <span aria-hidden="true">→</span>
                        </Link>
                    </template>
                    <template v-else-if="canLogin">
                        <Link :href="route('login')" class="rounded-xl px-3 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-blue-50 hover:text-[#0B2E8A] sm:px-4 sm:text-sm">Masuk</Link>
                        <Link v-if="canRegister" :href="route('register')" class="rounded-xl bg-[#0E3BB0] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#0B2E8A] sm:px-5 sm:text-sm">Daftar</Link>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <section class="relative mx-auto max-w-7xl px-5 pb-16 pt-14 sm:px-8 sm:pt-20 lg:px-10 lg:pb-24 lg:pt-24">
                <div class="absolute -left-32 top-16 h-72 w-72 rounded-full bg-blue-200/30 blur-3xl" />
                <div class="absolute -right-32 bottom-8 h-80 w-80 rounded-full bg-amber-100/50 blur-3xl" />
                <div class="relative grid items-center gap-12 lg:grid-cols-[1.02fr_.98fr] lg:gap-16">
                    <div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3.5 py-2 text-xs font-bold text-[#0B2E8A]"><span class="h-2 w-2 rounded-full bg-[#0E3BB0]" /> Ruang belajar digital SMK Pesat</span>
                        <h1 class="mt-6 max-w-2xl text-4xl font-black leading-[1.08] tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">Sistem Manajemen Pembelajaran <span class="text-[#0E3BB0]">Terpadu</span> SMK Pesat</h1>
                        <p class="mt-6 max-w-xl text-base leading-7 text-slate-600 sm:text-lg">Belajar lebih terarah dengan kelas digital, kuis gamifikasi, pengumpulan tugas, dan kolaborasi guru-siswa dalam satu ruang belajar modern.</p>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link :href="isAuthenticated ? route('dashboard') : route('login')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0E3BB0] px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-200 transition hover:-translate-y-0.5 hover:bg-[#0B2E8A]">Mulai Belajar <span aria-hidden="true">→</span></Link>
                            <Link :href="isAuthenticated ? route('classes.index') : route('login')" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-6 py-3.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-blue-300 hover:text-[#0B2E8A]">Jelajahi Kelas <span aria-hidden="true">⌄</span></Link>
                        </div>
                        <div class="mt-9 flex flex-wrap items-center gap-x-6 gap-y-3 text-xs font-semibold text-slate-500"><span class="flex items-center gap-2"><span class="text-base text-[#0E3BB0]">✓</span> Akses kapan saja</span><span class="flex items-center gap-2"><span class="text-base text-[#0E3BB0]">✓</span> Terintegrasi untuk sekolah</span></div>
                    </div>

                    <div class="relative mx-auto w-full max-w-xl lg:ml-auto">
                        <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-br from-blue-200/70 to-amber-100/30 blur-2xl" />
                        <div class="relative overflow-hidden rounded-[1.75rem] border-8 border-white bg-white shadow-2xl shadow-slate-300/60">
                            <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=800&q=80" alt="Siswa belajar bersama menggunakan teknologi digital" class="aspect-[4/3] w-full object-cover" />
                            <div class="absolute inset-x-4 bottom-4 flex items-center justify-between rounded-2xl border border-white/60 bg-white/90 p-3 shadow-xl backdrop-blur sm:inset-x-6 sm:bottom-6 sm:p-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-100 text-[#0E3BB0]"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15Z" /></svg></span><div><p class="text-xs font-black text-slate-800">Belajar bersama</p><p class="mt-0.5 text-[11px] text-slate-500">Dalam satu Learning Space</p></div></div><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold text-emerald-700">Aktif</span></div>
                        </div>
                        <div class="absolute -bottom-5 -left-5 hidden rounded-2xl border border-white bg-white p-3 shadow-xl sm:block"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Semangat belajar</p><p class="mt-1 text-sm font-black text-[#0E3BB0]">Tumbuh setiap hari</p></div>
                    </div>
                </div>
            </section>

            <section class="border-y border-slate-200/70 bg-white px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
                <div class="mx-auto max-w-7xl">
                    <div class="mx-auto max-w-2xl text-center"><span class="text-xs font-black uppercase tracking-[0.2em] text-[#0E3BB0]">Satu ruang, banyak kemungkinan</span><h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">Semua yang dibutuhkan untuk belajar lebih seru</h2><p class="mt-4 text-sm leading-6 text-slate-500 sm:text-base">Fitur terintegrasi untuk membantu guru mengajar dengan efektif dan siswa berkembang dengan percaya diri.</p></div>
                    <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <article class="group rounded-2xl border border-slate-100 bg-slate-50 p-6 transition hover:-translate-y-1 hover:border-blue-200 hover:bg-blue-50/50 hover:shadow-lg hover:shadow-blue-100"><div class="grid h-12 w-12 place-items-center rounded-xl bg-blue-100 text-[#0E3BB0] transition group-hover:bg-[#0E3BB0] group-hover:text-white"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 3 1.5 3H14l-2.5 2 1 3L9 9.2 5.5 11l1-3L4 6h3.5L9 3Z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 13v6m-3-3h6" /></svg></div><h3 class="mt-5 text-base font-extrabold text-slate-800">Game Kuis Interaktif</h3><p class="mt-2 text-sm leading-6 text-slate-500">Kuis ala Quizizz dengan leaderboard real-time yang membuat belajar terasa seperti bermain.</p></article>
                        <article class="group rounded-2xl border border-slate-100 bg-slate-50 p-6 transition hover:-translate-y-1 hover:border-blue-200 hover:bg-blue-50/50 hover:shadow-lg hover:shadow-blue-100"><div class="grid h-12 w-12 place-items-center rounded-xl bg-blue-100 text-blue-600 transition group-hover:bg-blue-500 group-hover:text-white"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 2v6h6M8 13h8M8 17h6" /></svg></div><h3 class="mt-5 text-base font-extrabold text-slate-800">Tugas & Penilaian</h3><p class="mt-2 text-sm leading-6 text-slate-500">Kumpulkan tugas, lihat preview dokumen, dan terima umpan balik dari guru dengan mudah.</p></article>
                        <article class="group rounded-2xl border border-slate-100 bg-slate-50 p-6 transition hover:-translate-y-1 hover:border-blue-200 hover:bg-blue-50/50 hover:shadow-lg hover:shadow-blue-100"><div class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-100 text-emerald-600 transition group-hover:bg-emerald-500 group-hover:text-white"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15Z" /></svg></div><h3 class="mt-5 text-base font-extrabold text-slate-800">Materi & Kelas Digital</h3><p class="mt-2 text-sm leading-6 text-slate-500">Akses materi pelajaran dan ruang kelas digital kapan saja, dari mana saja.</p></article>
                        <article class="group rounded-2xl border border-slate-100 bg-slate-50 p-6 transition hover:-translate-y-1 hover:border-blue-200 hover:bg-blue-50/50 hover:shadow-lg hover:shadow-blue-100"><div class="grid h-12 w-12 place-items-center rounded-xl bg-violet-100 text-violet-600 transition group-hover:bg-violet-500 group-hover:text-white"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect width="18" height="18" x="3" y="4" rx="2" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" /></svg></div><h3 class="mt-5 text-base font-extrabold text-slate-800">Presensi & Kehadiran</h3><p class="mt-2 text-sm leading-6 text-slate-500">Perekaman kehadiran siswa yang terintegrasi untuk pemantauan lebih tertib.</p></article>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-slate-900 px-5 py-8 text-slate-400 sm:px-8 lg:px-10"><div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 text-center sm:flex-row sm:text-left"><div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-[#0E3BB0] text-xs font-black text-white">P</span><span class="text-sm font-bold text-white">SMK Pesat Learning Space</span></div><p class="text-xs">Membangun pengalaman belajar yang lebih terarah dan menyenangkan.</p></div></footer>
    </div>
</template>
