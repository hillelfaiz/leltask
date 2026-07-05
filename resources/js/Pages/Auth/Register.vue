<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PremiumCard from '@/Components/PremiumCard.vue';
import PremiumButton from '@/Components/PremiumButton.vue';

const form = useForm({
    name: '', email: '', password: '', password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <div class="mb-10 text-center">
            <h1 class="text-3xl md:text-4xl font-medium tracking-tight text-primary mb-3">Create workspace.</h1>
            <p class="text-muted text-sm">Set up your personal lelTask manager.</p>
        </div>

        <PremiumCard padding="p-8">
            <form @submit.prevent="submit" class="flex flex-col gap-6">
                
                <div class="flex flex-col gap-2">
                    <label for="name" class="font-mono text-[10px] uppercase tracking-widest text-muted">Full Name</label>
                    <input id="name" type="text" v-model="form.name" required autofocus class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-base text-primary placeholder:text-muted/30 focus:border-primary focus:outline-none focus:ring-0 transition-colors rounded-none px-0" placeholder="e.g. Alex Morgan" />
                    <span v-if="form.errors.name" class="text-xs text-pastel-red-text mt-1">{{ form.errors.name }}</span>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="email" class="font-mono text-[10px] uppercase tracking-widest text-muted">Email Address</label>
                    <input id="email" type="email" v-model="form.email" required class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-base text-primary placeholder:text-muted/30 focus:border-primary focus:outline-none focus:ring-0 transition-colors rounded-none px-0" placeholder="student@university.edu" />
                    <span v-if="form.errors.email" class="text-xs text-pastel-red-text mt-1">{{ form.errors.email }}</span>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="password" class="font-mono text-[10px] uppercase tracking-widest text-muted">Password</label>
                    <input id="password" type="password" v-model="form.password" required class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-base text-primary placeholder:text-muted/30 focus:border-primary focus:outline-none focus:ring-0 transition-colors rounded-none px-0" placeholder="••••••••" />
                    <span v-if="form.errors.password" class="text-xs text-pastel-red-text mt-1">{{ form.errors.password }}</span>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="password_confirmation" class="font-mono text-[10px] uppercase tracking-widest text-muted">Confirm Password</label>
                    <input id="password_confirmation" type="password" v-model="form.password_confirmation" required class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-base text-primary placeholder:text-muted/30 focus:border-primary focus:outline-none focus:ring-0 transition-colors rounded-none px-0" placeholder="••••••••" />
                    <span v-if="form.errors.password_confirmation" class="text-xs text-pastel-red-text mt-1">{{ form.errors.password_confirmation }}</span>
                </div>

                <div class="pt-4">
                    <PremiumButton type="submit" class="w-full" :class="{ 'opacity-50 pointer-events-none': form.processing }">Create Account</PremiumButton>
                </div>
            </form>
        </PremiumCard>

        <div class="mt-8 text-center">
            <p class="text-sm text-muted">
                Already have an account? <Link :href="route('login')" class="text-primary font-medium hover:underline underline-offset-4 decoration-border-subtle hover:decoration-primary transition-all">Sign in</Link>
            </p>
        </div>
    </GuestLayout>
</template>