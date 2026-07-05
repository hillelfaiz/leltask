<script setup>
defineProps({
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' }
});
</script>
<template>
    <button :type="type" class="group relative inline-flex items-center justify-center gap-2 rounded-full px-6 py-2.5 text-sm font-medium transition-all duration-300 ease-spring active:scale-[0.98]"
        :class="[variant === 'primary' ? 'bg-primary text-inverted hover:opacity-90' : 'bg-surface text-primary ring-1 ring-border-subtle hover:bg-canvas']">
        <slot />
        <span v-if="$slots.icon" class="flex h-6 w-6 items-center justify-center rounded-full transition-transform duration-300 group-hover:translate-x-0.5 group-hover:scale-105"
              :class="variant === 'primary' ? 'bg-inverted/10' : 'bg-primary/5'">
            <slot name="icon" />
        </span>
    </button>
</template>