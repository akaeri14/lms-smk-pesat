<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import QuizAudioPlayer from '@/Components/Quiz/QuizAudioPlayer.vue';

const props = defineProps({
    quiz: { type: Object, required: true },
    gameState: { type: Object, required: true },
    liveLeaderboard: { type: Array, default: () => [] },
    classAccuracy: { type: Number, default: 0 },
    audioTracks: { type: Array, default: () => [] },
});

const status = ref(props.gameState.status || 'waiting');
const participants = ref(Object.values(props.gameState.participants || {}));
const leaderboard = ref([...props.liveLeaderboard]);
const classAccuracy = ref(props.classAccuracy || 0);
const busy = ref(false);
const bgmPlayer = ref(null);
let channel = null;
let pollInterval = null;

const statusLabel = computed(() => ({
    waiting: 'Lobby / Menunggu Peserta',
    running: 'Live Game Berjalan',
    paused: 'Game Dijeda',
    ended: 'Game Berakhir',
}[status.value] || status.value));

const gameCode = computed(() => String(props.quiz.id).padStart(6, '0'));
const qrUrl = computed(() => `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(window.location.origin + route('quizzes.play', props.quiz.id))}`);

const applyState = (payload) => {
    if (payload?.status) status.value = payload.status;
    if (Array.isArray(payload?.participants)) participants.value = payload.participants;
};

const applyParticipantJoined = (payload) => {
    if (!payload?.participant) return;
    const exists = participants.value.some((item) => String(item.user_id) === String(payload.participant.user_id));
    if (!exists) participants.value = [...participants.value, payload.participant];
};

const refreshState = () => {
    window.axios.get(route('quizzes.game-state', props.quiz.id)).then(({ data }) => applyState(data)).catch(() => {});
    window.axios.get(route('quizzes.live-leaderboard', props.quiz.id)).then(({ data }) => {
        leaderboard.value = data.leaderboard || [];
        classAccuracy.value = data.class_accuracy ?? classAccuracy.value;
    }).catch(() => {});
};

const controlGame = (action) => {
    if (busy.value) return;
    busy.value = true;
    window.axios.post(route(`quizzes.${action}`, props.quiz.id))
        .then(({ data }) => { applyState(data); if (action === 'start') bgmPlayer.value?.play(); })
        .finally(() => { busy.value = false; });
};

