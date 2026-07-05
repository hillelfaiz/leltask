<script setup>
defineProps({
    padding: { type: String, default: 'p-6' },
    as: { type: String, default: 'div' }
});
</script>
<template>
    <component :is="as" class="rounded-[1.5rem] bg-shell p-1.5 ring-1 ring-shell-ring block w-full transition-all duration-500 ease-spring">
        <div class="rounded-[calc(1.5rem-0.375rem)] bg-surface ring-1 ring-border-subtle shadow-[inset_0_1px_1px_rgba(255,255,255,0.8)] dark:shadow-[inset_0_1px_1px_rgba(255,255,255,0.05)] h-full transition-colors duration-500" :class="padding">
            <slot />
        </div>
    </component>
</template>