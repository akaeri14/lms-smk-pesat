<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: null },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Masuk" />

    <div class="min-h-screen bg-slate-50 lg:grid lg:grid-cols-2">
        <!-- Branding panel -->
        <section class="relative hidden min-h-screen overflow-hidden bg-gradient-to-br from-[#0E3BB0] via-[#F56B3A] to-[#D94C1D] px-10 py-10 text-white lg:flex lg:flex-col lg:justify-between xl:px-16">
            <div class="absolute -right-32 -top-24 h-96 w-96 rounded-full border-[42px] border-white/10" />
            <div class="absolute -bottom-44 -left-28 h-[30rem] w-[30rem] rounded-full border-[52px] border-white/10" />

            <Link href="/" class="relative z-10 flex w-fit items-center gap-3">
                <span class="grid h-14 w-20 shrink-0 place-items-center overflow-hidden rounded-2xl bg-white/15 px-1 shadow-lg backdrop-blur">
                    <img src="/images/logo-xpro.png" alt="XPRO" class="max-h-10 max-w-full object-contain" @error="$event.currentTarget.classList.add('hidden'); $event.currentTarget.nextElementSibling.classList.remove('hidden')" />
                    <span class="hidden text-base font-black tracking-tight text-white">XPRO</span>
                </span>
                <span><span class="block text-base font-black tracking-tight">SMK Pesat</span><span class="block text-[10px] font-bold uppercase tracking-[0.18em] text-blue-100">Learning Space</span></span>
            </Link>

            <div class="relative z-10 my-12 max-w-xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3.5 py-2 text-xs font-bold text-blue-50 backdrop-blur"><span class="h-2 w-2 rounded-full bg-white" /> Ruang belajar digital Anda</span>
                <h1 class="mt-6 text-4xl font-black leading-tight tracking-tight xl:text-5xl">Selamat Datang Kembali!</h1>
                <p class="mt-5 max-w-md text-base leading-7 text-blue-50">Silakan masuk untuk mengakses materi, tugas, dan kuis interaktif dalam satu ruang belajar yang menyenangkan.</p>

                <div class="mt-10 overflow-hidden rounded-3xl border-4 border-white/20 bg-white/10 shadow-2xl backdrop-blur-sm">
                    <img src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=800&q=80" alt="Siswa belajar bersama di ruang digital" class="aspect-[16/9] w-full object-cover" />
                </div>
            </div>

            <p class="relative z-10 text-xs font-medium text-blue-100">Belajar lebih terarah. Berkembang lebih percaya diri.</p>
        </section>

        <!-- Login form -->
        <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10 lg:px-14 xl:px-24">
            <div class="w-full max-w-md">
                <div class="mb-10 flex items-center gap-3 lg:hidden">
                    <span class="grid h-12 w-16 shrink-0 place-items-center overflow-hidden rounded-xl bg-[#0E3BB0] px-1 shadow-md shadow-blue-200">
                        <img src="/images/logo-xpro.png" alt="XPRO" class="max-h-9 max-w-full object-contain" @error="$event.currentTarget.classList.add('hidden'); $event.currentTarget.nextElementSibling.classList.remove('hidden')" />
                        <span class="hidden text-sm font-black tracking-tight text-white">XPRO</span>
                    </span>
                    <span><span class="block text-sm font-black tracking-tight text-slate-900">SMK Pesat</span><span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-[#0E3BB0]">Learning Space</span></span>
                </div>

                <div class="mb-8">
                    <p class="text-sm font-bold text-[#0E3BB0]">Learning Space</p>
                    <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Masuk ke Akun Anda</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Lanjutkan aktivitas belajar Anda bersama SMK Pesat.</p>
                </div>

                <div v-if="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ status }}</div>
                <div v-if="form.errors.email && !form.errors.password" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-700">{{ form.errors.email }}</div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" value="Alamat Email" class="!text-sm !font-bold !text-slate-700" />
                        <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" placeholder="nama@email.com" class="mt-2 block w-full rounded-xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-[#0E3BB0] focus:ring-2 focus:ring-blue-500/20" />
                        <InputError v-if="form.errors.email && form.errors.password" class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between"><InputLabel for="password" value="Password" class="!text-sm !font-bold !text-slate-700" /><Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-bold text-[#0B2E8A] transition hover:text-[#C24119]">Lupa password?</Link></div>
                        <input id="password" v-model="form.password" type="password" required autocomplete="current-password" placeholder="Masukkan password Anda" class="mt-2 block w-full rounded-xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-[#0E3BB0] focus:ring-2 focus:ring-blue-500/20" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-500"><input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#0E3BB0] shadow-sm focus:ring-blue-500" /> <span>Ingat saya</span></label>

                    <button type="submit" :disabled="form.processing" class="inline-flex w-full items-center justify-center rounded-xl bg-[#0E3BB0] py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-200 transition hover:bg-[#0B2E8A] disabled:cursor-not-allowed disabled:opacity-60"><svg v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v8H4Z" /></svg>{{ form.processing ? 'Memproses...' : 'Masuk' }}</button>
                </form>

                <p class="mt-8 text-center text-sm text-slate-500">Belum punya akun? <Link :href="route('register')" class="font-bold text-[#0B2E8A] hover:text-[#C24119]">Daftar sekarang</Link></p>
                <Link href="/" class="mt-8 block text-center text-xs font-semibold text-slate-400 transition hover:text-slate-600">← Kembali ke halaman utama</Link>
            </div>
        </section>
    </div>
</template>
