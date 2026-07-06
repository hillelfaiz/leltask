<script setup>
import { PhCalendarBlank, PhMapPin, PhGraduationCap } from '@phosphor-icons/vue';
import PremiumCard from './PremiumCard.vue';

defineProps({
    activeSemester: {
        type: [Number, String],
        default: null
    },
    activeSemesterCourses: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <PremiumCard v-if="activeSemester" padding="p-5 md:p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-mono text-xs uppercase tracking-widest text-muted">Jadwal Kuliah</h3>
            <PhCalendarBlank :size="14" class="text-muted" />
        </div>
        
        <div class="flex flex-col gap-4">
            <div v-for="day in ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']" :key="day">
                <h4 class="text-xs font-semibold text-primary mb-2 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary/30"></span> {{ day }}
                </h4>
                <div class="flex flex-col gap-2 pl-3 border-l border-border-subtle">
                    <template v-if="activeSemesterCourses.some(c => c.schedule_day === day)">
                        <div v-for="course in activeSemesterCourses.filter(c => c.schedule_day === day).sort((a,b) => (a.schedule_time_start || '').localeCompare(b.schedule_time_start || ''))" :key="course.id" class="flex flex-col gap-1 p-2 rounded-lg hover:bg-primary/5 transition-colors cursor-pointer text-left">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-primary line-clamp-1">{{ course.name }}</span>
                                <span class="text-[10px] font-mono text-muted whitespace-nowrap ml-2 bg-primary/5 px-1.5 py-0.5 rounded">{{ course.schedule_time_start }} - {{ course.schedule_time_end }}</span>
                            </div>
                            <div v-if="course.room || course.lecturer" class="flex items-center gap-3 text-[10px] text-muted">
                                <span v-if="course.room" class="flex items-center gap-1"><PhMapPin :size="10" /> {{ course.room }}</span>
                                <span v-if="course.lecturer" class="flex items-center gap-1"><PhGraduationCap :size="10" /> {{ course.lecturer }}</span>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="text-[10px] italic text-muted/50 p-1">Tidak ada kelas.</div>
                    </template>
                </div>
            </div>
        </div>
    </PremiumCard>
</template>
