<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

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
const timeRemaining = ref(props.quiz.duration_minutes * 60);
let timerInterval = null;

const currentQuestion = computed(() => {
    return props.questions[currentQuestionIndex.value] || null;
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
    gameState.value = 'playing';
    currentQuestionIndex.value = 0;
    selectedAnswers.value = {};
    streak.value = 0;
    earnedPoints.value = 0;
    timeRemaining.value = props.quiz.duration_minutes * 60;

    playCorrectSound();

    timerInterval = setInterval(() => {
        if (timeRemaining.value > 0) {
            timeRemaining.value--;
        } else {
            clearInterval(timerInterval);
            finishQuiz();
        }
    }, 1000);
};

// Pilih Jawaban
const selectedOptionId = ref(null);
const isAnswering = ref(false);

const chooseOption = (optionId) => {
    if (isAnswering.value) return;
    isAnswering.value = true;
    selectedOptionId.value = optionId;

    playClickSound();

    const q = currentQuestion.value;
    selectedAnswers.value[q.id] = optionId;

    // Tambah skor game visual
    streak.value++;
    earnedPoints.value += 100 + streak.value * 10;

    setTimeout(() => {
        if (currentQuestionIndex.value < props.questions.length - 1) {
            currentQuestionIndex.value++;
            selectedOptionId.value = null;
            isAnswering.value = false;
        } else {
            finishQuiz();
        }
    }, 450);
};

// Selesai Kuis & Submit ke Backend
const submitForm = useForm({
    answers: {},
});

const finishQuiz = () => {
    if (timerInterval) clearInterval(timerInterval);
    isAnswering.value = false;

    submitForm.answers = selectedAnswers.value;
    submitForm.post(route('quizzes.attempts.store', props.quiz.id), {
        preserveScroll: true,
        onSuccess: () => {
            gameState.value = 'result';
            playVictoryFanfare();
        },
    });
};

onMounted(() => {
    if (gameState.value === 'result') {
        playVictoryFanfare();
    }
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
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

                <div v-if="gameState === 'playing'" class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5 rounded-xl border border-blue-500/30 bg-blue-500/10 px-3 py-1.5 text-xs font-bold text-[#0E3BB0]">
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
                        {{ quiz.title }}
                    </h2>
                    <p class="text-sm text-slate-400 max-w-md mx-auto leading-relaxed">
                        {{ quiz.description || 'Siapkan fokusmu! Jawab pertanyaan dengan cepat dan tepat untuk mendapatkan skor maksimal dan menduduki puncak leaderboard.' }}
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

                <!-- Big Start Button -->
                <button
                    type="button"
                    @click="startQuiz"
                    class="group relative inline-flex w-full items-center justify-center gap-3 overflow-hidden rounded-2xl bg-gradient-to-r from-[#0E3BB0] to-[#1D4ED8] p-5 text-lg font-black tracking-wide text-white shadow-xl shadow-blue-500/25 transition-all hover:scale-[1.02] hover:shadow-blue-500/40 active:scale-[0.98]"
                >
                    <svg class="h-6 w-6 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 4c3.5-1 6-1 6-1s0 2.5-1 6c-1.2 3.9-4.1 6.8-8 8l-3 1 1-3c1.2-3.9 4.1-6.8 8-8Z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m8 16-3 3M6 12l-3 1 3 2m5 3 1 3 2-3" /><circle cx="15" cy="9" r="1.5" /></svg>
                    <span>MULAI MAIN SEKARANG!</span>
                </button>
            </div>
        </main>

        <!-- ============================================================== -->
        <!-- 2. SCREEN: PLAYING (QUESTION & OPTIONS)                        -->
        <!-- ============================================================== -->
        <main v-else-if="gameState === 'playing' && currentQuestion" class="relative z-10 flex-1 flex flex-col justify-between max-w-5xl mx-auto w-full p-4 sm:p-6 space-y-6">
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

            <!-- Question Card -->
            <div class="flex-1 flex items-center justify-center my-4">
                <div class="w-full rounded-3xl border border-white/10 bg-slate-900/80 p-8 sm:p-12 text-center shadow-2xl backdrop-blur-xl">
                    <span class="inline-block rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-blue-300 mb-4">
                        Soal #{{ currentQuestionIndex + 1 }} · {{ currentQuestion.points }} Poin
                    </span>
                    <h3 class="text-2xl sm:text-4xl font-extrabold leading-snug text-white">
                        {{ currentQuestion.question_text }}
                    </h3>
                </div>
            </div>

            <!-- 4 Options Grid (ala Quizizz/Kahoot) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-4">
                <button
                    v-for="(option, idx) in currentQuestion.options"
                    :key="option.id"
                    type="button"
                    :disabled="isAnswering"
                    @click="chooseOption(option.id)"
                    :class="[
                        'group relative flex items-center gap-4 rounded-2xl p-5 sm:p-6 text-left font-bold text-white shadow-lg transition-all duration-200 active:scale-95',
                        optionStyles[idx % optionStyles.length].bg,
                        optionStyles[idx % optionStyles.length].hover,
                        selectedOptionId === option.id ? 'ring-4 ring-white scale-102 brightness-110' : '',
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
                            type="button"
                            @click="startQuiz"
                            class="rounded-xl bg-[#0E3BB0] px-6 py-3 text-sm font-bold text-white transition hover:bg-[#0B2E8A] shadow-lg shadow-blue-500/25"
                        >
                            Mainkan Ulang Kuis
                        </button>
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
                            v-for="(item, rank) in leaderboard"
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
        <footer class="relative z-10 text-center py-4 border-t border-white/5 text-[11px] text-slate-600">
            LMS SMK Pesat · Interactive Gamified Quiz Engine
        </footer>
    </div>
</template>
