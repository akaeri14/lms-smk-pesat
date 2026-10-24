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
const teams = ref([...(props.gameState.teams || [])]);
const teamCount = ref(props.gameState.team_count || 2);
const leaderboard = ref([...props.liveLeaderboard]);
const classAccuracy = ref(props.classAccuracy || 0);
const crownHolder = ref(props.gameState.crown_holder_id ? {
    id: props.gameState.crown_holder_id,
    name: props.gameState.crown_holder_name,
    avatar: props.gameState.crown_holder_avatar || null,
    score: props.gameState.crown_holder_score || 0,
} : null);
const crownChangeKey = ref(0);
const crownStealNotice = ref(null);
let crownNoticeTimeout = null;

const answeredCount = ref(0);
const latestAnswerStudent = ref(null);
const currentQuestionId = ref(null);

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

const answerPercentage = computed(() => {
    const total = participants.value.length || 1;
    return Math.min(100, Math.round((answeredCount.value / total) * 100));
});

const applyState = (payload) => {
    if (payload?.status) status.value = payload.status;
    if (Array.isArray(payload?.participants)) participants.value = payload.participants;
    if (Array.isArray(payload?.teams)) teams.value = payload.teams;
    if (payload?.team_count) teamCount.value = payload.team_count;
    if (payload?.crown) applyCrown(payload.crown, false);
};

const playCrownStolenSfx = () => {
    try {
        const context = new (window.AudioContext || window.webkitAudioContext)();
        [659.25, 783.99, 1046.5].forEach((frequency, index) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.type = 'triangle';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.001, context.currentTime + index * 0.1);
            gain.gain.exponentialRampToValueAtTime(0.18, context.currentTime + index * 0.1 + 0.03);
            gain.gain.exponentialRampToValueAtTime(0.001, context.currentTime + index * 0.1 + 0.25);
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.start(context.currentTime + index * 0.1);
            oscillator.stop(context.currentTime + index * 0.1 + 0.28);
        });
    } catch (error) {
        // Efek suara bersifat opsional dan tidak boleh menghentikan game.
    }
};

const applyCrown = (payload, playSound = true) => {
    const previousId = crownHolder.value?.id;
    crownHolder.value = payload?.holder_id ? {
        id: payload.holder_id,
        name: payload.holder_name || 'Peserta',
        avatar: payload.holder_avatar || null,
        score: payload.score || 0,
    } : null;
    crownChangeKey.value++;

    if (payload?.holder_id && String(previousId) !== String(payload.holder_id)) {
        crownStealNotice.value = {
            name: payload.holder_name || 'Peserta',
            reason: payload.reason || 'mahkota_direbut',
            score: payload.score || 0,
            avatar: payload.holder_avatar || null,
        };
        if (crownNoticeTimeout) clearTimeout(crownNoticeTimeout);
        crownNoticeTimeout = setTimeout(() => {
            crownStealNotice.value = null;
        }, 5000);
    }

    if (playSound && previousId && String(previousId) !== String(payload?.holder_id || '')) {
        playCrownStolenSfx();
    }
};

const applyScoreUpdated = (payload) => {
    if (!payload) return;
    if (Array.isArray(payload.leaderboard)) {
        leaderboard.value = payload.leaderboard;
    }
    if (Array.isArray(payload.teams)) {
        teams.value = payload.teams;
    }
    if (payload.class_accuracy !== undefined && payload.class_accuracy !== null) {
        classAccuracy.value = Number(payload.class_accuracy);
    }
};

const applyAnswerSubmitted = (payload) => {
    if (!payload) return;
    if (payload.question_id && payload.question_id !== currentQuestionId.value) {
        currentQuestionId.value = payload.question_id;
        answeredCount.value = payload.answered_count || 1;
    } else {
        answeredCount.value = Math.max(answeredCount.value, Number(payload.answered_count || (answeredCount.value + 1)));
    }
    if (payload.student_name) {
        latestAnswerStudent.value = {
            name: payload.student_name,
            isCorrect: payload.is_correct ?? null,
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
        };
    }
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
        .then(({ data }) => {
            applyState(data);
            if (action === 'start') {
                bgmPlayer.value?.play();
                answeredCount.value = 0;
            }
        })
        .finally(() => { busy.value = false; });
};