onMounted(() => {
    if (status.value === 'running') bgmPlayer.value?.play();
    if (window.Echo) {
        channel = window.Echo.private(`quiz.${props.quiz.id}`)
            .listen('.quiz.game.state.updated', applyState)
            .listen('.quiz.participant.joined', applyParticipantJoined)
            .listen('.quiz.score.updated', (payload) => {
                leaderboard.value = payload.leaderboard || leaderboard.value;
                classAccuracy.value = payload.class_accuracy ?? classAccuracy.value;
            });
    }
    pollInterval = setInterval(refreshState, 5000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
    if (channel && window.Echo) window.Echo.leave(`quiz.${props.quiz.id}`);
});
</script>

<template>
    <Head :title="`Host Kuis: ${quiz.title}`" />

    <div class="min-h-screen bg-slate-950 text-white">
        <header class="flex items-center justify-between border-b border-white/10 bg-slate-900/80 px-6 py-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-[#FFA800]">SMK Pesat · Host Mode</p>
                <h1 class="mt-1 text-xl font-black">{{ quiz.title }}</h1>
                <p class="text-xs text-slate-400">{{ quiz.school_class?.name }} · {{ statusLabel }}</p>
            </div>
            <div class="flex items-center gap-2"><QuizAudioPlayer ref="bgmPlayer" :tracks="audioTracks" label="musik" /><Link :href="route('quizzes.show', quiz.id)" class="rounded-xl border border-white/15 px-4 py-2 text-xs font-bold text-slate-300 hover:bg-white/10">Keluar</Link></div>
        </header>

        <main class="mx-auto max-w-7xl space-y-6 p-6">
            <section v-if="status === 'waiting'" class="grid gap-6 lg:grid-cols-[1fr_280px]">
                <div class="rounded-3xl border border-white/10 bg-slate-900 p-8 sm:p-12">
                    <p class="text-sm font-bold uppercase tracking-widest text-slate-400">Game Code / PIN Kuis</p>
                    <p class="mt-3 text-7xl font-black tracking-[0.2em] text-[#FFA800]">{{ gameCode }}</p>
                    <p class="mt-4 max-w-xl text-sm leading-relaxed text-slate-400">Bagikan kode ini kepada siswa. Peserta akan muncul otomatis di lobby ketika membuka halaman Player View.</p>
                    <button type="button" :disabled="busy || !participants.length" @click="controlGame('start')" class="mt-8 w-full rounded-2xl bg-gradient-to-r from-[#0E3BB0] to-[#1D4ED8] px-6 py-4 text-lg font-black shadow-xl shadow-blue-950/40 transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-40">START / MULAI KUIS</button>
                </div>

                <div class="rounded-3xl border border-white/10 bg-white p-5 text-center">
                    <img :src="qrUrl" alt="QR Code untuk bergabung ke kuis" class="mx-auto aspect-square w-full max-w-[220px] rounded-xl" />
                    <p class="mt-3 text-xs font-semibold text-slate-500">Scan untuk bergabung</p>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Peserta Join</p><p class="mt-2 text-4xl font-black text-white">{{ participants.length }}</p></div>
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Class Accuracy</p><p class="mt-2 text-4xl font-black text-[#FFA800]">{{ classAccuracy }}%</p></div>
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Status</p><p class="mt-2 text-xl font-black text-blue-200">{{ statusLabel }}</p></div>
            </section>

            <section v-if="participants.length && status === 'waiting'" class="rounded-3xl border border-white/10 bg-slate-900 p-6">
                <h2 class="mb-4 text-lg font-black">Peserta di Lobby</h2>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4"><div v-for="student in participants" :key="student.user_id" class="rounded-xl bg-white/5 px-4 py-3 text-sm font-semibold text-slate-200">{{ student.name }}</div></div>
            </section>

            <section v-if="status !== 'waiting'" class="rounded-3xl border border-white/10 bg-slate-900 p-6">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div><h2 class="text-xl font-black">Live Leaderboard</h2><p class="text-xs text-slate-400">Peringkat berubah otomatis saat peserta menjawab.</p></div>
                    <div class="flex gap-2"><button v-if="status === 'running' || status === 'paused'" type="button" :disabled="busy || status === 'ended'" @click="controlGame('pause')" class="rounded-xl border border-[#FFA800]/40 px-4 py-2 text-xs font-bold text-[#FFA800] hover:bg-[#FFA800]/10">{{ status === 'paused' ? 'Resume' : 'Pause' }}</button><button v-if="status !== 'ended'" type="button" :disabled="busy" @click="controlGame('end')" class="rounded-xl bg-red-500/15 px-4 py-2 text-xs font-bold text-red-300 hover:bg-red-500/25">End Game</button></div>
                </div>
                <div class="space-y-2"><div v-for="item in leaderboard" :key="item.user_id" class="flex items-center gap-4 rounded-xl border border-white/5 bg-white/5 px-4 py-3"><span class="grid h-8 w-8 place-items-center rounded-lg bg-[#FFA800] text-sm font-black text-slate-950">{{ item.rank }}</span><span class="flex-1 text-sm font-bold text-white">{{ item.name }}</span><span class="text-sm font-black text-[#FFA800]">{{ item.current_score }} Pts</span><span class="text-xs text-slate-400">Live</span></div></div>
                <p v-if="!leaderboard.length" class="py-8 text-center text-sm text-slate-500">Belum ada jawaban masuk.</p>
            </section>
        </main>
    </div>
</template>
