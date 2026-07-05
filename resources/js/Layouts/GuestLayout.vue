<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-[100dvh] bg-canvas text-primary selection:bg-primary selection:text-inverted flex flex-col relative overflow-hidden transition-colors duration-500">
        
        <!-- Minimalist Navbar -->
        <nav class="absolute top-0 left-0 w-full p-6 md:p-8 flex justify-between items-center z-10">
            <Link href="/" class="flex items-center gap-2 group">
                <div class="h-2 w-2 rounded-full bg-primary transition-transform duration-500 ease-spring group-hover:scale-150"></div>
                <span class="font-mono text-xs font-medium uppercase tracking-[0.2em] text-primary">lelTask</span>
            </Link>
        </nav>
        
        <!-- Main Content Centered -->
        <div class="flex-1 flex flex-col justify-center items-center px-4 sm:px-6 py-24 z-10">
            <div class="w-full max-w-[400px] animate-fade-in-up">
                <slot />
            </div>
        </div>
        
    </div>
</template>

<style scoped>
.animate-fade-in-up {
    animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeInUp {
    from { 
        opacity: 0; 
        transform: translateY(24px); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0); 
    }
}
</style>