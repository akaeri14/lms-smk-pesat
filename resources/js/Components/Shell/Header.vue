<script setup>
import { computed } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const props = defineProps({ user: { type: Object, required: true } });
const emit = defineEmits(['toggle-sidebar']);
const roleLabel = computed(() => ({ admin: 'Administrator', guru: 'Guru', siswa: 'Siswa' }[props.user.role] ?? 'Siswa'));
const initials = computed(() => props.user.name.split(' ').map((name) => name[0]).slice(0, 2).join('').toUpperCase());
</script>

<template>
    <header class="flex h-20 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-7">
        <div class="flex items-center gap-3"><button class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" type="button" aria-label="Buka menu" @click="emit('toggle-sidebar')"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round" /></svg></button><div><p class="text-xs font-medium text-slate-400">LMS SMK Pesat</p><div class="text-base font-bold text-slate-800"><slot name="title">Dashboard</slot></div></div></div>
        <div class="flex items-center gap-2 sm:gap-4"><button class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100" type="button" aria-label="Notifikasi"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" stroke-linecap="round" stroke-linejoin="round" /></svg><span class="absolute right-2 top-2 h-1.5 w-1.5 rounded-full bg-[#FF6B35]" /></button>
            <Dropdown align="right" width="56"><template #trigger><button class="flex items-center gap-2 rounded-xl p-1.5 text-left hover:bg-slate-50"><img v-if="user.avatar" :src="user.avatar" :alt="user.name" class="h-9 w-9 rounded-full object-cover" /><span v-else class="grid h-9 w-9 place-items-center rounded-full bg-orange-100 text-xs font-bold text-[#E95521]">{{ initials }}</span><span class="hidden sm:block"><span class="block text-sm font-semibold text-slate-700">{{ user.name }}</span><span class="block text-xs text-slate-400">{{ roleLabel }}</span></span><svg class="hidden h-4 w-4 text-slate-400 sm:block" viewBox="0 0 20 20" fill="currentColor"><path d="m5.25 7.5 4.75 5 4.75-5h-9.5Z" /></svg></button></template><template #content><div class="border-b border-slate-100 px-4 py-3"><span class="inline-flex rounded-full bg-orange-50 px-2.5 py-1 text-xs font-bold text-[#E95521]">{{ roleLabel }}</span></div><DropdownLink :href="route('profile.edit')">Profil Saya</DropdownLink><DropdownLink :href="route('logout')" method="post" as="button">Keluar</DropdownLink></template></Dropdown>
        </div>
    </header>
</template>
