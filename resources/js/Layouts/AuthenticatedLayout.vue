<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Header from '@/Components/Shell/Header.vue';
import Sidebar from '@/Components/Shell/Sidebar.vue';

const sidebarOpen = ref(false);
const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <div class="flex min-h-screen bg-[#F8FAFC] text-slate-800">
        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />
        <div class="flex min-w-0 flex-1 flex-col">
            <Header :user="user" @toggle-sidebar="sidebarOpen = true">
                <template #title><slot name="header">Dashboard</slot></template>
            </Header>
            <main class="flex-1 p-4 sm:p-7"><slot /></main>
        </div>
    </div>
</template>
