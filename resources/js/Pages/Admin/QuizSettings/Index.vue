<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ tracks: { type: Array, default: () => [] }, phpUploadLimitMb: { type: Number, default: 2 } });
const form = useForm({ title: '', audio: null });
const fileInput = ref(null);
const onAudioChange = (event) => { form.audio = event.target.files[0] || null; };
const submit = () => form.post(route('admin.quiz-settings.upload'), { forceFormData: true, preserveScroll: true, onSuccess: () => { form.reset(); if (fileInput.value) fileInput.value.value = ''; } });
const remove = (track) => { if (confirm(`Hapus audio “${track.title}”?`)) form.delete(route('admin.quiz-settings.tracks.destroy', track.id), { preserveScroll: true }); };
const update = (track, field, value) => window.axios.patch(route('admin.quiz-settings.tracks.update', track.id), { title: track.title, is_active: field === 'is_active' ? value : track.is_active, sort_order: track.sort_order }).then(() => {}).catch(() => {});
</script>

<template>
    <Head title="Atur Kuis" />
    <AuthenticatedLayout>
        <template #header><div><p class="text-xs font-bold uppercase tracking-widest text-[#0E3BB0]">Administrasi</p><h2 class="text-xl font-extrabold text-slate-800">Atur Kuis · Global Audio Library</h2></div></template>

        <div class="mx-auto max-w-6xl space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-6"><h3 class="text-lg font-extrabold text-slate-800">Tambah Audio BGM</h3><p class="mt-1 text-sm text-slate-500">Audio aktif akan menjadi playlist global untuk semua kuis Live dan Assign.</p><p class="mt-2 text-xs text-amber-700">Maksimal file aplikasi: 100 MB. Konfigurasi PHP saat ini menerima sekitar {{ phpUploadLimitMb }} MB; jika lebih kecil, naikkan <code>upload_max_filesize</code> dan <code>post_max_size</code>.</p></div>
                <form class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end" @submit.prevent="submit">
                    <div><label class="block text-sm font-bold text-slate-700">Judul Lagu</label><input v-model="form.title" type="text" required placeholder="Contoh: Focus Session 01" class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm focus:border-[#0E3BB0] focus:ring-[#0E3BB0]" /><InputError :message="form.errors.title" class="mt-1" /></div>
                    <div><label class="block text-sm font-bold text-slate-700">File Audio</label><input ref="fileInput" type="file" required accept=".mp3,.wav,.ogg,.mpeg,.mpga,audio/mpeg,audio/wav,audio/ogg" class="mt-1.5 block w-full rounded-xl border border-slate-300 text-xs text-slate-500 file:mr-3 file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-xs file:font-bold file:text-[#0E3BB0]" @change="onAudioChange" /><InputError :message="form.errors.audio" class="mt-1" /></div>
                    <button type="submit" :disabled="form.processing" class="rounded-xl bg-[#0E3BB0] px-5 py-3 text-sm font-bold text-white hover:bg-[#0B2E8A] disabled:opacity-50">{{ form.processing ? 'Mengunggah...' : 'Upload Audio' }}</button>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 p-6"><div><h3 class="text-lg font-extrabold text-slate-800">Playlist Audio</h3><p class="mt-1 text-sm text-slate-500">Urutan playlist dipakai oleh sequencer Host dan Player.</p></div><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">{{ tracks.length }} lagu</span></div>
                <div v-if="tracks.length" class="divide-y divide-slate-100">
                    <div v-for="(track, index) in tracks" :key="track.id" class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-4"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-sm font-black text-[#0E3BB0]">{{ index + 1 }}</span><div class="min-w-0"><p class="truncate font-bold text-slate-800">{{ track.title }}</p><p class="text-xs text-slate-400">{{ track.is_active ? 'Aktif di playlist global' : 'Nonaktif' }}</p></div></div>
                        <div class="flex items-center gap-2"><audio :src="track.url" controls class="h-9 max-w-[230px]"></audio><button type="button" @click="update(track, 'is_active', !track.is_active)" :class="track.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'" class="rounded-lg px-3 py-2 text-xs font-bold">{{ track.is_active ? 'Aktif' : 'Nonaktif' }}</button><button type="button" @click="remove(track)" class="rounded-lg px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50">Hapus</button></div>
                    </div>
                </div>
                <div v-else class="p-12 text-center text-sm text-slate-500">Belum ada audio. Upload MP3 pertama untuk mengaktifkan playlist global.</div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
