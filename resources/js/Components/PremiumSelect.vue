<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { PhCaretUpDown, PhCheck } from '@phosphor-icons/vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: {
        type: Array,
        required: true,
        // Setiap item: { value: '', label: '', icon?: Component, iconColor?: String, dot?: String }
    },
    placeholder: { type: String, default: 'Pilih opsi...' },
    size: { type: String, default: 'default' }, // 'default' | 'compact'
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const triggerRef = ref(null);
const dropdownRef = ref(null);
const dropdownPosition = ref('bottom'); // 'bottom' atau 'top'

const selectedOption = computed(() => {
    return props.options.find(opt => String(opt.value) === String(props.modelValue));
});

const displayLabel = computed(() => {
    return selectedOption.value ? selectedOption.value.label : props.placeholder;
});

const toggle = () => {
    if (!isOpen.value) {
        calculatePosition();
    }
    isOpen.value = !isOpen.value;
};

const selectOption = (option) => {
    emit('update:modelValue', option.value);
    isOpen.value = false;
};

const calculatePosition = () => {
    if (!triggerRef.value) return;
    const rect = triggerRef.value.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;
    // Jika ruang di bawah < 200px dan ruang di atas lebih luas, tampilkan di atas
    dropdownPosition.value = (spaceBelow < 200 && spaceAbove > spaceBelow) ? 'top' : 'bottom';
};

const handleClickOutside = (e) => {
    if (
        isOpen.value &&
        triggerRef.value && !triggerRef.value.contains(e.target) &&
        dropdownRef.value && !dropdownRef.value.contains(e.target)
    ) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<template>
    <div class="premium-select-wrapper relative">
        <!-- TRIGGER BUTTON -->
        <button
            ref="triggerRef"
            type="button"
            @click="toggle"
            class="premium-select-trigger group relative w-full flex items-center justify-between gap-2 rounded-xl px-3 bg-primary/[0.03] ring-1 ring-border-subtle transition-all duration-300 ease-spring text-left cursor-pointer"
            :class="[
                isOpen ? 'ring-primary/30 bg-primary/[0.05] shadow-[0_0_0_3px_rgba(0,0,0,0.03)]' : 'hover:bg-primary/[0.05] hover:ring-border-subtle',
                size === 'compact' ? 'py-2' : 'py-2.5',
            ]"
        >
            <div class="flex items-center gap-2.5 overflow-hidden min-w-0">
                <!-- Dot Indicator -->
                <span v-if="selectedOption?.dot" class="shrink-0 h-2 w-2 rounded-full" :class="selectedOption.dot"></span>
                <!-- Icon -->
                <component v-if="selectedOption?.icon" :is="selectedOption.icon" :size="15" :weight="selectedOption?.iconWeight || 'regular'" class="shrink-0" :class="selectedOption?.iconColor || 'text-muted'" />
                <!-- Label -->
                <span class="text-sm font-medium truncate" :class="selectedOption ? 'text-primary' : 'text-muted/60'">
                    {{ displayLabel }}
                </span>
            </div>
            <PhCaretUpDown 
                :size="14" 
                weight="bold" 
                class="shrink-0 text-muted/50 transition-all duration-300" 
                :class="isOpen ? 'text-primary/70 rotate-180' : 'group-hover:text-muted'" 
            />
        </button>

        <!-- DROPDOWN PANEL -->
        <Transition
            enter-active-class="transition duration-200 ease-spring"
            enter-from-class="opacity-0 scale-95 translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-1"
        >
            <div
                v-show="isOpen"
                ref="dropdownRef"
                class="absolute z-[200] w-full rounded-xl bg-surface ring-1 ring-border-subtle shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:shadow-[0_8px_30px_rgba(0,0,0,0.3)] backdrop-blur-xl overflow-hidden"
                :class="dropdownPosition === 'top' ? 'bottom-full mb-1.5' : 'top-full mt-1.5'"
            >
                <div class="py-1 max-h-[200px] overflow-y-auto custom-scrollbar-mini">
                    <button
                        v-for="option in options"
                        :key="option.value"
                        type="button"
                        @click="selectOption(option)"
                        class="relative w-full flex items-center gap-2.5 px-3 py-2 text-left transition-all duration-150 cursor-pointer"
                        :class="String(modelValue) === String(option.value) 
                            ? 'bg-primary/[0.06] text-primary' 
                            : 'text-primary/80 hover:bg-primary/[0.04]'"
                    >
                        <!-- Dot -->
                        <span v-if="option.dot" class="shrink-0 h-2 w-2 rounded-full" :class="option.dot"></span>
                        <!-- Icon -->
                        <component v-if="option.icon" :is="option.icon" :size="15" :weight="option.iconWeight || 'regular'" class="shrink-0" :class="option.iconColor || 'text-muted'" />
                        <!-- Label -->
                        <span class="text-sm font-medium truncate flex-1">{{ option.label }}</span>
                        <!-- Check indicator -->
                        <Transition
                            enter-active-class="transition duration-200 ease-spring"
                            enter-from-class="opacity-0 scale-0"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition duration-100"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-0"
                        >
                            <PhCheck 
                                v-if="String(modelValue) === String(option.value)" 
                                :size="14" 
                                weight="bold" 
                                class="shrink-0 text-primary" 
                            />
                        </Transition>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.custom-scrollbar-mini::-webkit-scrollbar { width: 4px; }
.custom-scrollbar-mini::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar-mini::-webkit-scrollbar-thumb { background-color: rgba(150,150,150,0.15); border-radius: 10px; }
.custom-scrollbar-mini::-webkit-scrollbar-thumb:hover { background-color: rgba(150,150,150,0.3); }
</style>
