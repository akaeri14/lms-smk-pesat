<template>
    <span class="inline-grid place-items-center overflow-hidden rounded-xl bg-[#0E3BB0] px-1" :class="sizeClass">
        <img src="/images/logo-xpro.png" alt="XPRO" class="max-h-full max-w-full object-contain" @error="$event.currentTarget.classList.add('hidden'); $event.currentTarget.nextElementSibling.classList.remove('hidden')" />
        <span class="hidden text-sm font-black tracking-tight text-white">XPRO</span>
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    size: { type: String, default: 'md' },
});

const sizeClass = computed(() => ({
    sm: 'h-9 w-14',
    md: 'h-12 w-16',
    lg: 'h-16 w-24',
}[props.size] ?? 'h-12 w-16'));
</script>
