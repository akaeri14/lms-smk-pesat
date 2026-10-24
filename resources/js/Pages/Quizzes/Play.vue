<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import QuizAudioPlayer from '@/Components/Quiz/QuizAudioPlayer.vue';

const props = defineProps({
    quiz: {
        type: Object,
        required: true,
    },
    questions: {
        type: Array,
        required: true,
    },
    myAttempt: {
        type: Object,
        default: null,
    },
    leaderboard: {
        type: Array,
        default: () => [],
    },
    attemptsUsed: {
        type: Number,
        default: 0,
    },
    canAttempt: {
        type: Boolean,
        default: true,
    },
    liveLeaderboard: {
        type: Array,
        default: () => [],
    },
    gameState: {
        type: Object,
        default: () => ({ status: 'waiting', participants: [] }),
    },
    audioTracks: { type: Array, default: () => [] },
    myTeam: { type: Object, default: null },
    teamScores: { type: Array, default: () => [] },
    crown: { type: Object, default: () => ({ holder_id: null, holder_name: null, score: 0 }) },
    myCrown: { type: Boolean, default: false },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// ==========================================
// Web Audio Synthesizer (Sound Effects)
// ==========================================
const soundEnabled = ref(true);
let audioCtx = null;

const playTone = (freq, type = 'sine', duration = 0.2, delay = 0) => {
    if (!soundEnabled.value) return;
    try {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = type;
        osc.frequency.setValueAtTime(freq, audioCtx.currentTime + delay);
        gain.gain.setValueAtTime(0.15, audioCtx.currentTime + delay);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + delay + duration);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start(audioCtx.currentTime + delay);
        osc.stop(audioCtx.currentTime + delay + duration);
    } catch (e) {
        // Audio error ignored
    }
};

const playCorrectSound = () => {
    playTone(523.25, 'triangle', 0.15, 0); // C5
    playTone(659.25, 'triangle', 0.15, 0.1); // E5
    playTone(783.99, 'triangle', 0.25, 0.2); // G5
};

const playClickSound = () => {
    playTone(440, 'sine', 0.08, 0);
};

const playVictoryFanfare = () => {
    playTone(523.25, 'triangle', 0.15, 0);
    playTone(659.25, 'triangle', 0.15, 0.12);
    playTone(783.99, 'triangle', 0.15, 0.24);
    playTone(1046.50, 'triangle', 0.4, 0.36); // C6
};

// ==========================================
// Game State
// ==========================================
const gameState = ref(props.myAttempt ? 'result' : 'ready'); // 'ready' | 'playing' | 'result'
const currentQuestionIndex = ref(0);
const selectedAnswers = ref({}); // { question_id: option_id }
const userAnswersHistory = ref([]);
const streak = ref(0);
const earnedPoints = ref(0);
const timeRemaining = ref(props.quiz.time_per_question || 30);
const answerTimes = ref({});
const powerupsUsed = ref([]);
const powerupStocks = ref({ fifty_fifty: 0, freeze_time: 0 });
const powerupReward = ref(null);
const rewardNotice = ref('');
const pendingPowerupUsage = ref(null);
const hiddenOptionIds = ref([]);
const freezeRemaining = ref(0);
const liveLeaderboard = ref([...props.liveLeaderboard]);
const remoteGameStatus = ref(props.gameState?.status || 'waiting');
const myTeam = ref(props.myTeam);
const crownHolder = ref(props.crown?.holder_id ? { ...props.crown } : null);
const hasCrown = ref(props.myCrown);
const crownNotice = ref('');
const quizStartedAt = ref(null);
const questionStartedAt = ref(null);
let timerInterval = null;
let leaderboardInterval = null;
let liveLeaderboardInterval = null;
let liveChannel = null;
let redirectingToResults = false;

const currentQuestion = computed(() => {
    return props.questions[currentQuestionIndex.value] || null;
});

const currentQuestionLimit = computed(() => Math.max(5, Number(currentQuestion.value?.time_limit_seconds || props.quiz.time_per_question || 30)));
const timerPercent = computed(() => Math.max(0, Math.min(100, (timeRemaining.value / currentQuestionLimit.value) * 100)));
const isTimerUrgent = computed(() => timeRemaining.value <= Math.max(5, Math.ceil(currentQuestionLimit.value * 0.25)));
const resultLeaderboard = computed(() => {
    if (!liveLeaderboard.value.length) return props.leaderboard;

    return liveLeaderboard.value.map((item) => ({
        id: `live-${item.user_id}`,
        user_id: item.user_id,
        user: { name: item.name },
        score: item.current_score,
    }));
});

const progressPercentage = computed(() => {
    if (!props.questions.length) return 0;
    return Math.round(((currentQuestionIndex.value + 1) / props.questions.length) * 100);
});

const formattedTime = computed(() => {
    const mins = Math.floor(timeRemaining.value / 60);
    const secs = timeRemaining.value % 60;
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
});

// Palet warna opsi jawaban ala Quizizz
const optionStyles = [
    {
        bg: 'bg-gradient-to-br from-rose-500 to-red-600',
        border: 'border-red-400',
        hover: 'hover:from-rose-600 hover:to-red-700',
        ring: 'ring-red-400',
        symbol: '▲',
        name: 'A',
    },
    {
        bg: 'bg-gradient-to-br from-blue-500 to-indigo-600',
        border: 'border-blue-400',
        hover: 'hover:from-blue-600 hover:to-indigo-700',
        ring: 'ring-blue-400',
        symbol: '◆',
        name: 'B',
    },
    {
        bg: 'bg-gradient-to-br from-amber-500 to-yellow-600',
        border: 'border-amber-400',
        hover: 'hover:from-amber-600 hover:to-yellow-700',
        ring: 'ring-amber-400',
        symbol: '●',
        name: 'C',
    },
    {
        bg: 'bg-gradient-to-br from-emerald-500 to-teal-600',
        border: 'border-emerald-400',
        hover: 'hover:from-emerald-600 hover:to-teal-700',
        ring: 'ring-emerald-400',
        symbol: '■',
        name: 'D',
    },
    {
        bg: 'bg-gradient-to-br from-purple-500 to-violet-600',
        border: 'border-purple-400',
        hover: 'hover:from-purple-600 hover:to-violet-700',
        ring: 'ring-purple-400',
        symbol: '★',
        name: 'E',
    },
    {
        bg: 'bg-gradient-to-br from-pink-500 to-fuchsia-600',
        border: 'border-pink-400',
        hover: 'hover:from-pink-600 hover:to-fuchsia-700',
        ring: 'ring-pink-400',
        symbol: '✦',
        name: 'F',
    },
];

// Start Kuis
const startQuiz = () => {
    if (!props.canAttempt) return;
    gameState.value = 'playing';
    currentQuestionIndex.value = 0;
    selectedAnswers.value = {};
    selectedOptionIds.value = [];
    streak.value = 0;
    earnedPoints.value = 0;
    answerTimes.value = {};
    powerupsUsed.value = [];
    powerupStocks.value = { fifty_fifty: 0, freeze_time: 0 };
    powerupReward.value = null;
    rewardNotice.value = '';
    pendingPowerupUsage.value = null;
    hiddenOptionIds.value = [];
    freezeRemaining.value = 0;
    quizStartedAt.value = Date.now();
    questionStartedAt.value = Date.now();
    timeRemaining.value = currentQuestionLimit.value;
    startLiveLeaderboard();
    bgmPlayer.value?.play();

    playCorrectSound();

    timerInterval = setInterval(() => {
        if (remoteGameStatus.value === 'paused' || remoteGameStatus.value === 'ended') return;
        if (freezeRemaining.value > 0) {
            freezeRemaining.value--;
        } else if (timeRemaining.value > 0) {
            timeRemaining.value--;
        } else {
            clearInterval(timerInterval);
            recordAnswerTime();
            advanceQuestion();
        }
    }, 1000);
};

// Pilih Jawaban
const selectedOptionId = ref(null);
const selectedOptionIds = ref([]);
const isAnswering = ref(false);
const bgmPlayer = ref(null);

const recordAnswerTime = () => {
    if (!currentQuestion.value || answerTimes.value[currentQuestion.value.id] !== undefined) return;
    const elapsed = Math.max(0, Math.round((Date.now() - (questionStartedAt.value || Date.now())) / 1000));
    answerTimes.value[currentQuestion.value.id] = Math.min(currentQuestionLimit.value, elapsed);
};

const redirectToResults = () => {
    if (redirectingToResults) return;
    redirectingToResults = true;
    window.location.assign(route('quizzes.results', props.quiz.id));
};

const applyGameState = (payload) => {
    if (!payload?.status) return;
    remoteGameStatus.value = payload.status;
    if (Array.isArray(payload.participants)) {
        const participant = payload.participants.find((item) => String(item.user_id) === String(user.value.id));
        if (participant?.team_id) myTeam.value = { id: participant.team_id, name: participant.team_name, color: participant.team_color };
    }
    if (payload.crown) applyCrown(payload.crown, false);

    if (payload.status === 'running' && gameState.value === 'ready' && props.canAttempt) {
        startQuiz();
    }

    if (payload.status === 'ended' && timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }

    if (payload.status === 'ended' && gameState.value !== 'result') {
        redirectToResults();
    }
};

const applyCrown = (payload, notify = true) => {
    const previousId = crownHolder.value?.holder_id || crownHolder.value?.id;
    const nextId = payload?.holder_id || null;
    crownHolder.value = nextId
        ? { holder_id: nextId, holder_name: payload.holder_name || 'Peserta', score: payload.score || 0 }
        : null;
    hasCrown.value = nextId !== null && String(nextId) === String(user.value.id);

    if (notify && String(previousId || '') !== String(nextId || '')) {
        crownNotice.value = hasCrown.value
            ? 'Mahkota berhasil direbut! Jawaban benar bernilai 2x poin.'
            : previousId && String(previousId) === String(user.value.id)
                ? 'Mahkota kamu direbut peserta lain.'
                : nextId
                    ? `${payload.holder_name || 'Peserta'} menjadi pemegang mahkota baru.`
                    : 'Mahkota sedang diperebutkan kembali.';
        window.setTimeout(() => { crownNotice.value = ''; }, 3500);
    }
};

const applyQuizStarted = (payload) => {
    remoteGameStatus.value = 'running';
    if (gameState.value === 'ready' && props.canAttempt) startQuiz();
    applyGameState(payload);
};

const applyQuizEnded = (payload) => {
    remoteGameStatus.value = 'ended';
    mergeLiveLeaderboard(payload);
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    if (gameState.value !== 'result') {
        redirectToResults();
    }
};

const mergeLiveLeaderboard = (payload) => {
    if (Array.isArray(payload?.leaderboard)) {
        liveLeaderboard.value = payload.leaderboard;
    }
};

const fetchLiveLeaderboard = () => {
    window.axios.get(route('quizzes.live-leaderboard', props.quiz.id))
        .then(({ data }) => mergeLiveLeaderboard(data))
        .catch(() => {});
};

const startLiveLeaderboard = () => {
    if (liveChannel || liveLeaderboardInterval) return;

    if (window.Echo) {
        liveChannel = window.Echo.private(`quiz.${props.quiz.id}`)
            .listen('.quiz.game.state.updated', applyGameState)
            .listen('.quiz.started', applyQuizStarted)
            .listen('.quiz.ended', applyQuizEnded)
            .listen('.quiz.crown.transferred', (payload) => applyCrown(payload))
            .listen('CrownTransferred', (payload) => applyCrown(payload));
    }

    liveLeaderboardInterval = setInterval(() => {
        window.axios.get(route('quizzes.game-state', props.quiz.id))
            .then(({ data }) => applyGameState(data))
            .catch(() => {});
    }, 5000);
    window.axios.get(route('quizzes.game-state', props.quiz.id)).then(({ data }) => applyGameState(data)).catch(() => {});
};

const applyAnswerResult = (data) => {
    if (typeof data?.current_streak === 'number') streak.value = data.current_streak;
    if (data?.powerup_stocks) powerupStocks.value = data.powerup_stocks;
    if (data?.crown_holder_id !== undefined) applyCrown({ holder_id: data.crown_holder_id, holder_name: data.crown_holder_name, score: data.crown_holder_score }, false);
    if (data?.is_correct) earnedPoints.value += (100 + streak.value * 10) * (data?.crown_multiplier || 1);
    if (data?.powerup_reward) {
        powerupReward.value = data.powerup_reward;
        rewardNotice.value = `${streak.value}x Streak! Kamu mendapatkan Power-Up ${data.powerup_reward === 'fifty_fifty' ? '50:50' : 'Freeze Time'}!`;
        window.setTimeout(() => { powerupReward.value = null; rewardNotice.value = ''; }, 3500);
    }
};

const submitAnswerToServer = (question, optionIdOrIds) => {
    const payload = { question_id: question.id, elapsed_seconds: answerTimes.value[question.id] ?? currentQuestionLimit.value, powerup_used: pendingPowerupUsage.value };
    if (question.question_type === 'multiple_choice_multiple') payload.option_ids = optionIdOrIds;
    else payload.option_id = optionIdOrIds;
    window.axios.post(route('quizzes.answers.store', props.quiz.id), payload).then(({ data }) => { pendingPowerupUsage.value = null; mergeLiveLeaderboard(data); applyAnswerResult(data); }).catch(() => {});
};

const advanceQuestion = () => {
    if (currentQuestionIndex.value < props.questions.length - 1) {
        currentQuestionIndex.value++;
        selectedOptionId.value = null;
        selectedOptionIds.value = [];
        hiddenOptionIds.value = [];
        freezeRemaining.value = 0;
        timeRemaining.value = currentQuestionLimit.value;
        questionStartedAt.value = Date.now();
        isAnswering.value = false;
    } else {
        finishQuiz();
    }
};

const chooseOption = (optionId) => {
    if (currentQuestion.value?.question_type === 'multiple_choice_multiple') {
        toggleComplexOption(optionId);
        return;
    }
    if (isAnswering.value || remoteGameStatus.value !== 'running') return;
    isAnswering.value = true;
    selectedOptionId.value = optionId;

    playClickSound();

    const q = currentQuestion.value;
    recordAnswerTime();
    selectedAnswers.value[q.id] = optionId;
    submitAnswerToServer(q, optionId);

    setTimeout(() => {
        if (currentQuestionIndex.value < props.questions.length - 1) {
            advanceQuestion();
        } else {
            finishQuiz();
        }
    }, 450);
};

const toggleComplexOption = (optionId) => {
    if (isAnswering.value || remoteGameStatus.value !== 'running') return;
    selectedOptionIds.value = selectedOptionIds.value.includes(optionId)
        ? selectedOptionIds.value.filter((id) => id !== optionId)
        : [...selectedOptionIds.value, optionId];
    playClickSound();
};

const submitComplexAnswer = () => {
    if (!selectedOptionIds.value.length || isAnswering.value) return;
    isAnswering.value = true;
    const q = currentQuestion.value;
    recordAnswerTime();
    selectedAnswers.value[q.id] = [...selectedOptionIds.value];
    submitAnswerToServer(q, selectedOptionIds.value);
    setTimeout(() => currentQuestionIndex.value < props.questions.length - 1 ? advanceQuestion() : finishQuiz(), 450);
};

// Selesai Kuis & Submit ke Backend
const submitForm = useForm({
    answers: {},
    answer_times: {},
    powerups_used: [],
    time_taken_seconds: 0,
});

const useFiftyFifty = () => {
    if (!props.quiz.enable_fifty_fifty || powerupStocks.value.fifty_fifty < 1 || !currentQuestion.value) return;
    powerupStocks.value.fifty_fifty--;
    pendingPowerupUsage.value = 'fifty_fifty';
    powerupsUsed.value.push('fifty_fifty');
    hiddenOptionIds.value = currentQuestion.value.fifty_fifty_remove_ids || [];
    playClickSound();
};

const useFreezeTime = () => {
    if (!props.quiz.enable_freeze_time || powerupStocks.value.freeze_time < 1) return;
    powerupStocks.value.freeze_time--;
    pendingPowerupUsage.value = 'freeze_time';
    powerupsUsed.value.push('freeze_time');
    freezeRemaining.value = 10;
    playClickSound();
};

const startLeaderboardPolling = () => {
    if (leaderboardInterval) return;
    leaderboardInterval = setInterval(() => {
        router.reload({ only: ['leaderboard'], preserveScroll: true, preserveState: true });
    }, 5000);
};

const finishQuiz = () => {
    if (timerInterval) clearInterval(timerInterval);
    isAnswering.value = false;

    submitForm.answers = selectedAnswers.value;
    submitForm.answer_times = answerTimes.value;
    submitForm.powerups_used = powerupsUsed.value;
    submitForm.time_taken_seconds = Math.max(0, Math.round((Date.now() - (quizStartedAt.value || Date.now())) / 1000));
    submitForm.post(route('quizzes.attempts.store', props.quiz.id), {
        preserveScroll: true,
        onSuccess: () => {
            gameState.value = 'result';
            playVictoryFanfare();
            startLeaderboardPolling();
        },
    });
};

onMounted(() => {
    if (gameState.value !== 'result') {
        startLiveLeaderboard();
        window.axios.post(route('quizzes.join', props.quiz.id))
            .then(({ data }) => applyGameState(data))
            .catch(() => {});
    }
    if (remoteGameStatus.value === 'running' && gameState.value === 'ready' && props.canAttempt) {
        startQuiz();
    }
    if (gameState.value === 'result') {
        playVictoryFanfare();
        startLeaderboardPolling();
    }
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    if (leaderboardInterval) clearInterval(leaderboardInterval);
    if (liveLeaderboardInterval) clearInterval(liveLeaderboardInterval);
    if (liveChannel && window.Echo) {
        window.Echo.leave(`quiz.${props.quiz.id}`);
    }
});
</script>

<template>
    <Head :title="`Game Kuis: ${quiz.title}`" />

    <div class="min-h-screen bg-slate-950 text-white selection:bg-[#0E3BB0] selection:text-white flex flex-col justify-between font-sans relative overflow-hidden">
        <!-- Background Glow Accent (Modern Game Feel) -->
        <div class="pointer-events-none absolute -top-40 -left-40 h-96 w-96 rounded-full bg-[#0E3BB0]/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl"></div>

        <!-- ============================================================== -->
        <!-- TOP NAV BAR                                                    -->
        <!-- ============================================================== -->
        <header class="relative z-10 flex items-center justify-between border-b border-white/10 bg-slate-900/60 backdrop-blur-md px-6 py-4">
            <div class="flex items-center gap-3">
                <Link
                    :href="route('classes.show', quiz.class_id)"
                    class="rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-slate-300 hover:bg-white/10 hover:text-white transition"
                >
                    ← Kembali ke Kelas
                </Link>
                <div class="hidden sm:block">
                    <h1 class="text-sm font-bold text-white truncate max-w-xs sm:max-w-md">
                        {{ quiz.title }}
                    </h1>
                    <p class="text-[11px] text-slate-400">{{ quiz.school_class?.name }} · {{ quiz.school_class?.subject }}</p>
                    <span v-if="quiz.game_mode === 'team_battle' && myTeam" class="mt-1 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-black text-white" :style="{ backgroundColor: myTeam.color }">Kamu di {{ myTeam.name }}</span>
                    <span v-if="quiz.game_mode === 'steal_crown' && hasCrown" class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-[#FFA800] px-2 py-0.5 text-[10px] font-black text-slate-950"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 7 3 3 3-6 3 6 3-6 3 6 3-3-2 12H5L3 7Z"/></svg> Raja/Ratu Kuis Saat Ini · 2x Poin</span>
                </div>
            </div>

            <!-- Sound Toggle & Timer / Poin -->
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="soundEnabled = !soundEnabled"
                    class="grid h-9 w-9 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-300 hover:bg-white/10 transition"
                    :title="soundEnabled ? 'Matikan Suara' : 'Nyalakan Suara'"
                >
                    <svg v-if="soundEnabled" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5 6 9H3v6h3l5 4V5ZM15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13" /></svg>
                    <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m11 5-5 4H3v6h3l5 4V5ZM18 9l-5 6m0-6 5 6" /></svg>
                </button>
                <QuizAudioPlayer ref="bgmPlayer" :tracks="audioTracks" :muted-by-default="true" label="musik" />

                <div v-if="gameState === 'playing'" class="flex items-center gap-3">
                    <div :class="['flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold transition', isTimerUrgent ? 'border-red-400/50 bg-red-500/15 text-red-300 animate-pulse' : 'border-[#FFA800]/40 bg-[#FFA800]/10 text-[#FFA800]']">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9" stroke-width="1.8" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 7v5l3 2" /></svg>
                        <span>{{ formattedTime }}</span>
                    </div>

                    <div class="hidden sm:flex items-center gap-1 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 text-xs font-bold text-emerald-400">
                        <span>{{ streak }}x Streak</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- ============================================================== -->
        <!-- 1. SCREEN: LOBBY / READY                                       -->
        <!-- ============================================================== -->
        <main v-if="gameState === 'ready'" class="relative z-10 flex-1 flex items-center justify-center p-6">
            <div class="w-full max-w-xl text-center space-y-6">
                <div class="inline-flex items-center gap-2 rounded-full border border-blue-500/30 bg-blue-500/10 px-4 py-1.5 text-xs font-bold text-[#0E3BB0] animate-pulse">
                    <span>MODE GAME KUIS INTERAKTIF</span>
                </div>

                <div class="space-y-3">
                    <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-white">
                        {{ remoteGameStatus === 'waiting' ? 'Menunggu Guru Memulai Permainan' : remoteGameStatus === 'paused' ? 'Permainan Sedang Dijeda' : remoteGameStatus === 'ended' ? 'Permainan Telah Berakhir' : quiz.title }}
                    </h2>
                    <p class="text-sm text-slate-400 max-w-md mx-auto leading-relaxed">
                        {{ remoteGameStatus === 'waiting' ? 'Anda sudah bergabung ke lobby. Tetap di halaman ini sampai guru menekan tombol START.' : remoteGameStatus === 'paused' ? 'Guru sedang menjeda permainan. Timer akan dilanjutkan ketika permainan kembali aktif.' : remoteGameStatus === 'ended' ? 'Guru telah mengakhiri sesi kuis ini.' : quiz.description || 'Siapkan fokusmu! Jawab pertanyaan dengan cepat dan tepat untuk mendapatkan skor maksimal dan menduduki puncak leaderboard.' }}
                    </p>
                </div>

                <!-- Game Stats Cards -->
                <div class="grid grid-cols-3 gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                    <div>
                        <span class="text-xs uppercase font-bold text-slate-400">Jumlah Soal</span>
                        <p class="text-xl font-black text-white mt-1">{{ questions.length }}</p>
                    </div>
                    <div class="border-x border-white/10">
                        <span class="text-xs uppercase font-bold text-slate-400">Durasi</span>
                        <p class="text-xl font-black text-white mt-1">{{ quiz.duration_minutes }}m</p>
                    </div>
                    <div>
                        <span class="text-xs uppercase font-bold text-slate-400">Poin Maks</span>
                        <p class="text-xl font-black text-white mt-1">100 Pts</p>
                    </div>
                </div>

                <p v-if="quiz.max_attempts" class="text-xs font-semibold text-slate-400">
                    Batas percobaan: {{ quiz.max_attempts }} kali · Terpakai: {{ attemptsUsed }} kali
                </p>

                <!-- Big Start Button -->
                <button
                    v-if="remoteGameStatus === 'running' && canAttempt"
                    type="button"
                    @click="startQuiz"
                    class="group relative inline-flex w-full items-center justify-center gap-3 overflow-hidden rounded-2xl bg-gradient-to-r from-[#0E3BB0] to-[#1D4ED8] p-5 text-lg font-black tracking-wide text-white shadow-xl shadow-blue-500/25 transition-all hover:scale-[1.02] hover:shadow-blue-500/40 active:scale-[0.98]"
                >
                    <svg class="h-6 w-6 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 4c3.5-1 6-1 6-1s0 2.5-1 6c-1.2 3.9-4.1 6.8-8 8l-3 1 1-3c1.2-3.9 4.1-6.8 8-8Z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m8 16-3 3M6 12l-3 1 3 2m5 3 1 3 2-3" /><circle cx="15" cy="9" r="1.5" /></svg>
                    <span>MULAI MAIN SEKARANG!</span>
                </button>
                <div v-else class="rounded-2xl border border-white/10 bg-white/5 px-5 py-4 text-sm font-bold text-slate-300">
                    Status permainan: <span class="text-[#FFA800]">{{ remoteGameStatus === 'waiting' ? 'Menunggu guru' : remoteGameStatus === 'paused' ? 'Dijeda' : 'Berakhir' }}</span>
                </div>
            </div>
        </main>

        <!-- ============================================================== -->
        <!-- 2. SCREEN: PLAYING (QUESTION & OPTIONS)                        -->
        <!-- ============================================================== -->
        <main v-else-if="gameState === 'playing' && currentQuestion" class="relative z-10 flex-1 max-w-6xl mx-auto w-full p-4 sm:p-6">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div class="space-y-6">
            <!-- Progress Bar -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-slate-400">
                    <span>Pertanyaan {{ currentQuestionIndex + 1 }} dari {{ questions.length }}</span>
                    <span>{{ progressPercentage }}% Selesai</span>
                </div>
                <div class="h-2.5 w-full rounded-full bg-white/10 overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-[#0E3BB0] to-blue-400 transition-all duration-300 rounded-full"
                        :style="{ width: `${progressPercentage}%` }"
                    ></div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#FFA800]/20 bg-[#FFA800]/5 p-3">
                <div><p class="text-[10px] font-black uppercase tracking-widest text-[#FFA800]">Power-Ups</p><p class="text-xs text-slate-400">Gunakan stok dari reward streak saat menjawab.</p></div>
                <div class="flex gap-2">
                    <button v-if="quiz.enable_fifty_fifty" type="button" :disabled="powerupStocks.fifty_fifty < 1 || isAnswering" @click="useFiftyFifty" class="rounded-xl border border-[#FFA800]/40 bg-[#FFA800]/10 px-3 py-2 text-[11px] font-bold text-[#FFA800] transition hover:bg-[#FFA800]/20 disabled:cursor-not-allowed disabled:opacity-40">50:50 <span class="ml-1 rounded bg-[#FFA800]/20 px-1.5 py-0.5">{{ powerupStocks.fifty_fifty }}</span></button>
                    <button v-if="quiz.enable_freeze_time" type="button" :disabled="powerupStocks.freeze_time < 1 || isAnswering" @click="useFreezeTime" class="rounded-xl border border-[#0E3BB0]/40 bg-[#0E3BB0]/15 px-3 py-2 text-[11px] font-bold text-blue-200 transition hover:bg-[#0E3BB0]/30 disabled:cursor-not-allowed disabled:opacity-40">Freeze 10s <span class="ml-1 rounded bg-[#0E3BB0]/30 px-1.5 py-0.5">{{ powerupStocks.freeze_time }}</span></button>
                </div>
            </div>
            <Transition name="reward"><div v-if="rewardNotice" class="rounded-xl border border-emerald-400/40 bg-emerald-500/15 px-4 py-3 text-center text-sm font-black text-emerald-200">{{ rewardNotice }}</div></Transition>
            <Transition name="reward"><div v-if="crownNotice" class="rounded-xl border border-[#FFA800]/50 bg-[#FFA800]/15 px-4 py-3 text-center text-sm font-black text-[#FFA800]">{{ crownNotice }}</div></Transition>

            <!-- Question Card -->
            <div class="flex-1 flex items-center justify-center my-4">
                <div class="w-full rounded-3xl border border-white/10 bg-slate-900/80 p-8 sm:p-12 text-center shadow-2xl backdrop-blur-xl">
                    <span class="inline-block rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-blue-300 mb-4">
                        Soal #{{ currentQuestionIndex + 1 }} · {{ currentQuestion.points }} Poin · {{ currentQuestionLimit }} detik
                    </span>
                    <p v-if="currentQuestion.question_type === 'multiple_choice_multiple'" class="mx-auto mb-4 inline-flex items-center rounded-full border border-[#FFA800]/40 bg-[#FFA800]/10 px-3 py-1.5 text-xs font-bold text-[#FFA800]">
                        Pilihan ganda kompleks · pilih jawaban lebih dari 1
                    </p>
                    <h3 class="text-2xl sm:text-4xl font-extrabold leading-snug text-white">
                        {{ currentQuestion.question_text }}
                    </h3>
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-slate-400">
                    <span>Waktu soal</span>
                    <span v-if="freezeRemaining > 0" class="text-[#FFA800]">Timer dibekukan {{ freezeRemaining }}s</span>
                    <span v-else>{{ timeRemaining }} detik tersisa</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-white/10">
                    <div :class="['h-full rounded-full transition-all duration-500', isTimerUrgent ? 'bg-red-400' : 'bg-gradient-to-r from-[#0E3BB0] to-[#FFA800]']" :style="{ width: `${timerPercent}%` }"></div>
                </div>
            </div>

            <!-- 4 Options Grid (ala Quizizz/Kahoot) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-4">
                <button
                    v-for="(option, idx) in currentQuestion.options"
                    :key="option.id"
                    v-show="!hiddenOptionIds.includes(option.id)"
                    type="button"
                    :disabled="isAnswering"
                    @click="chooseOption(option.id)"
                    :class="[
                        'group relative flex items-center gap-4 rounded-2xl p-5 sm:p-6 text-left font-bold text-white shadow-lg transition-all duration-200 active:scale-95',
                        optionStyles[idx % optionStyles.length].bg,
                        optionStyles[idx % optionStyles.length].hover,
                        (currentQuestion.question_type === 'multiple_choice_multiple' ? selectedOptionIds.includes(option.id) : selectedOptionId === option.id) ? 'ring-4 ring-white scale-102 brightness-110' : '',
                        isAnswering && selectedOptionId !== option.id ? 'opacity-50' : ''
                    ]"
                >
                    <!-- Symbol Pill -->
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-black/20 text-lg font-black backdrop-blur-sm">
                        {{ optionStyles[idx % optionStyles.length].symbol }}
                    </span>

                    <!-- Option Text -->
                    <span class="flex-1 text-base sm:text-lg leading-snug">
                        {{ option.option_text }}
                    </span>
                </button>
            </div>
            <button v-if="currentQuestion.question_type === 'multiple_choice_multiple'" type="button" :disabled="!selectedOptionIds.length || isAnswering" @click="submitComplexAnswer" class="w-full rounded-2xl bg-[#FFA800] px-5 py-3 text-sm font-black text-slate-950 disabled:cursor-not-allowed disabled:opacity-40">Kirim Jawaban ({{ selectedOptionIds.length }} pilihan)</button>
                </div>
            </div>
        </main>

        <!-- ============================================================== -->
        <!-- 3. SCREEN: VICTORY & LEADERBOARD                               -->
        <!-- ============================================================== -->
        <main v-else-if="gameState === 'result'" class="relative z-10 flex-1 flex items-center justify-center p-6">
            <div class="w-full max-w-3xl space-y-8">
                <!-- Result Banner -->
                <div class="rounded-3xl border border-white/10 bg-slate-900/90 p-8 text-center shadow-2xl backdrop-blur-xl relative overflow-hidden">
                    <div class="absolute -top-24 left-1/2 -translate-x-1/2 h-48 w-48 rounded-full bg-blue-500/20 blur-2xl pointer-events-none"></div>

                    <svg class="mx-auto h-16 w-16 animate-bounce text-[#FFB547]" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 6H4v2a4 4 0 0 0 4 4M17 6h3v2a4 4 0 0 1-4 4M9 21h6" /></svg>
                    <h2 class="mt-3 text-3xl sm:text-4xl font-black text-white">
                        Kuis Berhasil Diselesaikan!
                    </h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Kerja luar biasa! Berikut perolehan skor dan peringkat kelas Anda.
                    </p>

                    <!-- Score Card -->
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-6">
                        <div class="rounded-2xl border border-blue-500/30 bg-blue-500/10 px-8 py-5 text-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-300">Skor Akhir</span>
                            <p class="text-4xl sm:text-5xl font-black text-[#0E3BB0] mt-1">
                                {{ myAttempt?.score ?? 0 }} <span class="text-2xl font-bold text-slate-400">/ 100</span>
                            </p>
                        </div>

                        <div class="rounded-2xl border border-white/10 bg-white/5 px-6 py-5 text-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Jawaban Benar</span>
                            <p class="text-3xl font-black text-white mt-1">
                                {{ myAttempt?.total_correct ?? 0 }} / {{ questions.length }}
                            </p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                        <button
                            v-if="canAttempt && remoteGameStatus === 'running'"
                            type="button"
                            @click="startQuiz"
                            class="rounded-xl bg-[#0E3BB0] px-6 py-3 text-sm font-bold text-white transition hover:bg-[#0B2E8A] shadow-lg shadow-blue-500/25"
                        >
                            Mainkan Ulang Kuis
                        </button>
                        <p v-else class="rounded-xl border border-red-400/30 bg-red-500/10 px-6 py-3 text-sm font-bold text-red-300">
                            Batas percobaan kuis telah tercapai ({{ attemptsUsed }}/{{ quiz.max_attempts }}).
                        </p>
                        <Link
                            :href="route('classes.show', quiz.class_id)"
                            class="rounded-xl border border-white/20 bg-white/5 px-6 py-3 text-sm font-bold text-white transition hover:bg-white/10"
                        >
                            ← Kembali ke Kelas
                        </Link>
                    </div>
                </div>

                <!-- Leaderboard Kelas Real-time -->
                <div class="rounded-3xl border border-white/10 bg-slate-900/80 p-6 sm:p-8 shadow-xl backdrop-blur-md">
                    <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-4">
                        <div class="flex items-center gap-2">
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-[#FFB547]/15 text-sm font-black text-[#FFB547]">1</span>
                            <h3 class="text-lg font-bold text-white">Leaderboard Kelas</h3>
                        </div>
                        <span class="text-xs text-slate-400">Top Skor Tercepat</span>
                    </div>

                    <div v-if="leaderboard.length" class="divide-y divide-white/5">
                        <div
                            v-for="(item, rank) in resultLeaderboard"
                            :key="item.id"
                            :class="[
                                'flex items-center justify-between py-3.5 px-3 rounded-xl transition',
                                item.user_id === user.id ? 'bg-blue-500/15 border border-blue-500/30 font-bold' : ''
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    :class="[
                                        'grid h-7 w-7 place-items-center rounded-lg text-xs font-black',
                                        rank === 0 ? 'bg-yellow-400 text-yellow-950' :
                                        rank === 1 ? 'bg-slate-300 text-slate-900' :
                                        rank === 2 ? 'bg-amber-600 text-amber-50' : 'bg-white/10 text-slate-400'
                                    ]"
                                >
                                    {{ rank + 1 }}
                                </span>

                                <span class="text-sm font-medium text-white">
                                    {{ item.user?.name }}
                                    <span v-if="item.user_id === user.id" class="text-xs text-[#0E3BB0] font-bold">(Anda)</span>
                                </span>
                            </div>

                            <span class="text-sm font-black text-[#0E3BB0]">
                                {{ item.score }} Pts
                            </span>
                        </div>
                    </div>

                    <div v-else class="text-center py-6 text-xs text-slate-500">
                        Belum ada leaderboard untuk kuis ini.
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer simple info -->
        <footer class="relative z-10 flex items-center justify-center gap-2 border-t border-white/5 py-4 text-[11px] text-slate-600">
            <img src="/images/logo-xpro.png" alt="XPRO" class="h-5 w-auto object-contain" />
            LMS SMK Pesat · Interactive Gamified Quiz Engine
        </footer>
    </div>
</template>

<style scoped>
.leaderboard-enter-active,
.leaderboard-leave-active,
.leaderboard-move {
    transition: all 0.35s ease;
}

.leaderboard-enter-from,
.leaderboard-leave-to {
    opacity: 0;
    transform: translateX(16px) scale(0.96);
}

.leaderboard-leave-active {
    position: absolute;
    width: 100%;
}

.reward-enter-active,
.reward-leave-active {
    transition: all 0.3s ease;
}

.reward-enter-from,
.reward-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
}
</style>
