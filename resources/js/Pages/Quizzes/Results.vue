<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    quiz: { type: Object, required: true },
    myResult: { type: Object, required: true },
    leaderboard: { type: Array, default: () => [] },
    podium: { type: Array, default: () => [] },
});

const podiumClass = (rank) => ({
    1: 'order-2 border-[#FFA800] bg-[#FFA800]/10 sm:-translate-y-4',
    2: 'order-1 border-slate-300 bg-white/10',
    3: 'order-3 border-amber-700/60 bg-amber-900/20',
}[rank] || 'border-white/10');
</script>

<template>
    <Head :title="`Hasil Game: ${quiz.title}`" />

    <div class="min-h-screen bg-slate-950 px-4 py-8 text-white sm:px-6">
        <main class="mx-auto max-w-5xl space-y-6">
            <section class="relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-[#0E3BB0] to-slate-900 p-8 text-center shadow-2xl sm:p-12">
                <div class="pointer-events-none absolute -right-20 -top-20 h-56 w-56 rounded-full bg-[#FFA800]/20 blur-3xl"></div>
                <p class="text-xs font-bold uppercase tracking-[0.25em] text-[#FFA800]">Game Over · {{ quiz.title }}</p>
                <h1 class="mt-4 text-4xl font-black sm:text-6xl">Permainan Selesai!</h1>
                <p class="mx-auto mt-3 max-w-xl text-sm text-blue-100">Berikut ringkasan performa Anda dan podium terbaik kelas.</p>
            </section>

            <section class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Peringkat Akhir</p><p class="mt-2 text-3xl font-black text-[#FFA800]">{{ myResult.rank ? `#${myResult.rank}` : '-' }}</p></div>
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Skor</p><p class="mt-2 text-3xl font-black text-white">{{ myResult.score }}<span class="text-base text-slate-500"> / 100</span></p></div>
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Benar / Salah</p><p class="mt-2 text-3xl font-black text-white">{{ myResult.correct }} <span class="text-lg text-slate-500">/ {{ myResult.incorrect }}</span></p></div>
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Akurasi</p><p class="mt-2 text-3xl font-black text-[#FFA800]">{{ myResult.accuracy }}%</p></div>
            </section>

            <section class="rounded-3xl border border-white/10 bg-slate-900 p-6 sm:p-8">
                <div class="mb-8 text-center"><p class="text-xs font-bold uppercase tracking-[0.2em] text-[#FFA800]">Top 3 Kelas</p><h2 class="mt-2 text-2xl font-black">Podium Pemenang</h2></div>
                <div v-if="podium.length" class="flex items-end justify-center gap-2 sm:gap-5">
                    <div v-for="item in podium" :key="item.user_id" :class="['flex w-1/3 max-w-[190px] flex-col items-center rounded-2xl border p-4 text-center transition', podiumClass(item.rank)]">
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-white/10 text-lg font-black">{{ item.rank }}</span>
                        <p class="mt-3 w-full truncate text-sm font-bold text-white">{{ item.name }}</p>
                        <p class="mt-1 text-xl font-black text-[#FFA800]">{{ item.score }}</p>
                        <p class="text-[10px] uppercase tracking-wider text-slate-500">poin</p>
                    </div>
                </div>
                <p v-else class="py-8 text-center text-sm text-slate-500">Belum ada data podium.</p>
            </section>

            <section class="rounded-3xl border border-white/10 bg-slate-900 p-6">
                <div class="mb-4 flex items-center justify-between"><h2 class="text-lg font-black">Leaderboard Kelas</h2><span class="text-xs text-slate-500">Top 5</span></div>
                <div class="divide-y divide-white/5"><div v-for="item in leaderboard" :key="item.user_id" class="flex items-center gap-3 py-3"><span class="grid h-7 w-7 place-items-center rounded-lg bg-white/10 text-xs font-black text-slate-300">{{ item.rank }}</span><span class="flex-1 text-sm font-semibold text-white">{{ item.name }}</span><span class="text-sm font-black text-[#FFA800]">{{ item.score }} Pts</span></div></div>
            </section>

            <div class="flex justify-center pt-2"><Link :href="route('classes.show', quiz.class_id)" class="rounded-2xl bg-[#0E3BB0] px-8 py-3.5 text-sm font-black text-white shadow-lg shadow-blue-950/40 transition hover:bg-[#0B2E8A]">Kembali ke Kelas</Link></div>
        </main>
    </div>
</template>
