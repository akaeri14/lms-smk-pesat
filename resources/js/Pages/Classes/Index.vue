<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';

defineProps({
    classes: { type: Array, required: true },
    canCreate: { type: Boolean, required: true },
    canJoin: { type: Boolean, required: true },
});

const createModalOpen = ref(false);
const joinModalOpen = ref(false);
const classForm = useForm({ name: '', subject: '', description: '', academic_year: '2026/2027' });
const joinForm = useForm({ code: '' });

const createClass = () => classForm.post(route('classes.store'), {
    onSuccess: () => { createModalOpen.value = false; classForm.reset(); },
});
const joinClass = () => joinForm.post(route('classes.join'), {
    onSuccess: () => { joinModalOpen.value = false; joinForm.reset(); },
});
</script>

<template>
    <Head title="Kelas Saya" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-bold">Kelas Saya</h2></template>
        <section class="mx-auto max-w-7xl">
            <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div><h3 class="text-2xl font-bold text-slate-800">Ruang belajar Anda</h3><p class="mt-1 text-sm text-slate-500">Pilih kelas untuk melihat materi dan tugas.</p></div>
                <div class="flex gap-3"><button v-if="canJoin" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-600 shadow-sm hover:border-[#FF6B35] hover:text-[#E95521]" @click="joinModalOpen = true">Gabung dengan kode</button><button v-if="canCreate" class="rounded-xl bg-[#FF6B35] px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#E95521]" @click="createModalOpen = true">+ Buat kelas</button></div>
            </div>
            <div v-if="classes.length" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <Link v-for="schoolClass in classes" :key="schoolClass.id" :href="route('classes.show', schoolClass.id)" class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="h-2 bg-[#FF6B35]" /><div class="p-6"><div class="mb-5 flex items-start justify-between"><span class="rounded-lg bg-orange-50 px-3 py-1.5 text-xs font-black tracking-wide text-[#E95521]">{{ schoolClass.code }}</span><span class="text-xs font-medium text-slate-400">{{ schoolClass.academic_year }}</span></div><h4 class="text-lg font-bold text-slate-800 group-hover:text-[#E95521]">{{ schoolClass.name }}</h4><p class="mt-1 text-sm font-medium text-slate-500">{{ schoolClass.subject }}</p><p class="mt-4 line-clamp-2 text-sm leading-6 text-slate-500">{{ schoolClass.description || 'Belum ada deskripsi kelas.' }}</p><div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4 text-xs text-slate-500"><span>{{ schoolClass.teacher.name }}</span><span>{{ schoolClass.students_count }} anggota</span></div></div>
                </Link>
            </div>
            <div v-else class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"><div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-orange-50 text-xl text-[#FF6B35]">⌁</div><h4 class="mt-4 font-bold text-slate-800">Belum ada kelas</h4><p class="mt-1 text-sm text-slate-500">{{ canJoin ? 'Masukkan kode kelas untuk mulai belajar.' : 'Buat kelas pertama Anda untuk memulai.' }}</p></div>
        </section>

        <Modal :show="createModalOpen" max-width="lg" @close="createModalOpen = false"><form class="p-6" @submit.prevent="createClass"><h3 class="text-lg font-bold text-slate-800">Buat kelas baru</h3><div class="mt-5 grid gap-4"><label class="text-sm font-semibold text-slate-700">Nama kelas<input v-model="classForm.name" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-[#FF6B35] focus:ring-[#FF6B35]" placeholder="Contoh: XII RPL 1" /></label><InputError :message="classForm.errors.name" /><label class="text-sm font-semibold text-slate-700">Mata pelajaran<input v-model="classForm.subject" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-[#FF6B35] focus:ring-[#FF6B35]" placeholder="Contoh: Pemrograman Web" /></label><InputError :message="classForm.errors.subject" /><label class="text-sm font-semibold text-slate-700">Tahun akademik<input v-model="classForm.academic_year" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-[#FF6B35] focus:ring-[#FF6B35]" /></label><InputError :message="classForm.errors.academic_year" /><label class="text-sm font-semibold text-slate-700">Deskripsi<textarea v-model="classForm.description" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-[#FF6B35] focus:ring-[#FF6B35]" /></label></div><div class="mt-6 flex justify-end gap-3"><button type="button" class="text-sm font-bold text-slate-500" @click="createModalOpen = false">Batal</button><button :disabled="classForm.processing" class="rounded-lg bg-[#FF6B35] px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Buat kelas</button></div></form></Modal>
        <Modal :show="joinModalOpen" max-width="md" @close="joinModalOpen = false"><form class="p-6" @submit.prevent="joinClass"><h3 class="text-lg font-bold text-slate-800">Gabung kelas</h3><p class="mt-1 text-sm text-slate-500">Masukkan enam karakter kode dari guru Anda.</p><label class="mt-5 block text-sm font-semibold text-slate-700">Kode kelas<input v-model="joinForm.code" maxlength="6" class="mt-1 block w-full rounded-lg border-slate-300 font-mono uppercase tracking-[0.3em] focus:border-[#FF6B35] focus:ring-[#FF6B35]" @input="joinForm.code = joinForm.code.toUpperCase()" /></label><InputError class="mt-2" :message="joinForm.errors.code" /><div class="mt-6 flex justify-end gap-3"><button type="button" class="text-sm font-bold text-slate-500" @click="joinModalOpen = false">Batal</button><button :disabled="joinForm.processing" class="rounded-lg bg-[#FF6B35] px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Gabung</button></div></form></Modal>
    </AuthenticatedLayout>
</template>
