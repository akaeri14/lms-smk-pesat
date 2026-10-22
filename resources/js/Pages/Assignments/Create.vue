<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ schoolClass: { type: Object, required: true } });
const form = useForm({ title: '', description: '', deadline: '', max_score: 100, attachment: null });
const clientAttachmentError = ref('');
const submit = () => form.post(route('classes.assignments.store', props.schoolClass.id), { forceFormData: true });
const setAttachment = (event) => {
    clientAttachmentError.value = '';
    const file = event.target.files[0];
    if (file && file.size > 10 * 1024 * 1024) {
        clientAttachmentError.value = 'Ukuran file lampiran terlalu besar. Maksimal 10MB.';
        event.target.value = '';
        form.attachment = null;
        return;
    }
    form.attachment = file;
};
</script>

<template>
    <Head title="Buat Tugas" />
    <AuthenticatedLayout>
        <template #header><div class="flex items-center gap-3"><Link :href="route('classes.show', schoolClass.id)" class="text-sm text-slate-400 hover:text-[#0E3BB0]">{{ schoolClass.name }}</Link><span class="text-slate-300">/</span><h2 class="text-xl font-bold">Buat tugas</h2></div></template>
        <form class="mx-auto max-w-3xl rounded-xl bg-white p-6 shadow-sm sm:p-8" @submit.prevent="submit"><div class="mb-7"><h3 class="text-xl font-bold text-slate-800">Tugas baru</h3><p class="mt-1 text-sm text-slate-500">Tugas ini akan dibagikan kepada semua anggota {{ schoolClass.name }}.</p></div><div class="space-y-5"><label class="block text-sm font-bold text-slate-700">Judul tugas<input v-model="form.title" class="mt-2 block w-full rounded-lg border-slate-300 focus:border-[#0E3BB0] focus:ring-[#0E3BB0]" placeholder="Contoh: Praktik HTML Dasar" /></label><InputError :message="form.errors.title" /><label class="block text-sm font-bold text-slate-700">Instruksi tugas<textarea v-model="form.description" rows="7" class="mt-2 block w-full rounded-lg border-slate-300 focus:border-[#0E3BB0] focus:ring-[#0E3BB0]" placeholder="Jelaskan tujuan, langkah pengerjaan, dan ketentuan tugas." /></label><InputError :message="form.errors.description" /><div class="grid gap-5 sm:grid-cols-2"><label class="block text-sm font-bold text-slate-700">Batas waktu<input v-model="form.deadline" type="datetime-local" class="mt-2 block w-full rounded-lg border-slate-300 focus:border-[#0E3BB0] focus:ring-[#0E3BB0]" /></label><label class="block text-sm font-bold text-slate-700">Nilai maksimum<input v-model="form.max_score" type="number" min="1" class="mt-2 block w-full rounded-lg border-slate-300 focus:border-[#0E3BB0] focus:ring-[#0E3BB0]" /></label></div><InputError :message="form.errors.deadline || form.errors.max_score" /><label class="block text-sm font-bold text-slate-700">Lampiran <span class="font-normal text-slate-400">(opsional · maks. 10 MB)</span><input type="file" accept=".pdf,.docx,.xlsx,.png,.jpg,.jpeg,.zip" class="mt-2 block w-full rounded-lg border border-slate-300 text-sm file:mr-4 file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:font-bold file:text-[#0B2E8A]" @change="setAttachment" /></label><InputError :message="clientAttachmentError || form.errors.attachment" /></div><div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-6"><Link :href="route('classes.show', schoolClass.id)" class="px-4 py-2 text-sm font-bold text-slate-500">Batal</Link><button :disabled="form.processing" class="rounded-lg bg-[#0E3BB0] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#0B2E8A] disabled:opacity-50">Publikasikan tugas</button></div></form>
    </AuthenticatedLayout>
</template>
