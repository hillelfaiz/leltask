<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PremiumCard from '@/Components/PremiumCard.vue';
import PremiumButton from '@/Components/PremiumButton.vue';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
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
    <GuestLayout>
        <Head title="Masuk" />

        <div v-if="status" class="mb-4 font-medium text-sm text-pastel-green-text bg-pastel-green-bg p-3 rounded-lg">
            {{ status }}
        </div>

        <div class="mb-10 text-center">
            <h1 class="text-3xl md:text-4xl font-medium tracking-tight text-primary mb-3">Selamat datang kembali.</h1>
            <p class="text-muted text-sm">Masukkan kredensial Anda untuk mengakses ruang kerja.</p>
        </div>

        <PremiumCard padding="p-8">
            <form @submit.prevent="submit" class="flex flex-col gap-6">
                
                <div class="flex flex-col gap-2">
                    <label for="email" class="font-mono text-[10px] uppercase tracking-widest text-muted">Alamat Email</label>
                    <input 
                        id="email" type="email" v-model="form.email" required autofocus
                        class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-base text-primary placeholder:text-muted/30 focus:border-primary focus:outline-none focus:ring-0 transition-colors rounded-none px-0" 
                        placeholder="student@university.edu" 
                    />
                    <span v-if="form.errors.email" class="text-xs text-pastel-red-text mt-1">{{ form.errors.email }}</span>
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex justify-between items-center">
                        <label for="password" class="font-mono text-[10px] uppercase tracking-widest text-muted">Kata Sandi</label>
                        <Link v-if="canResetPassword" :href="route('password.request')" class="text-[10px] text-muted hover:text-primary transition-colors">Lupa?</Link>
                    </div>
                    <input 
                        id="password" type="password" v-model="form.password" required
                        class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-base text-primary placeholder:text-muted/30 focus:border-primary focus:outline-none focus:ring-0 transition-colors rounded-none px-0" 
                        placeholder="••••••••" 
                    />
                    <span v-if="form.errors.password" class="text-xs text-pastel-red-text mt-1">{{ form.errors.password }}</span>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <input 
                        id="remember" type="checkbox" v-model="form.remember"
                        class="h-4 w-4 rounded-sm border-border-subtle text-primary focus:ring-primary bg-transparent transition-colors cursor-pointer" 
                    />
                    <label for="remember" class="text-sm text-muted cursor-pointer select-none">Ingat saya selama 30 hari</label>
                </div>

                <div class="pt-4">
                    <PremiumButton type="submit" class="w-full" :class="{ 'opacity-50 pointer-events-none': form.processing }">
                        Masuk
                    </PremiumButton>
                </div>
            </form>
        </PremiumCard>

        <div class="mt-8 text-center">
            <p class="text-sm text-muted">
                Belum punya akun? 
                <Link :href="route('register')" class="text-primary font-medium hover:underline underline-offset-4 decoration-border-subtle hover:decoration-primary transition-all">Buat akun sekarang</Link>
            </p>
        </div>
    </GuestLayout>
</template>