<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    assignment: { type: Object, required: true },
    schoolClass: { type: Object, required: true },
});

// Pre-fill form dengan data yang ada, format deadline agar sesuai input datetime-local
const formatDeadlineForInput = (deadline) => {
    if (!deadline) return '';
    const d = new Date(deadline);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const form = useForm({
    title:       props.assignment.title,
    description: props.assignment.description || '',
    deadline:    formatDeadlineForInput(props.assignment.deadline),
    max_score:   props.assignment.max_score,
    attachment:  null,
});

const clientAttachmentError = ref('');

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

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'patch',
    })).post(route('assignments.update', props.assignment.id), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head :title="`Edit Tugas: ${assignment.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('classes.show', schoolClass.id)" class="text-sm text-slate-400 hover:text-[#FF6B35]">
                    {{ schoolClass.name }}
                </Link>
                <span class="text-slate-300">/</span>
                <Link :href="route('assignments.show', assignment.id)" class="text-sm text-slate-400 hover:text-[#FF6B35]">
                    Tugas
                </Link>
                <span class="text-slate-300">/</span>
                <h2 class="text-lg font-bold text-slate-800">Edit Tugas</h2>
            </div>
        </template>

        <form
            class="mx-auto max-w-3xl rounded-xl bg-white p-6 shadow-sm sm:p-8"
            @submit.prevent="submit"
        >
            <div class="mb-7">
                <h3 class="text-xl font-bold text-slate-800">Edit Tugas</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Perbarui informasi tugas untuk kelas <strong>{{ schoolClass.name }}</strong>.
                </p>
            </div>

            <div class="space-y-5">
                <!-- Judul Tugas -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">
                        Judul Tugas <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="mt-2 block w-full rounded-xl border-slate-300 focus:border-[#FF6B35] focus:ring-[#FF6B35]"
                        placeholder="Contoh: Praktik HTML Dasar"
                        required
                    />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>

                <!-- Instruksi Tugas -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">
                        Instruksi Tugas <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="7"
                        class="mt-2 block w-full rounded-xl border-slate-300 focus:border-[#FF6B35] focus:ring-[#FF6B35]"
                        placeholder="Jelaskan tujuan, langkah pengerjaan, dan ketentuan tugas."
                    />
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>

                <!-- Batas Waktu & Nilai Maksimum -->
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-bold text-slate-700">Batas Waktu</label>
                        <input
                            v-model="form.deadline"
                            type="datetime-local"
                            class="mt-2 block w-full rounded-xl border-slate-300 focus:border-[#FF6B35] focus:ring-[#FF6B35]"
                        />
                        <InputError :message="form.errors.deadline" class="mt-1" />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700">Nilai Maksimum</label>
                        <input
                            v-model="form.max_score"
                            type="number"
                            min="1"
                            class="mt-2 block w-full rounded-xl border-slate-300 focus:border-[#FF6B35] focus:ring-[#FF6B35]"
                        />
                        <InputError :message="form.errors.max_score" class="mt-1" />
                    </div>
                </div>

                <!-- Lampiran -->
                <div>
                    <label class="block text-sm font-bold text-slate-700">
                        Ganti Lampiran
                        <span class="font-normal text-slate-400">(opsional · maks. 10 MB)</span>
                    </label>

                    <!-- Lampiran saat ini -->
                    <div v-if="assignment.file_path" class="mt-2 flex items-center gap-3">
                        <span class="text-xs text-slate-500">Lampiran saat ini:</span>
                        <a
                            :href="`/storage/${assignment.file_path}`"
                            target="_blank"
                            class="inline-flex items-center gap-1 text-xs font-bold text-[#FF6B35] underline hover:text-[#E95521]"
                        >
                            {{ assignment.file_path.split('/').pop() }}
                        </a>
                    </div>

                    <input
                        type="file"
                        accept=".pdf,.docx,.xlsx,.png,.jpg,.jpeg,.zip"
                        class="mt-2 block w-full rounded-xl border border-slate-300 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-orange-50 file:px-4 file:py-2 file:font-bold file:text-[#E95521] transition hover:file:bg-orange-100 focus:outline-none"
                        @change="setAttachment"
                    />
                    <p class="mt-1 text-xs text-slate-400">Biarkan kosong jika tidak ingin mengganti lampiran yang ada.</p>
                    <InputError :message="clientAttachmentError || form.errors.attachment" class="mt-1" />
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-6">
                <Link
                    :href="route('assignments.show', assignment.id)"
                    class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700"
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#FF6B35] px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#E95521] disabled:opacity-50"
                >
                    <svg
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin text-white"
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                    </svg>
                    <span>{{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
