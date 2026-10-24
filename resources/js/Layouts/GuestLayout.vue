<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/" class="flex items-center gap-3">
                <span class="grid h-14 w-20 shrink-0 place-items-center overflow-hidden rounded-2xl bg-[#0E3BB0] px-1 shadow-lg shadow-blue-200">
                    <img src="/images/logo-xpro.png" alt="XPRO" class="max-h-10 max-w-full object-contain" @error="$event.currentTarget.classList.add('hidden'); $event.currentTarget.nextElementSibling.classList.remove('hidden')" />
                    <span class="hidden text-base font-black tracking-tight text-white">XPRO</span>
                </span>
                <span>
                    <span class="block text-base font-black tracking-tight text-slate-800">SMK PESAT</span>
                    <span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-[#0E3BB0]">Learning Space</span>
                </span>
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>

