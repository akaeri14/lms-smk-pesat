<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    tracks: { type: Array, default: () => [] },
    mutedByDefault: { type: Boolean, default: false },
    label: { type: String, default: 'BGM' },
});

const audio = ref(null);
const index = ref(0);
const enabled = ref(!props.mutedByDefault);
const currentTrack = computed(() => props.tracks[index.value] || null);

const play = () => {
    if (!audio.value || !currentTrack.value) return;
    audio.value.muted = !enabled.value;
    audio.value.play().catch(() => {});
};

const toggle = () => {
    enabled.value = !enabled.value;
    if (!audio.value) return;
    audio.value.muted = !enabled.value;
    if (enabled.value) play();
    else audio.value.pause();
};

const nextTrack = () => {
    if (props.tracks.length <= 1) return;
    index.value = (index.value + 1) % props.tracks.length;
    watch(currentTrack, () => play(), { once: true });
};

watch(() => props.tracks, () => {
    index.value = 0;
    play();
}, { deep: true });

defineExpose({ play, toggle });
</script>

<template>
    <div v-if="tracks.length" class="flex items-center gap-2">
        <audio ref="audio" :src="currentTrack?.url" :loop="tracks.length === 1" @ended="nextTrack"></audio>
        <button type="button" @click="toggle" class="grid h-9 w-9 place-items-center rounded-xl border border-[#FFA800]/40 bg-[#FFA800]/10 text-[#FFA800]" :title="enabled ? `Matikan ${label}` : `Nyalakan ${label}`">
            <svg v-if="enabled" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5 6 9H3v6h3l5 4V5ZM15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13" /></svg>
            <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m11 5-5 4H3v6h3l5 4V5ZM18 9l-5 6m0-6 5 6" /></svg>
        </button>
    </div>
</template>
