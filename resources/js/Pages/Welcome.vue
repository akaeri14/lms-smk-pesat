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

    <div class="min-h-screen overflow-hidden bg-slate-50 text-slate-800 selection:bg-orange-200 selection:text-orange-950">
        <header class="relative z-10 border-b border-slate-200/70 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8 lg:px-10">
                <Link href="/" class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-[#FF6B35] text-lg font-black text-white shadow-lg shadow-orange-200">P</span>
                    <span><span class="block text-sm font-black tracking-tight text-slate-900 sm:text-base">SMK Pesat</span><span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-[#FF6B35]">Learning Space</span></span>
                </Link>

                <nav class="flex items-center gap-2 sm:gap-3">
                    <template v-if="isAuthenticated">
                        <Link :href="route('dashboard')" class="inline-flex items-center gap-2 rounded-xl bg-[#FF6B35] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#E95521] sm:px-5 sm:text-sm">
                            Ke Dashboard <span aria-hidden="true">→</span>
                        </Link>
                    </template>
                    <template v-else-if="canLogin">
                        <Link :href="route('login')" class="rounded-xl px-3 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-orange-50 hover:text-[#E95521] sm:px-4 sm:text-sm">Masuk</Link>
                        <Link v-if="canRegister" :href="route('register')" class="rounded-xl bg-[#FF6B35] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#E95521] sm:px-5 sm:text-sm">Daftar</Link>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <section class="relative mx-auto max-w-7xl px-5 pb-16 pt-14 sm:px-8 sm:pt-20 lg:px-10 lg:pb-24 lg:pt-24">
                <div class="absolute -left-32 top-16 h-72 w-72 rounded-full bg-orange-200/30 blur-3xl" />
                <div class="absolute -right-32 bottom-8 h-80 w-80 rounded-full bg-amber-100/50 blur-3xl" />
                <div class="relative grid items-center gap-12 lg:grid-cols-[1.02fr_.98fr] lg:gap-16">
                    <div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3.5 py-2 text-xs font-bold text-[#E95521]"><span class="h-2 w-2 rounded-full bg-[#FF6B35]" /> Ruang belajar digital SMK Pesat</span>
                        <h1 class="mt-6 max-w-2xl text-4xl font-black leading-[1.08] tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">Sistem Manajemen Pembelajaran <span class="text-[#FF6B35]">Terpadu</span> SMK Pesat</h1>
                        <p class="mt-6 max-w-xl text-base leading-7 text-slate-600 sm:text-lg">Belajar lebih terarah dengan kelas digital, kuis gamifikasi, pengumpulan tugas, dan kolaborasi guru-siswa dalam satu ruang belajar modern.</p>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link :href="isAuthenticated ? route('dashboard') : route('login')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FF6B35] px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-orange-200 transition hover:-translate-y-0.5 hover:bg-[#E95521]">Mulai Belajar <span aria-hidden="true">→</span></Link>
                            <Link :href="isAuthenticated ? route('classes.index') : route('login')" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-6 py-3.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-orange-300 hover:text-[#E95521]">Jelajahi Kelas <span aria-hidden="true">⌄</span></Link>
                        </div>
                        <div class="mt-9 flex flex-wrap items-center gap-x-6 gap-y-3 text-xs font-semibold text-slate-500"><span class="flex items-center gap-2"><span class="text-base text-[#FF6B35]">✓</span> Akses kapan saja</span><span class="flex items-center gap-2"><span class="text-base text-[#FF6B35]">✓</span> Terintegrasi untuk sekolah</span></div>
                    </div>

                    <div class="relative mx-auto w-full max-w-xl lg:ml-auto">
                        <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-br from-orange-200/70 to-amber-100/30 blur-2xl" />
                        <div class="relative overflow-hidden rounded-[1.75rem] border-8 border-white bg-white shadow-2xl shadow-slate-300/60">
                            <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=800&q=80" alt="Siswa belajar bersama menggunakan teknologi digital" class="aspect-[4/3] w-full object-cover" />
                            <div class="absolute inset-x-4 bottom-4 flex items-center justify-between rounded-2xl border border-white/60 bg-white/90 p-3 shadow-xl backdrop-blur sm:inset-x-6 sm:bottom-6 sm:p-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-orange-100 text-lg">🎓</span><div><p class="text-xs font-black text-slate-800">Belajar bersama</p><p class="mt-0.5 text-[11px] text-slate-500">Dalam satu Learning Space</p></div></div><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold text-emerald-700">Aktif</span></div>
                        </div>
                        <div class="absolute -bottom-5 -left-5 hidden rounded-2xl border border-white bg-white p-3 shadow-xl sm:block"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Semangat belajar</p><p class="mt-1 text-sm font-black text-[#FF6B35]">Tumbuh setiap hari ✨</p></div>
                    </div>
                </div>
            </section>

            <section class="border-y border-slate-200/70 bg-white px-5 py-16 sm:px-8 lg:px-10 lg:py-20">
                <div class="mx-auto max-w-7xl">
                    <div class="mx-auto max-w-2xl text-center"><span class="text-xs font-black uppercase tracking-[0.2em] text-[#FF6B35]">Satu ruang, banyak kemungkinan</span><h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">Semua yang dibutuhkan untuk belajar lebih seru</h2><p class="mt-4 text-sm leading-6 text-slate-500 sm:text-base">Fitur terintegrasi untuk membantu guru mengajar dengan efektif dan siswa berkembang dengan percaya diri.</p></div>
                    <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <article class="group rounded-2xl border border-slate-100 bg-slate-50 p-6 transition hover:-translate-y-1 hover:border-orange-200 hover:bg-orange-50/50 hover:shadow-lg hover:shadow-orange-100"><div class="grid h-12 w-12 place-items-center rounded-xl bg-orange-100 text-2xl transition group-hover:bg-[#FF6B35]">🎮</div><h3 class="mt-5 text-base font-extrabold text-slate-800">Game Kuis Interaktif</h3><p class="mt-2 text-sm leading-6 text-slate-500">Kuis ala Quizizz dengan leaderboard real-time yang membuat belajar terasa seperti bermain.</p></article>
                        <article class="group rounded-2xl border border-slate-100 bg-slate-50 p-6 transition hover:-translate-y-1 hover:border-orange-200 hover:bg-orange-50/50 hover:shadow-lg hover:shadow-orange-100"><div class="grid h-12 w-12 place-items-center rounded-xl bg-blue-100 text-2xl transition group-hover:bg-blue-500">📝</div><h3 class="mt-5 text-base font-extrabold text-slate-800">Tugas & Penilaian</h3><p class="mt-2 text-sm leading-6 text-slate-500">Kumpulkan tugas, lihat preview dokumen, dan terima umpan balik dari guru dengan mudah.</p></article>
                        <article class="group rounded-2xl border border-slate-100 bg-slate-50 p-6 transition hover:-translate-y-1 hover:border-orange-200 hover:bg-orange-50/50 hover:shadow-lg hover:shadow-orange-100"><div class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-100 text-2xl transition group-hover:bg-emerald-500">📚</div><h3 class="mt-5 text-base font-extrabold text-slate-800">Materi & Kelas Digital</h3><p class="mt-2 text-sm leading-6 text-slate-500">Akses materi pelajaran dan ruang kelas digital kapan saja, dari mana saja.</p></article>
                        <article class="group rounded-2xl border border-slate-100 bg-slate-50 p-6 transition hover:-translate-y-1 hover:border-orange-200 hover:bg-orange-50/50 hover:shadow-lg hover:shadow-orange-100"><div class="grid h-12 w-12 place-items-center rounded-xl bg-violet-100 text-2xl transition group-hover:bg-violet-500">📅</div><h3 class="mt-5 text-base font-extrabold text-slate-800">Presensi & Kehadiran</h3><p class="mt-2 text-sm leading-6 text-slate-500">Perekaman kehadiran siswa yang terintegrasi untuk pemantauan lebih tertib.</p></article>
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-slate-900 px-5 py-8 text-slate-400 sm:px-8 lg:px-10"><div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 text-center sm:flex-row sm:text-left"><div class="flex items-center gap-2"><span class="grid h-8 w-8 place-items-center rounded-lg bg-[#FF6B35] text-xs font-black text-white">P</span><span class="text-sm font-bold text-white">SMK Pesat Learning Space</span></div><p class="text-xs">Membangun pengalaman belajar yang lebih terarah dan menyenangkan.</p></div></footer>
    </div>
</template>