const shuffleTeams = () => {
    if (busy.value || !participants.value.length) return;
    busy.value = true;
    window.axios.post(route('quizzes.shuffle-teams', props.quiz.id), { team_count: teamCount.value })
        .then(({ data }) => applyState(data))
        .finally(() => { busy.value = false; });
};

const teamMaxScore = computed(() => Math.max(1, ...teams.value.map((team) => Number(team.score || 0))));

onMounted(() => {
    if (status.value === 'running') bgmPlayer.value?.play();
    if (window.Echo) {
        channel = window.Echo.private(`quiz.${props.quiz.id}`)
            .listen('ScoreUpdated', applyScoreUpdated)
            .listen('.ScoreUpdated', applyScoreUpdated)
            .listen('.quiz.score.updated', applyScoreUpdated)
            .listen('.quiz.team.scores.updated', (payload) => {
                if (Array.isArray(payload?.teams)) teams.value = payload.teams;
            })
            .listen('CrownTransferred', (payload) => applyCrown(payload))
            .listen('.CrownTransferred', (payload) => applyCrown(payload))
            .listen('.quiz.crown.transferred', (payload) => applyCrown(payload))
            .listen('AnswerSubmitted', applyAnswerSubmitted)
            .listen('.AnswerSubmitted', applyAnswerSubmitted)
            .listen('.quiz.answer.submitted', applyAnswerSubmitted)
            .listen('.quiz.game.state.updated', applyState)
            .listen('.quiz.participant.joined', applyParticipantJoined);
    }
    pollInterval = setInterval(refreshState, 5000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
    if (crownNoticeTimeout) clearTimeout(crownNoticeTimeout);
    if (window.Echo) window.Echo.leave(`quiz.${props.quiz.id}`);
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

            <section v-if="status === 'waiting' && quiz.game_mode === 'team_battle'" class="rounded-3xl border border-[#FFA800]/30 bg-slate-900 p-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div><p class="text-xs font-bold uppercase tracking-widest text-[#FFA800]">Battle Mode</p><h2 class="mt-1 text-xl font-black">Team vs Team</h2><p class="mt-1 text-xs text-slate-400">Pilih jumlah tim, lalu acak anggota secara seimbang sebelum mulai.</p></div>
                    <div class="flex items-end gap-2"><label class="text-xs font-bold text-slate-400">Jumlah Tim<select v-model="teamCount" class="mt-1 block rounded-xl border-slate-700 bg-slate-800 text-sm font-bold text-white"><option :value="2">2 Tim</option><option :value="3">3 Tim</option><option :value="4">4 Tim</option></select></label><button type="button" :disabled="busy || !participants.length" @click="shuffleTeams" class="rounded-xl bg-[#FFA800] px-4 py-2.5 text-xs font-black text-slate-950 disabled:opacity-40">Acak Tim</button></div>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div v-for="team in teams" :key="team.id" class="rounded-xl border border-white/10 bg-white/5 p-3"><div class="flex items-center gap-2 text-sm font-bold"><span class="h-3 w-3 rounded-full" :style="{ backgroundColor: team.color }"></span>{{ team.name }}</div><p class="mt-1 text-xs text-slate-400">{{ (team.member_ids || []).length }} anggota</p></div></div>
            </section>

            <!-- Steal the Crown Alert / Header -->
            <section v-if="quiz.game_mode === 'steal_crown'" class="relative overflow-hidden rounded-3xl border border-[#FFA800]/40 bg-gradient-to-r from-[#FFA800]/15 via-slate-900 to-slate-900 p-6 shadow-xl shadow-amber-500/5">
                <!-- Banner saat perebutan mahkota terjadi -->
                <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 -translate-y-2 scale-95" leave-active-class="transition duration-200 ease-in" leave-to-class="opacity-0 -translate-y-2 scale-95">
                    <div v-if="crownStealNotice" class="mb-4 flex items-center justify-between gap-3 rounded-2xl bg-gradient-to-r from-[#FFA800] to-amber-500 px-4 py-3 font-black text-slate-950 shadow-lg shadow-amber-500/20">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">👑</span>
                            <span>MAHKOTA DIREBUT! {{ crownStealNotice.name }} kini menjadi Penguasa Mahkota Emas!</span>
                        </div>
                        <span class="rounded-full bg-slate-950/20 px-3 py-1 text-xs uppercase tracking-wider text-slate-950">{{ crownStealNotice.score }} Pts</span>
                    </div>
                </transition>

                <div class="flex flex-wrap items-center gap-4">
                    <div class="relative grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-[#FFA800]/20 text-[#FFA800]" :key="crownChangeKey">
                        <img v-if="crownHolder?.avatar" :src="crownHolder.avatar" :alt="crownHolder.name" class="h-14 w-14 rounded-xl object-cover" />
                        <svg v-else class="h-10 w-10 animate-bounce" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 7 3 3 3-6 3 6 3-6 3 6 3-3-2 12H5L3 7Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 19h14"/></svg>
                        <span class="absolute -top-2 -right-2 text-xl drop-shadow-md">👑</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-xs font-bold uppercase tracking-widest text-[#FFA800]">Steal the Crown Mode</p>
                            <span v-if="crownHolder" class="rounded-full bg-[#FFA800]/20 px-2 py-0.5 text-[10px] font-black text-[#FFA800]">AKTIF</span>
                        </div>
                        <p class="mt-1 text-xl font-black text-white">{{ crownHolder?.name || 'Mahkota belum dimiliki' }}</p>
                        <p class="text-xs text-slate-400">Pemegang mahkota memperoleh 2x poin untuk setiap jawaban benar.</p>
                    </div>
                    <span v-if="crownHolder" class="ml-auto rounded-full bg-[#FFA800] px-5 py-2.5 text-sm font-black text-slate-950 shadow-md shadow-amber-500/20">{{ crownHolder.score }} Pts · 2x Multiplier</span>
                </div>
            </section>

            <!-- Real-time Answer Progress Card (Aktif saat game running) -->
            <section v-if="status === 'running'" class="rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-lg">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="relative flex h-3 w-3">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                        </span>
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-wider text-slate-200">Progres Jawaban Soal Berjalan</h3>
                            <p v-if="latestAnswerStudent" class="text-xs text-slate-400">
                                <span class="font-bold text-[#FFA800]">{{ latestAnswerStudent.name }}</span> baru saja menjawab ({{ latestAnswerStudent.time }})
                            </p>
                            <p v-else class="text-xs text-slate-500">Menunggu peserta menjawab soal...</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-lg font-black text-[#FFA800]">{{ answeredCount }} / {{ participants.length }}</span>
                        <span class="ml-2 text-xs font-bold text-slate-400">({{ answerPercentage }}% siswa)</span>
                    </div>
                </div>
                <div class="mt-4 h-3 w-full overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-gradient-to-r from-blue-600 via-[#0E3BB0] to-emerald-400 transition-all duration-500" :style="{ width: `${answerPercentage}%` }"></div>
                </div>
            </section>

            <!-- Ringkasan Statistik Live -->
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Peserta Join</p>
                    <p class="mt-2 text-4xl font-black text-white">{{ participants.length }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Sudah Menjawab</p>
                    <p class="mt-2 text-4xl font-black text-emerald-400">{{ answeredCount }} <span class="text-lg text-slate-400">/ {{ participants.length }}</span></p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Class Accuracy</p>
                    <p class="mt-2 text-4xl font-black text-[#FFA800]">{{ classAccuracy }}%</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-slate-900 p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Status Game</p>
                    <p class="mt-2 text-xl font-black text-blue-200">{{ statusLabel }}</p>
                </div>
            </section>

            <section v-if="participants.length && status === 'waiting'" class="rounded-3xl border border-white/10 bg-slate-900 p-6">
                <h2 class="mb-4 text-lg font-black">Peserta di Lobby</h2>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4"><div v-for="student in participants" :key="student.user_id" class="rounded-xl bg-white/5 px-4 py-3 text-sm font-semibold text-slate-200">{{ student.name }}</div></div>
            </section>

            <section v-if="status !== 'waiting' && quiz.game_mode === 'team_battle'" class="rounded-3xl border border-[#FFA800]/30 bg-slate-900 p-6">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-widest text-[#FFA800]">Battle Mode</p><h2 class="mt-1 text-xl font-black">Live Team Leaderboard</h2><p class="text-xs text-slate-400">Akumulasi poin dan akurasi setiap tim diperbarui secara real-time via WebSocket.</p></div><div class="flex gap-2"><button v-if="status === 'running' || status === 'paused'" type="button" :disabled="busy" @click="controlGame('pause')" class="rounded-xl border border-[#FFA800]/40 px-4 py-2 text-xs font-bold text-[#FFA800]">{{ status === 'paused' ? 'Resume' : 'Pause' }}</button><button v-if="status !== 'ended'" type="button" :disabled="busy" @click="controlGame('end')" class="rounded-xl bg-red-500/15 px-4 py-2 text-xs font-bold text-red-300">End Game</button></div></div>
                <div class="space-y-4"><div v-for="(team, index) in teams" :key="team.id" class="rounded-2xl border border-white/10 bg-white/5 p-4"><div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-lg bg-white/10 text-sm font-black">{{ index + 1 }}</span><span class="h-4 w-4 rounded-full" :style="{ backgroundColor: team.color }"></span><span class="flex-1 font-black">{{ team.name }}</span><span class="text-lg font-black text-[#FFA800]">{{ team.score || 0 }} Pts</span></div><div class="mt-3 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full transition-all duration-500" :style="{ width: `${Math.round(((team.score || 0) / teamMaxScore) * 100)}%`, backgroundColor: team.color }"></div></div><p class="mt-2 text-xs text-slate-400">Akurasi {{ team.accuracy ?? 0 }}% · {{ team.member_ids?.length || 0 }} anggota</p></div></div>
            </section>

            <section v-if="status !== 'waiting' && quiz.game_mode !== 'team_battle'" class="rounded-3xl border border-white/10 bg-slate-900 p-6">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div><h2 class="text-xl font-black">Live Leaderboard</h2><p class="text-xs text-slate-400">Peringkat dan skor diperbarui instan via WebSocket.</p></div>
                    <div class="flex gap-2"><button v-if="status === 'running' || status === 'paused'" type="button" :disabled="busy || status === 'ended'" @click="controlGame('pause')" class="rounded-xl border border-[#FFA800]/40 px-4 py-2 text-xs font-bold text-[#FFA800] hover:bg-[#FFA800]/10">{{ status === 'paused' ? 'Resume' : 'Pause' }}</button><button v-if="status !== 'ended'" type="button" :disabled="busy" @click="controlGame('end')" class="rounded-xl bg-red-500/15 px-4 py-2 text-xs font-bold text-red-300 hover:bg-red-500/25">End Game</button></div>
                </div>
                <div class="space-y-2">
                    <div v-for="item in leaderboard" :key="item.user_id" class="relative flex items-center gap-4 rounded-xl border border-white/5 bg-white/5 px-4 py-3 transition-all duration-300">
                        <span v-if="quiz.game_mode === 'steal_crown' && String(crownHolder?.id) === String(item.user_id)" class="absolute -top-5 left-7 text-[#FFA800]" :key="`crown-${crownChangeKey}`">
                            <svg class="h-8 w-8 animate-bounce drop-shadow-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 7 3 3 3-6 3 6 3-6 3 6 3-3-2 12H5L3 7Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 19h14"/>
                            </svg>
                        </span>
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#FFA800] text-sm font-black text-slate-950">{{ item.rank }}</span>
                        <span class="flex-1 text-sm font-bold text-white">{{ item.name }}</span>
                        <span class="text-sm font-black text-[#FFA800]">{{ item.current_score }} Pts</span>
                        <span class="flex items-center gap-1.5 rounded-md bg-emerald-500/10 px-2 py-0.5 text-xs font-bold text-emerald-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Live
                        </span>
                    </div>
                </div>
                <p v-if="!leaderboard.length" class="py-8 text-center text-sm text-slate-500">Belum ada jawaban masuk.</p>
            </section>
        </main>
    </div>
</template>
