<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import PremiumCard from '@/Components/PremiumCard.vue';
import PremiumButton from '@/Components/PremiumButton.vue';
import PremiumSelect from '@/Components/PremiumSelect.vue';
import { 
    PhCheckCircle, PhCircle, PhPlus, PhBookBookmark, 
    PhCalendarBlank, PhListChecks, PhCaretRight,
    PhFlag, PhTextIndent, PhX, PhChartLineUp, PhDotsThreeCircle,
    PhNotebook, PhTextAa, PhTrash, PhSignOut, PhMagnifyingGlass,
    PhClockCounterClockwise, PhMoon, PhSun, PhPaperclip,
    PhDotsThree, PhPencilSimple, PhMagicWand, PhSpinner,
    PhCaretDown, PhCalendar, PhClock, PhMapPin, PhGraduationCap, PhDownloadSimple
} from '@phosphor-icons/vue';
import axios from 'axios';

const props = defineProps({
    tasks: Array, notes: Array, courses: Array, tags: Array, activeSemester: Number,
});

// --- DARK MODE LOGIC ---
const isDark = ref(false);

const toggleTheme = () => {
    isDark.value = !isDark.value;
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

const closeDropdowns = () => {
    // This function will be attached to document click event
    // When clicking anywhere outside, close dropdowns. 
    // Buttons that open them have @click.stop, so they won't trigger this immediately.
    activeStatusDropdownId.value = null;
    activeMenuDropdownId.value = null;
};

onMounted(() => {
    if (localStorage.theme === 'dark') {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    } else {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    }
    document.addEventListener('click', closeDropdowns);
});

onUnmounted(() => {
    document.removeEventListener('click', closeDropdowns);
});

// --- STATE MANAJEMEN MODAL & TABS ---
const isTaskModalOpen = ref(false);
const isCourseModalOpen = ref(false);
const isNoteModalOpen = ref(false);
const viewNoteData = ref(null);
const viewTaskData = ref(null); 

const activeStatusDropdownId = ref(null);
const activeMenuDropdownId = ref(null);

const activeTab = ref('active'); 
const searchQuery = ref('');
const selectedCourseFilter = ref(''); 

// STATE UNTUK MODE EDIT CATATAN
const isEditingNote = ref(false);
const isDownloadingPdf = ref(false);

// REFERENSI UNTUK VISUAL EDITOR (PENGGANTI TEXTAREA)
const editorCreateRef = ref(null);
const editorEditRef = ref(null);

// --- FORMS ---
const taskForm = useForm({ title: '', description: '', course_id: '', priority: 'medium', due_date: '', status: 'todo', attachment: null });
const courseForm = useForm({ 
    name: '', code: '', semester: '',
    schedule_day: '', schedule_time_start: '', schedule_time_end: '', room: ''
});
const noteForm = useForm({ title: '', content: '', course_id: '' });

const editTaskForm = useForm({ 
    title: '', description: '', course_id: '', priority: 'medium', due_date: '', status: 'todo', attachment: null, remove_attachment: false 
});

const editNoteForm = useForm({ title: '', content: '', course_id: '' });

// --- OPTIONS UNTUK PREMIUM SELECT ---
const activeSemesterCourses = computed(() => {
    return props.courses.filter(c => Number(c.semester) === props.activeSemester);
});

const courseOptions = computed(() => [
    { value: '', label: 'Tanpa Mata Kuliah', icon: PhBookBookmark, iconColor: 'text-muted/40' },
    ...activeSemesterCourses.value.map(c => ({ value: c.id, label: c.code || c.name, icon: PhBookBookmark, iconColor: 'text-primary/60' }))
]);

const noteCourseOptions = computed(() => [
    { value: '', label: 'Catatan Umum (Tanpa Mata Kuliah)', icon: PhNotebook, iconColor: 'text-muted/40' },
    ...activeSemesterCourses.value.map(c => ({ value: c.id, label: c.code || c.name, icon: PhBookBookmark, iconColor: 'text-primary/60' }))
]);

const statusOptions = [
    { value: 'todo', label: 'Belum Dikerjakan', icon: PhCircle, iconColor: 'text-muted', iconWeight: 'light' },
    { value: 'in_progress', label: 'Sedang Dikerjakan (Doing)', icon: PhDotsThreeCircle, iconColor: 'text-pastel-blue-text', iconWeight: 'fill' },
    { value: 'done', label: 'Selesai (Done)', icon: PhCheckCircle, iconColor: 'text-pastel-green-text', iconWeight: 'fill' },
];

const createStatusOptions = [
    { value: 'todo', label: 'Belum Dikerjakan', icon: PhCircle, iconColor: 'text-muted', iconWeight: 'light' },
    { value: 'in_progress', label: 'Proses', icon: PhDotsThreeCircle, iconColor: 'text-pastel-blue-text', iconWeight: 'fill' },
];

const priorityOptions = [
    { value: 'low', label: 'Rendah', icon: PhFlag, iconColor: 'text-pastel-blue-text', iconWeight: 'fill' },
    { value: 'medium', label: 'Sedang', icon: PhFlag, iconColor: 'text-pastel-yellow-text', iconWeight: 'fill' },
    { value: 'high', label: 'Tinggi', icon: PhFlag, iconColor: 'text-pastel-red-text', iconWeight: 'fill' },
];

// --- KONVERTER MARKDOWN KE HTML ---
const markdownToHtml = (markdown) => {
    if (!markdown) return '';
    
    const formatInline = (text) => {
        // Bold: **text**
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Italic: *text* (hanya jika bukan bagian dari bold)
        text = text.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');
        return text;
    };
    
    const lines = markdown.split('\n');
    
    // Pass 1: Parse baris demi baris menjadi struktur data
    const blocks = [];
    let currentNumberedItem = null;
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const trimmed = line.trim();
        
        if (trimmed === '') {
            // Baris kosong: tutup numbered item yang sedang aktif
            if (currentNumberedItem) {
                blocks.push(currentNumberedItem);
                currentNumberedItem = null;
            }
            continue;
        }
        
        // Cek numbered list: "1. **Topik**" atau "1. Teks"
        const numberedMatch = trimmed.match(/^(\d+)\.\s+(.+)$/);
        if (numberedMatch && !line.match(/^\s{3,}/)) {
            // Simpan numbered item sebelumnya jika ada
            if (currentNumberedItem) {
                blocks.push(currentNumberedItem);
            }
            currentNumberedItem = {
                type: 'numbered',
                text: numberedMatch[2],
                children: []
            };
            continue;
        }
        
        // Cek sub-bullet: "   - teks" atau "  - teks"
        const bulletMatch = trimmed.match(/^[-•]\s+(.+)$/);
        if (bulletMatch && (line.startsWith('   ') || line.startsWith('  ') || line.startsWith('\t'))) {
            if (currentNumberedItem) {
                // Sub-bullet milik numbered item terakhir
                currentNumberedItem.children.push(bulletMatch[1]);
            } else {
                // Sub-bullet tanpa parent — jadikan unordered list biasa
                blocks.push({ type: 'bullet', text: bulletMatch[1] });
            }
            continue;
        }
        
        // Top-level bullet (tanpa indent)
        if (bulletMatch && !line.startsWith(' ')) {
            if (currentNumberedItem) {
                blocks.push(currentNumberedItem);
                currentNumberedItem = null;
            }
            blocks.push({ type: 'bullet', text: bulletMatch[1] });
            continue;
        }
        
        // Baris biasa (paragraf)
        if (currentNumberedItem) {
            blocks.push(currentNumberedItem);
            currentNumberedItem = null;
        }
        blocks.push({ type: 'paragraph', text: trimmed });
    }
    
    // Jangan lupa simpan item terakhir
    if (currentNumberedItem) {
        blocks.push(currentNumberedItem);
    }
    
    // Pass 2: Render blok menjadi HTML
    let html = '';
    let inOl = false;
    let inUl = false;
    
    for (let i = 0; i < blocks.length; i++) {
        const block = blocks[i];
        
        if (block.type === 'numbered') {
            // Tutup UL jika sedang buka
            if (inUl) { html += '</ul>'; inUl = false; }
            // Buka OL jika belum
            if (!inOl) { html += '<ol>'; inOl = true; }
            
            html += '<li>' + formatInline(block.text);
            
            // Render sub-bullets sebagai nested UL di dalam LI
            if (block.children.length > 0) {
                html += '<ul>';
                for (const child of block.children) {
                    html += '<li>' + formatInline(child) + '</li>';
                }
                html += '</ul>';
            }
            
            html += '</li>';
            
        } else if (block.type === 'bullet') {
            // Tutup OL jika sedang buka
            if (inOl) { html += '</ol>'; inOl = false; }
            // Buka UL jika belum
            if (!inUl) { html += '<ul>'; inUl = true; }
            
            html += '<li>' + formatInline(block.text) + '</li>';
            
        } else if (block.type === 'paragraph') {
            // Tutup semua list yang terbuka
            if (inUl) { html += '</ul>'; inUl = false; }
            if (inOl) { html += '</ol>'; inOl = false; }
            
            html += '<p>' + formatInline(block.text) + '</p>';
        }
    }
    
    // Tutup list yang masih terbuka
    if (inUl) html += '</ul>';
    if (inOl) html += '</ol>';
    
    return html;
};

// --- LOGIKA AI FORMATTING (DIPERBAIKI) ---
const isAILoading = ref(false);

const formatNoteWithAI = async (targetForm, isEditMode = false) => {
    if (!targetForm.content) return;
    
    isAILoading.value = true;
    try {
        // Hapus tag HTML sebelum dikirim agar AI memahami teks mentahnya
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = targetForm.content;
        const plainTextForAI = tempDiv.innerText || tempDiv.textContent || '';

        const response = await axios.post(route('notes.ai'), {
            content: plainTextForAI
        });
        
        const aiMarkdown = response.data.formatted_content;
        
        // Konversi Markdown dari AI menjadi HTML terstruktur
        const htmlContent = markdownToHtml(aiMarkdown);
        
        targetForm.content = htmlContent;
        
        if (isEditMode && editorEditRef.value) {
            editorEditRef.value.innerHTML = htmlContent;
        } else if (!isEditMode && editorCreateRef.value) {
            editorCreateRef.value.innerHTML = htmlContent;
        }

    } catch (error) {
        const errorMessage = error.response?.data?.error || 'Gagal terhubung ke AI. Pastikan API Key valid.';
        alert(errorMessage);
    } finally {
        isAILoading.value = false;
    }
};

// --- SUBMIT LOGIC ---
const submitTask = () => { 
    if (!taskForm.title) return; 
    taskForm.post(route('tasks.store'), { 
        preserveScroll: true,
        onSuccess: () => { taskForm.reset(); isTaskModalOpen.value = false; activeTab.value = 'active'; }
    }); 
};

const submitCourse = () => { 
    if (!courseForm.name) return; 
    courseForm.post(route('courses.store'), { 
        preserveScroll: true,
        onSuccess: () => { courseForm.reset(); isCourseModalOpen.value = false; }
    }); 
};

const activateSemester = (semesterValue) => {
    router.put(route('semesters.activate'), { semester: semesterValue }, {
        preserveScroll: true,
    });
};

const downloadPdf = () => {
    if (!viewNoteData.value) return;
    isDownloadingPdf.value = true;
    
    // Create an iframe to hold the print content cleanly
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = 'none';
    document.body.appendChild(iframe);

    // Write content with basic styles optimized for printing
    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`
        <html>
            <head>
                <title>${viewNoteData.value.title}</title>
                <style>
                    body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; padding: 40px; color: #111; line-height: 1.75; font-size: 15px; }
                    h1 { font-size: 24px; font-weight: 600; margin-bottom: 8px; }
                    .course-badge { display: inline-block; font-size: 11px; padding: 4px 8px; background: #f3f4f6; border-radius: 9999px; margin-bottom: 24px; font-weight: 500; border: 1px solid #e5e7eb; }
                    .content h2 { font-size: 20px; margin-top: 28px; font-weight: 600; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 8px; }
                    .content h3 { font-size: 17.5px; margin-top: 24px; font-weight: 600; margin-bottom: 6px; }
                    .content p { margin-bottom: 16px; }
                    .content ol { list-style-type: decimal; padding-left: 24px; margin-top: 8px; margin-bottom: 16px; }
                    .content ol ol { list-style-type: lower-alpha; }
                    .content ul { list-style-type: disc; padding-left: 24px; margin-top: 8px; margin-bottom: 16px; }
                    .content ul ul { list-style-type: circle; }
                    .content li { margin-bottom: 8px; padding-left: 4px; }
                    .content strong, .content b { font-weight: 600; }
                    .content em, .content i { font-style: italic; }
                    .content blockquote { border-left: 3px solid #e5e7eb; padding-left: 16px; margin: 16px 0; color: #787774; font-style: italic; }
                    .content code { font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.85em; background: #f3f4f6; padding: 2px 5px; border-radius: 4px; }
                    .content pre { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; overflow-x: auto; margin: 16px 0; }
                    .content pre code { background: transparent; padding: 0; }
                    .content table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px; }
                    .content th, .content td { border: 1px solid #e5e7eb; padding: 8px 12px; text-align: left; }
                    .content th { font-weight: 600; background: #f9fafb; }
                    .content hr { border: none; border-top: 1px solid #e5e7eb; margin: 24px 0; }
                    .content > div { margin-bottom: 6px; }
                </style>
            </head>
            <body>
                <h1>${viewNoteData.value.title}</h1>
                ${viewNoteData.value.course ? `<div class="course-badge">${viewNoteData.value.course.code || viewNoteData.value.course.name}</div>` : ''}
                <div class="content">${renderedNoteContent.value}</div>
            </body>
        </html>
    `);
    doc.close();

    // Give it a moment to render, then print
    setTimeout(() => {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        document.body.removeChild(iframe);
        isDownloadingPdf.value = false;
    }, 500);
};

const submitNote = () => {  
    if (!noteForm.title) return; 
    noteForm.post(route('notes.store'), { 
        preserveScroll: true,
        onSuccess: () => { 
            noteForm.reset(); 
            if (editorCreateRef.value) editorCreateRef.value.innerHTML = '';
            isNoteModalOpen.value = false; 
        }
    }); 
};

const submitEditTask = () => {
    if (!editTaskForm.title) return;
    editTaskForm.transform((data) => ({
        ...data,
        _method: 'put',
    })).post(route('tasks.update', viewTaskData.value.id), {
        preserveScroll: true,
        onSuccess: () => { viewTaskData.value = null; editTaskForm.reset(); }
    });
};

const submitEditNote = () => {
    if (!editNoteForm.title) return;
    editNoteForm.put(route('notes.update', viewNoteData.value.id), {
        preserveScroll: true,
        onSuccess: () => { 
            viewNoteData.value.title = editNoteForm.title;
            viewNoteData.value.content = editNoteForm.content;
            viewNoteData.value.course_id = editNoteForm.course_id;
            viewNoteData.value.course = props.courses.find(c => c.id === editNoteForm.course_id);
            isEditingNote.value = false; 
        }
    });
};

// --- ACTIONS ---
const deleteTask = (id) => { 
    if (confirm('Hapus tugas ini secara permanen?')) {
        router.delete(route('tasks.destroy', id), { preserveScroll: true });
        viewTaskData.value = null;
    }
};

const deleteNote = (id) => { 
    if (confirm('Hapus catatan ini?')) { 
        router.delete(route('notes.destroy', id), { preserveScroll: true }); 
        viewNoteData.value = null; 
    } 
};

const deleteCourse = (id) => {
    if (confirm('Hapus mata kuliah ini? (Tugas dan catatan yang terkait tidak akan terhapus, hanya kehilangan label mata kuliahnya).')) {
        router.delete(route('courses.destroy', id), { preserveScroll: true });
        if (selectedCourseFilter.value === id) selectedCourseFilter.value = '';
    }
};

const openNoteViewer = (note) => { 
    viewNoteData.value = note; 
    isEditingNote.value = false; 
    editNoteForm.title = note.title;
    editNoteForm.content = note.content || '';
    editNoteForm.course_id = note.course_id || '';
};

const enableNoteEditMode = async () => {
    isEditingNote.value = true;
    await nextTick();
    if (editorEditRef.value) {
        editorEditRef.value.innerHTML = editNoteForm.content || '';
    }
};

const openTaskViewer = (task) => {
    activeStatusDropdownId.value = null;
    activeMenuDropdownId.value = null;
    
    viewTaskData.value = task;
    editTaskForm.title = task.title;
    editTaskForm.description = task.description || '';
    editTaskForm.course_id = task.course_id || '';
    editTaskForm.priority = task.priority;
    editTaskForm.due_date = task.due_date ? task.due_date.substring(0, 10) : ''; 
    editTaskForm.status = task.status;
    editTaskForm.attachment = null;
    editTaskForm.remove_attachment = false;
};

const toggleStatusDropdown = (taskId) => {
    activeMenuDropdownId.value = activeMenuDropdownId.value === taskId ? null : taskId;
    activeStatusDropdownId.value = null;
};

const updateTaskStatus = (task, newStatus) => {
    activeStatusDropdownId.value = null; 
    activeMenuDropdownId.value = null;
    
    if (task.status !== newStatus) {
        router.put(route('tasks.update', task.id), { status: newStatus }, { preserveScroll: true });
    }
};

const cycleStatus = (task) => {
    let newStatus = 'todo';
    if (task.status === 'todo') newStatus = 'in_progress';
    else if (task.status === 'in_progress') newStatus = 'done';
    router.put(route('tasks.update', task.id), { status: newStatus }, { preserveScroll: true });
};

const undoTask = (task) => { router.put(route('tasks.update', task.id), { status: 'todo' }, { preserveScroll: true }); };

// --- COMPUTED ---
const activeTasks = computed(() => {
    return props.tasks.filter(t => {
        // Filter by semester
        if (t.course && Number(t.course.semester) !== props.activeSemester) return false;
        if (t.status === 'done') return false;
        if (selectedCourseFilter.value && t.course_id !== selectedCourseFilter.value) return false;
        if (searchQuery.value) {
            return t.title.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                   (t.description && t.description.toLowerCase().includes(searchQuery.value.toLowerCase()));
        }
        return true;
    });
});

const historyTasks = computed(() => {
    return props.tasks.filter(t => {
        if (t.course && Number(t.course.semester) !== props.activeSemester) return false;
        if (t.status !== 'done') return false;
        if (selectedCourseFilter.value && t.course_id !== selectedCourseFilter.value) return false;
        return true;
    });
});

const filteredNotes = computed(() => {
    return props.notes.filter(n => {
        // Filter by semester
        if (n.course && Number(n.course.semester) !== props.activeSemester) return false;
        if (selectedCourseFilter.value && n.course_id !== selectedCourseFilter.value) return false;
        if (searchQuery.value) {
            return n.title.toLowerCase().includes(searchQuery.value.toLowerCase());
        }
        return true;
    });
});

// FIX: Menampilkan konten catatan dengan format yang konsisten.
// Jika konten sudah berupa HTML (dari AI atau contenteditable), tampilkan langsung.
// Jika konten masih berupa plain text/markdown (dari data lama), konversi dulu.
const renderedNoteContent = computed(() => {
    if (!viewNoteData.value || !viewNoteData.value.content) return '<p class="text-muted">Tidak ada konten yang ditulis.</p>';
    
    const content = viewNoteData.value.content;
    
    // Cek apakah konten sudah berupa HTML (mengandung tag HTML)
    const hasHtmlTags = /<(p|ol|ul|li|strong|em|br|div|span)\b/i.test(content);
    
    if (hasHtmlTags) {
        // Konten sudah HTML, tampilkan langsung
        return content;
    } else {
        // Konten masih plain text/markdown, konversi ke HTML
        return markdownToHtml(content);
    }
});

const formatDate = (dateString) => { 
    if (!dateString) return null; 
    return new Date(dateString).toLocaleDateString('id-ID', { month: 'short', day: 'numeric' }); 
};
const formatFullDate = () => {
    return new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
};

const getPriorityConfig = (priority) => {
    switch(priority) {
        case 'high': return { icon: PhFlag, color: 'text-pastel-red-text', bg: 'bg-pastel-red-bg', label: 'Tinggi' };
        case 'medium': return { icon: PhFlag, color: 'text-pastel-yellow-text', bg: 'bg-pastel-yellow-bg', label: 'Sedang' };
        case 'low': return { icon: PhFlag, color: 'text-pastel-blue-text', bg: 'bg-pastel-blue-bg', label: 'Rendah' };
        default: return { icon: PhFlag, color: 'text-muted', bg: 'bg-primary/5', label: 'Tidak ada' };
    }
};

const getStatusConfig = (status) => {
    switch(status) {
        case 'todo': return { icon: PhCircle, color: 'text-muted', bg: 'bg-primary/5', label: 'Belum Dikerjakan', weight: 'light' };
        case 'in_progress': return { icon: PhDotsThreeCircle, color: 'text-pastel-blue-text', bg: 'bg-pastel-blue-bg', label: 'Proses', weight: 'fill' };
        case 'done': return { icon: PhCheckCircle, color: 'text-pastel-green-text', bg: 'bg-pastel-green-bg', label: 'Selesai', weight: 'fill' };
        default: return { icon: PhCircle, color: 'text-muted', bg: 'bg-primary/5', label: 'Belum Dikerjakan', weight: 'light' };
    }
};
</script>

<template>
    <Head title="Ruang Kerja" />

    <!-- Empty spacer where overlay used to be, to keep template structure intact -->

    <div class="min-h-screen bg-canvas text-primary selection:bg-primary/20 transition-colors duration-500 pb-20 md:pb-0">
        
        <!-- Navbar -->
        <nav class="sticky top-0 z-[50] w-full bg-canvas/80 px-4 md:px-6 py-3 md:py-4 backdrop-blur-xl border-b border-border-subtle transition-colors duration-500">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="h-2 w-2 rounded-full bg-primary"></div>
                    <span class="font-mono text-sm font-medium uppercase tracking-widest text-primary">lelTask</span>
                </div>
                <div class="flex items-center gap-4 md:gap-6">
                    <div class="hidden sm:block text-xs md:text-sm text-muted">
                        {{ formatFullDate() }}
                    </div>
                    <button @click="toggleTheme" class="text-muted hover:text-primary transition-colors">
                        <PhSun v-if="isDark" :size="18" weight="bold" />
                        <PhMoon v-else :size="18" weight="bold" />
                    </button>
                    <Link :href="route('logout')" method="post" as="button" class="group flex items-center gap-2 text-muted hover:text-pastel-red-text transition-colors">
                        <span class="text-xs font-medium opacity-0 -translate-x-2 transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0 hidden sm:block">Keluar</span>
                        <PhSignOut :size="18" weight="bold" />
                    </Link>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-6xl px-4 md:px-6 py-6 md:py-12 relative z-30">
            
            <header class="mb-8 max-w-2xl">
                <h1 class="text-4xl font-medium tracking-tight text-primary md:text-5xl">Kerjain sekarang, takut besok lupa.</h1>
                <p class="mt-4 text-lg text-muted leading-relaxed">
                    Saya memiliki <strong class="font-medium text-primary">{{ activeTasks.length }} tugas aktif</strong> dan <strong class="font-medium text-primary">{{ filteredNotes.length }} catatan</strong>.
                </p>
            </header>

            <div class="mb-10 flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between bg-surface p-2 rounded-2xl ring-1 ring-border-subtle shadow-[0_2px_10px_rgba(0,0,0,0.02)] transition-colors duration-500 relative z-30">
                <div class="relative w-full sm:max-w-xs shrink-0">
                    <PhMagnifyingGlass class="absolute left-3 top-1/2 -translate-y-1/2 text-muted" :size="16" />
                    <input v-model="searchQuery" type="text" placeholder="Cari tugas atau catatan..." class="w-full bg-transparent border-none rounded-xl pl-9 pr-4 py-2 text-sm focus:ring-0 text-primary placeholder:text-muted/50" />
                </div>
                <div class="flex items-center gap-1 overflow-x-auto w-full sm:w-auto px-2 sm:px-0 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                    <button @click="selectedCourseFilter = ''" class="px-4 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-all duration-300" :class="selectedCourseFilter === '' ? 'bg-primary text-inverted shadow-md' : 'bg-transparent text-muted hover:bg-primary/5'">Semua Mata Kuliah</button>
                    <button v-for="course in activeSemesterCourses" :key="course.id" @click="selectedCourseFilter = course.id" class="px-4 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-all duration-300" :class="selectedCourseFilter === course.id ? 'bg-primary text-inverted shadow-md' : 'bg-transparent text-muted hover:bg-primary/5'">{{ course.code || course.name }}</button>
                </div>
            </div>

            <div class="flex flex-col md:grid md:grid-cols-12 gap-8 md:gap-12 align-top">
                
                <div class="flex flex-col gap-10 md:col-span-8 order-2 md:order-1">
                    
                    <!-- SECTION: TASKS -->
                    <section>
                        <div class="flex items-center justify-between border-b border-border-subtle pb-4 mb-4 relative z-30">
                            <div class="flex items-center gap-6">
                                <button @click="activeTab = 'active'" class="font-mono text-xs uppercase tracking-widest transition-colors relative" :class="activeTab === 'active' ? 'text-primary font-semibold' : 'text-muted hover:text-primary'">
                                    Tugas
                                    <span v-if="activeTab === 'active'" class="absolute -bottom-[17px] left-0 w-full h-0.5 bg-primary rounded-t-full"></span>
                                </button>
                                <button @click="activeTab = 'history'" class="font-mono text-xs uppercase tracking-widest transition-colors relative" :class="activeTab === 'history' ? 'text-primary font-semibold' : 'text-muted hover:text-primary'">
                                    Riwayat
                                    <span v-if="activeTab === 'history'" class="absolute -bottom-[17px] left-0 w-full h-0.5 bg-primary rounded-t-full"></span>
                                </button>
                            </div>
                            <button v-if="activeTab === 'active'" @click="isTaskModalOpen = true" class="flex items-center gap-2 text-sm font-medium text-primary hover:opacity-70 transition-opacity">
                                <PhPlus :size="16" /> Tugas Baru
                            </button>
                        </div>

                        <!-- TAB CONTENT: ACTIVE -->
                        <div v-show="activeTab === 'active'" @click="activeStatusDropdownId = null; activeMenuDropdownId = null" class="flex flex-col gap-2 animate-fade-in-up relative z-[45]">
                            <div v-if="activeTasks.length === 0" class="py-12 text-center text-muted border border-dashed border-border-subtle rounded-2xl">
                                <PhListChecks :size="48" weight="light" class="mx-auto mb-4 opacity-20" />
                                <p v-if="searchQuery || selectedCourseFilter">Tidak ada tugas yang sesuai dengan pencarian Anda.</p>
                                <p v-else>Tidak ada tugas.</p>
                            </div>

                            <div v-for="task in activeTasks" :key="task.id" @click.stop="activeStatusDropdownId || activeMenuDropdownId ? (activeStatusDropdownId = null, activeMenuDropdownId = null) : openTaskViewer(task)" class="group relative flex items-start gap-4 rounded-[1rem] p-4 transition-all duration-300 hover:bg-surface hover:shadow-[0_4px_20px_rgba(0,0,0,0.03)] ring-1 ring-border-subtle/50 hover:ring-border-subtle bg-primary/[0.01] cursor-pointer" :style="{ zIndex: (activeStatusDropdownId === task.id || activeMenuDropdownId === task.id) ? 50 : 10 }">
                                
                                <div class="relative shrink-0 mt-1" @click.stop>
                                    <button type="button" @click.stop="activeStatusDropdownId = activeStatusDropdownId === task.id ? null : task.id; activeMenuDropdownId = null" class="relative z-10 transition-transform active:scale-90" :class="getStatusConfig(task.status).color">
                                        <component :is="getStatusConfig(task.status).icon" :size="24" :weight="getStatusConfig(task.status).weight" />
                                    </button>
                                    
                                    <div v-if="activeStatusDropdownId === task.id" class="absolute left-0 top-full mt-2 w-48 rounded-xl bg-surface ring-1 ring-border-subtle shadow-2xl z-[70] py-1 flex flex-col animate-fade-in-up" style="animation-duration: 0.2s">
                                        <button type="button" @click.stop.prevent="updateTaskStatus(task, 'todo')" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-primary/5 transition-colors w-full text-left cursor-pointer">
                                            <PhCircle :size="16" class="text-muted" /> <span class="text-primary font-medium">Belum Dikerjakan</span>
                                        </button>
                                        <button type="button" @click.stop.prevent="updateTaskStatus(task, 'in_progress')" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-primary/5 transition-colors w-full text-left cursor-pointer">
                                            <PhDotsThreeCircle :size="16" class="text-pastel-blue-text" weight="fill" /> <span class="text-primary font-medium">Sedang Dikerjakan</span>
                                        </button>
                                        <button type="button" @click.stop.prevent="updateTaskStatus(task, 'done')" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-primary/5 transition-colors w-full text-left cursor-pointer">
                                            <PhCheckCircle :size="16" class="text-pastel-green-text" weight="fill" /> <span class="text-primary font-medium">Selesai</span>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="flex w-full flex-col gap-2 overflow-hidden">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-center gap-2">
                                            <span class="text-base font-medium text-primary leading-snug">{{ task.title }}</span>
                                            <span v-if="task.status === 'in_progress'" class="rounded-full px-2 py-0.5 text-[9px] font-mono uppercase tracking-widest bg-pastel-blue-bg text-pastel-blue-text">Proses</span>
                                        </div>
                                        <span class="shrink-0 flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-mono uppercase tracking-wider" :class="[getPriorityConfig(task.priority).bg, getPriorityConfig(task.priority).color]">
                                            <component :is="getPriorityConfig(task.priority).icon" :size="10" weight="fill" />
                                            {{ getPriorityConfig(task.priority).label }}
                                        </span>
                                    </div>
                                    <p v-if="task.description" class="text-sm text-muted line-clamp-2 leading-relaxed break-words">{{ task.description }}</p>
                                    
                                    <div class="flex flex-wrap items-center gap-3 text-xs mt-1">
                                        <span v-if="task.course" class="flex items-center gap-1.5 text-muted bg-primary/5 px-2 py-1 rounded-md">
                                            <PhBookBookmark :size="14" /> {{ task.course.code || task.course.name }}
                                        </span>
                                        <span v-if="task.due_date" class="flex items-center gap-1.5 px-2 py-1 rounded-md" :class="new Date(task.due_date) < new Date() ? 'bg-pastel-red-bg text-pastel-red-text' : 'bg-primary/5 text-muted'">
                                            <PhCalendarBlank :size="14" /> {{ formatDate(task.due_date) }}
                                        </span>
                                        <span v-if="task.attachment_name" class="flex items-center gap-1.5 px-2 py-1 rounded-md bg-primary/5 text-muted max-w-[150px] truncate">
                                            <PhPaperclip :size="14" class="shrink-0" /> <span class="truncate">{{ task.attachment_name }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="relative shrink-0 ml-2 mt-0.5">
                                    <button type="button" @click.stop="toggleStatusDropdown(task.id)" class="p-1.5 text-muted hover:text-primary hover:bg-primary/5 rounded-lg transition-colors">
                                        <PhDotsThree :size="24" weight="bold" />
                                    </button>
                                    
                                    <div v-show="activeMenuDropdownId === task.id" class="absolute right-0 top-full mt-2 w-48 rounded-xl bg-surface ring-1 ring-border-subtle shadow-2xl z-[70] py-2 flex flex-col">
                                        <div class="px-4 py-1 text-[10px] font-mono uppercase tracking-widest text-muted mb-1">Ubah Status</div>
                                        <button type="button" @click.stop.prevent="updateTaskStatus(task, 'todo')" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-primary/5 transition-colors w-full text-left cursor-pointer">
                                            <PhCircle :size="16" class="text-muted" /> <span class="text-primary font-medium">Belum Dikerjakan</span>
                                        </button>
                                        <button type="button" @click.stop.prevent="updateTaskStatus(task, 'in_progress')" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-primary/5 transition-colors w-full text-left cursor-pointer">
                                            <PhDotsThreeCircle :size="16" class="text-pastel-blue-text" weight="fill" /> <span class="text-primary font-medium">Sedang Dikerjakan</span>
                                        </button>
                                        <button type="button" @click.stop.prevent="updateTaskStatus(task, 'done')" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-primary/5 transition-colors w-full text-left cursor-pointer">
                                            <PhCheckCircle :size="16" class="text-pastel-green-text" weight="fill" /> <span class="text-primary font-medium">Selesai</span>
                                        </button>
                                        
                                        <div class="h-px bg-border-subtle my-1.5 mx-2"></div>
                                        
                                        <button type="button" @click.stop="openTaskViewer(task)" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-primary/5 transition-colors w-full text-left cursor-pointer">
                                            <PhPencilSimple :size="16" class="text-muted" /> <span class="text-primary font-medium">Edit Detail</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB CONTENT: HISTORY -->
                        <div v-show="activeTab === 'history'" class="flex flex-col gap-2 animate-fade-in-up relative z-30">
                            <div v-if="historyTasks.length === 0" class="py-12 text-center text-muted border border-dashed border-border-subtle rounded-2xl">
                                <PhClockCounterClockwise :size="48" weight="light" class="mx-auto mb-4 opacity-20" />
                                <p v-if="searchQuery || selectedCourseFilter">Tidak ada tugas selesai yang sesuai.</p>
                                <p v-else>Belum ada tugas yang diselesaikan.</p>
                            </div>

                            <div v-for="task in historyTasks" :key="task.id" @click="openTaskViewer(task)" class="group relative flex items-start justify-between gap-4 rounded-[1rem] p-4 transition-all duration-300 hover:bg-surface hover:shadow-[0_4px_20px_rgba(0,0,0,0.03)] ring-1 ring-border-subtle/50 hover:ring-border-subtle bg-primary/[0.01] cursor-pointer z-10">
                                <div class="flex items-start gap-4 opacity-60 overflow-hidden w-full">
                                    <div class="mt-1 shrink-0 text-pastel-green-text"><PhCheckCircle :size="24" weight="fill" /></div>
                                    <div class="flex flex-col gap-1 w-full">
                                        <span class="text-base font-medium text-primary line-through break-words">{{ task.title }}</span>
                                        <div class="flex flex-wrap items-center gap-3 text-xs">
                                            <span v-if="task.course" class="text-muted">{{ task.course.code || task.course.name }}</span>
                                            <span class="text-muted">Selesai pada {{ formatDate(task.updated_at) }}</span>
                                            <span v-if="task.attachment_name" class="flex items-center gap-1 text-muted"><PhPaperclip :size="12"/> Ada lampiran</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                                    <button @click.stop="undoTask(task)" class="p-2 text-muted hover:text-primary bg-primary/5 hover:bg-primary/10 rounded-full transition-colors" title="Kembalikan ke Papan Aktif"><PhClockCounterClockwise :size="16" /></button>
                                    <button @click.stop="deleteTask(task.id)" class="p-2 text-muted hover:text-pastel-red-text bg-primary/5 hover:bg-pastel-red-bg rounded-full transition-colors" title="Hapus permanen"><PhTrash :size="16" /></button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- SECTION: STUDY JOURNAL -->
                    <section class="relative z-30">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-border-subtle pb-4 mb-4 gap-4">
                            <h2 class="font-mono text-xs uppercase tracking-widest text-muted">Catatan</h2>
                            <button @click="isNoteModalOpen = true" class="flex items-center gap-2 text-sm font-medium text-primary hover:opacity-70 transition-opacity">
                                <PhPlus :size="16" /> Catatan Baru
                            </button>
                        </div>

                        <div v-if="filteredNotes.length === 0" class="py-12 text-center text-muted border border-dashed border-border-subtle rounded-2xl">
                            <PhNotebook :size="48" weight="light" class="mx-auto mb-4 opacity-20" />
                            <p v-if="searchQuery || selectedCourseFilter">Tidak ada catatan yang sesuai.</p>
                            <p v-else>Tidak ada catatan.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div v-for="note in filteredNotes" :key="note.id" @click="openNoteViewer(note)" class="group cursor-pointer flex flex-col gap-3 rounded-[1rem] p-5 transition-all duration-300 hover:bg-surface hover:shadow-[0_4px_20px_rgba(0,0,0,0.03)] ring-1 ring-border-subtle/50 hover:ring-border-subtle bg-primary/[0.01]">
                                <div class="flex items-start justify-between">
                                    <h3 class="text-base font-medium text-primary line-clamp-1">{{ note.title }}</h3>
                                    <PhTextAa :size="18" class="text-muted opacity-50 group-hover:opacity-100 transition-opacity shrink-0" />
                                </div>
                                <p class="text-sm text-muted line-clamp-3 leading-relaxed flex-1 break-words">{{ note.content ? note.content.replace(/<[^>]*>?/gm, '') : 'Tidak ada konten yang ditulis.' }}</p>
                                <div class="flex items-center justify-between mt-2 pt-3 border-t border-border-subtle/50">
                                    <span class="text-[10px] font-mono text-muted uppercase tracking-wider">{{ formatDate(note.created_at) }}</span>
                                    <span v-if="note.course" class="text-[10px] font-medium text-primary bg-primary/5 px-2 py-0.5 rounded-full">{{ note.course.code || note.course.name }}</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- KOLOM KANAN (SIDEBAR) -->
                <aside class="flex flex-col gap-6 w-full md:col-span-4 relative z-30 order-1 md:order-2">
                    <!-- SEMESTER SWITCHER -->
                    <div class="relative w-full">
                        <PremiumSelect 
                            :modelValue="activeSemester"
                            @update:modelValue="activateSemester"
                            :options="[1,2,3,4,5,6,7,8].map(s => ({ value: s, label: `Semester ${s}`, icon: PhGraduationCap, iconColor: 'text-primary' }))"
                            placeholder="Pilih Semester"
                            size="compact"
                        />
                    </div>

                    <!-- MATA KULIAH CARD -->
                    <PremiumCard padding="p-5 md:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-mono text-xs uppercase tracking-widest text-muted">Mata Kuliah</h3>
                        </div>
                        <div v-if="!activeSemester" class="text-sm text-muted py-2">Pilih semester terlebih dahulu.</div>
                        <div v-else-if="activeSemesterCourses.length === 0" class="text-sm text-muted py-2">Belum ada mata kuliah di semester ini.</div>
                        <ul v-else class="flex flex-col gap-2">
                            <li v-for="course in activeSemesterCourses" :key="course.id" @click="selectedCourseFilter = selectedCourseFilter === course.id ? '' : course.id" class="flex items-center justify-between group cursor-pointer p-2.5 -mx-2.5 rounded-lg transition-colors" :class="selectedCourseFilter === course.id ? 'bg-primary/5 ring-1 ring-primary/10' : 'hover:bg-primary/5'">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium" :class="selectedCourseFilter === course.id ? 'text-primary' : 'text-primary/80'">{{ course.name }}</span>
                                    <span class="text-xs text-muted">{{ course.code }}</span>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <button @click.stop="deleteCourse(course.id)" class="opacity-0 group-hover:opacity-100 text-muted hover:text-pastel-red-text transition-all" title="Hapus Mata Kuliah">
                                        <PhTrash :size="14" />
                                    </button>
                                    <div class="h-2 w-2 rounded-full transition-all" :class="selectedCourseFilter === course.id ? 'bg-primary' : 'bg-transparent group-hover:bg-primary/20'"></div>
                                </div>
                            </li>
                        </ul>
                        <div v-if="activeSemester" class="mt-4 border-t border-border-subtle pt-4">
                            <button @click="isCourseModalOpen = true" class="flex items-center gap-2 text-sm font-medium text-muted hover:text-primary transition-colors">
                                <PhPlus :size="16" /> Tambah mata kuliah
                            </button>
                        </div>
                    </PremiumCard>

                    <!-- JADWAL MINGGUAN CARD -->
                    <PremiumCard v-if="activeSemester" padding="p-5 md:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-mono text-xs uppercase tracking-widest text-muted">Jadwal Kuliah</h3>
                            <PhCalendarBlank :size="14" class="text-muted" />
                        </div>
                        
                        <!-- JADWAL (SELALU TAMPIL SEMUA HARI) -->
                        <div class="flex flex-col gap-4">
                            <div v-for="day in ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']" :key="day">
                                <h4 class="text-xs font-semibold text-primary mb-2 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary/30"></span> {{ day }}
                                </h4>
                                <div class="flex flex-col gap-2 pl-3 border-l border-border-subtle">
                                    <!-- JIKA ADA MATA KULIAH -->
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
                                    <!-- JIKA KOSONG -->
                                    <template v-else>
                                        <div class="text-[10px] italic text-muted/50 p-1">Tidak ada kelas.</div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </PremiumCard>
                </aside>
            </div>
        </main>

        <!-- FLOATING ACTION BUTTON (MOBILE) -->
        <button @click="isTaskModalOpen = true" class="md:hidden fixed bottom-6 right-6 z-50 h-14 w-14 rounded-full bg-primary text-inverted shadow-[0_8px_30px_rgba(0,0,0,0.3)] flex items-center justify-center transition-transform active:scale-95">
            <PhPlus :size="24" />
        </button>

        <!-- ========================================== -->
        <!-- MODALS (Semua modal di-set z-[100])        -->
        <!-- ========================================== -->

        <!-- MODAL: VIEW & EDIT TASK -->
        <div v-if="viewTaskData" class="fixed inset-0 z-[100] flex items-end md:items-center justify-center px-0 md:px-4 pb-0 md:py-6">
            <div @click="viewTaskData = null" class="absolute inset-0 bg-canvas/60 backdrop-blur-md transition-opacity"></div>
            <div class="relative w-full max-w-2xl bg-surface md:bg-shell p-0 md:p-1.5 md:ring-1 md:ring-shell-ring shadow-2xl rounded-t-[1.5rem] md:rounded-[1.5rem] animate-fade-in-up mt-auto md:my-auto max-h-[90vh] flex flex-col">
                <div class="md:rounded-[calc(1.5rem-0.375rem)] bg-surface md:ring-1 md:ring-border-subtle p-6 md:p-8 flex-1 overflow-y-auto custom-scrollbar rounded-t-[1.5rem]">
                    
                    <div class="flex items-center justify-between mb-6 shrink-0 border-b border-border-subtle pb-4">
                        <div class="flex items-center gap-3">
                            <span class="font-mono text-[10px] uppercase tracking-widest text-muted">Detail Tugas</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <button @click="deleteTask(viewTaskData.id)" class="text-muted hover:text-pastel-red-text transition-colors" title="Hapus Tugas"><PhTrash :size="18" /></button>
                            <button @click="viewTaskData = null" class="text-muted hover:text-primary transition-colors"><PhX :size="20" /></button>
                        </div>
                    </div>

                    <form @submit.prevent="submitEditTask" class="flex flex-col gap-6">
                        <div class="flex flex-col gap-1.5">
                            <input v-model="editTaskForm.title" type="text" required placeholder="Judul Tugas..." 
                                class="w-full border-0 border-b border-border-subtle bg-transparent py-3 text-2xl font-medium text-primary placeholder:text-muted/30 focus:border-primary focus:ring-0 px-0" />
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="font-mono text-[10px] uppercase tracking-widest text-muted flex items-center gap-1"><PhTextIndent :size="12"/> Deskripsi</label>
                            <textarea v-model="editTaskForm.description" rows="3" placeholder="Tambahkan detail, tautan, atau catatan..." 
                                class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-sm text-primary placeholder:text-muted/30 focus:border-primary focus:ring-0 px-0 resize-none"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-5">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Mata Kuliah</label>
                                <PremiumSelect v-model="editTaskForm.course_id" :options="courseOptions" placeholder="Pilih Mata Kuliah" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Status</label>
                                <PremiumSelect v-model="editTaskForm.status" :options="statusOptions" placeholder="Pilih Status" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-5">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Prioritas</label>
                                <PremiumSelect v-model="editTaskForm.priority" :options="priorityOptions" placeholder="Pilih Prioritas" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Tenggat Waktu</label>
                                <input v-model="editTaskForm.due_date" type="date" 
                                    class="w-full rounded-xl bg-primary/[0.03] ring-1 ring-border-subtle px-3 py-2.5 text-sm font-medium text-primary focus:ring-primary/30 focus:bg-primary/[0.05] focus:shadow-[0_0_0_3px_rgba(0,0,0,0.03)] cursor-pointer transition-all duration-300 ease-spring [&::-webkit-calendar-picker-indicator]:opacity-50 [&::-webkit-calendar-picker-indicator]:cursor-pointer hover:[&::-webkit-calendar-picker-indicator]:opacity-100 dark:[&::-webkit-calendar-picker-indicator]:invert" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 pt-2">
                            <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Lampiran Berkas</label>
                            <div v-if="viewTaskData.attachment_name && !editTaskForm.remove_attachment" class="flex items-center justify-between bg-primary/5 p-3 rounded-xl border border-border-subtle">
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <div class="p-2 bg-surface rounded-lg shrink-0"><PhPaperclip :size="16" class="text-primary"/></div>
                                    <a :href="viewTaskData.attachment_url" target="_blank" class="text-sm font-medium text-primary hover:underline truncate">{{ viewTaskData.attachment_name }}</a>
                                </div>
                                <button type="button" @click="editTaskForm.remove_attachment = true" class="text-xs font-medium text-pastel-red-text hover:underline shrink-0 ml-4">Hapus</button>
                            </div>
                            <div v-if="!viewTaskData.attachment_name || editTaskForm.remove_attachment" class="relative group">
                                <input type="file" id="edit-file-upload" @change="e => editTaskForm.attachment = e.target.files[0]" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                                <div class="flex items-center justify-center gap-2 w-full border border-dashed border-border-subtle rounded-xl py-4 bg-primary/[0.02] group-hover:bg-primary/5 transition-colors">
                                    <PhPaperclip :size="18" class="text-muted" />
                                    <span class="text-sm font-medium" :class="editTaskForm.attachment ? 'text-primary' : 'text-muted'">
                                        {{ editTaskForm.attachment ? editTaskForm.attachment.name : 'Klik untuk melampirkan berkas (Maks 10MB)' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end gap-3 shrink-0 border-t border-border-subtle pt-4">
                            <button type="button" @click="viewTaskData = null" class="px-4 py-2 text-sm font-medium text-muted hover:text-primary">Batal</button>
                            <PremiumButton type="submit" :disabled="editTaskForm.processing">Simpan Perubahan</PremiumButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: CREATE TASK -->
        <div v-if="isTaskModalOpen" class="fixed inset-0 z-[100] flex items-end md:items-center justify-center px-0 md:px-4 pb-0 md:py-6">
            <div @click="isTaskModalOpen = false" class="absolute inset-0 bg-canvas/60 backdrop-blur-md transition-opacity"></div>
            <div class="relative z-10 w-full max-w-lg bg-surface md:bg-shell p-0 md:p-1.5 md:ring-1 md:ring-shell-ring shadow-2xl rounded-t-[1.5rem] md:rounded-[1.5rem] animate-fade-in-up mt-auto md:my-auto max-h-[90vh] flex flex-col pointer-events-auto select-auto">
                <div class="md:rounded-[calc(1.5rem-0.375rem)] bg-surface md:ring-1 md:ring-border-subtle p-6 md:p-8 flex-1 overflow-y-auto custom-scrollbar rounded-t-[1.5rem]">
                    <div class="flex items-center justify-between mb-6 border-b border-border-subtle pb-4">
                        <h2 class="font-mono text-[10px] uppercase tracking-widest text-muted">Tugas Baru</h2>
                        <button @click="isTaskModalOpen = false" class="text-muted hover:text-primary transition-colors"><PhX :size="20" /></button>
                    </div>
                    <form @submit.prevent="submitTask" class="flex flex-col gap-5">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Judul Tugas</label>
                            <input v-model="taskForm.title" type="text" required class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-base font-medium text-primary focus:border-primary focus:ring-0 px-0" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Deskripsi</label>
                            <textarea v-model="taskForm.description" rows="2" class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-sm text-primary focus:border-primary focus:ring-0 px-0 resize-none"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-5">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Mata Kuliah</label>
                                <PremiumSelect v-model="taskForm.course_id" :options="courseOptions" placeholder="Pilih Mata Kuliah" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Status</label>
                                <PremiumSelect v-model="taskForm.status" :options="createStatusOptions" placeholder="Pilih Status" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-5">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Prioritas</label>
                                <PremiumSelect v-model="taskForm.priority" :options="priorityOptions" placeholder="Pilih Prioritas" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Tenggat Waktu</label>
                                <input v-model="taskForm.due_date" type="date" class="w-full rounded-xl bg-primary/[0.03] ring-1 ring-border-subtle px-3 py-2.5 text-sm font-medium text-primary focus:ring-primary/30 focus:bg-primary/[0.05] focus:shadow-[0_0_0_3px_rgba(0,0,0,0.03)] cursor-pointer transition-all duration-300 ease-spring [&::-webkit-calendar-picker-indicator]:opacity-50 [&::-webkit-calendar-picker-indicator]:cursor-pointer hover:[&::-webkit-calendar-picker-indicator]:opacity-100 dark:[&::-webkit-calendar-picker-indicator]:invert" />
                            </div>
                        </div>
                        
                        <div class="relative group mt-2">
                            <input type="file" id="create-file-upload" @change="e => taskForm.attachment = e.target.files[0]" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                            <div class="flex items-center justify-center gap-2 w-full border border-dashed border-border-subtle rounded-xl py-3 bg-primary/[0.02] group-hover:bg-primary/5 transition-colors">
                                <PhPaperclip :size="16" class="text-muted" />
                                <span class="text-xs font-medium" :class="taskForm.attachment ? 'text-primary' : 'text-muted'">
                                    {{ taskForm.attachment ? taskForm.attachment.name : 'Lampirkan berkas (Opsional)' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end gap-3">
                            <button type="button" @click="isTaskModalOpen = false" class="px-4 py-2 text-sm font-medium text-muted hover:text-primary">Batal</button>
                            <PremiumButton type="submit" :disabled="taskForm.processing">Simpan Tugas</PremiumButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: CREATE JOURNAL ENTRY -->
        <div v-if="isNoteModalOpen" class="fixed inset-0 z-[100] flex items-end md:items-center justify-center px-0 md:px-4 pb-0 md:py-6">
            <div @click="isNoteModalOpen = false" class="absolute inset-0 bg-canvas/60 backdrop-blur-md transition-opacity"></div>
            <div class="relative w-full max-w-4xl bg-surface md:bg-shell p-0 md:p-1.5 md:ring-1 md:ring-shell-ring shadow-2xl rounded-t-[1.5rem] md:rounded-[1.5rem] animate-fade-in-up mt-auto md:my-auto max-h-[95vh] md:max-h-[90vh] flex flex-col">
                <div class="md:rounded-[calc(1.5rem-0.375rem)] bg-surface md:ring-1 md:ring-border-subtle p-5 md:p-8 flex flex-col min-h-[75vh] md:min-h-[75vh] h-full rounded-t-[1.5rem]">
                    <div class="flex items-center justify-between mb-6 shrink-0 border-b border-border-subtle pb-4">
                        <h2 class="font-mono text-[10px] uppercase tracking-widest text-muted">Catatan Baru</h2>
                        <button @click="isNoteModalOpen = false" class="text-muted hover:text-primary transition-colors"><PhX :size="20" /></button>
                    </div>
                    <form @submit.prevent="submitNote" class="flex flex-col gap-6 overflow-y-auto pr-2 custom-scrollbar flex-1">
                        <div class="flex flex-col gap-1.5">
                            <input v-model="noteForm.title" type="text" required placeholder="Judul Dokumen..." class="w-full border-0 border-b border-border-subtle bg-transparent py-3 text-3xl font-medium text-primary placeholder:text-muted/30 focus:border-primary focus:ring-0 px-0" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Mata Kuliah Terkait</label>
                            <PremiumSelect v-model="noteForm.course_id" :options="noteCourseOptions" placeholder="Pilih Mata Kuliah" />
                        </div>
                        <div class="flex flex-col gap-2 flex-1 relative mt-2">
                            <div class="flex items-center justify-between">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Isi Catatan</label>
                                <button type="button" @click="formatNoteWithAI(noteForm, false)" :disabled="isAILoading || !noteForm.content" 
                                    class="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-full transition-colors"
                                    :class="(!noteForm.content || isAILoading) ? 'text-muted bg-primary/5 cursor-not-allowed' : 'text-pastel-blue-text bg-pastel-blue-bg hover:opacity-80'">
                                    <PhSpinner v-if="isAILoading" :size="14" class="animate-spin" />
                                    <PhMagicWand v-else :size="14" weight="bold" />
                                    {{ isAILoading ? 'AI Sedang Merapihkan...' : '✨ Rapihkan dengan AI' }}
                                </button>
                            </div>
                            
                            <div 
                                ref="editorCreateRef"
                                contenteditable="true"
                                data-placeholder="Ketik catatan disini"
                                @input="e => noteForm.content = e.target.innerHTML"
                                class="note-content flex-1 border-0 bg-transparent py-2 focus:ring-0 px-0 resize-none outline-none overflow-y-auto min-h-[150px]"
                                :class="{'opacity-50 cursor-not-allowed': isAILoading}"
                                :contenteditable="!isAILoading"
                            ></div>
                        </div>
                        <div class="mt-2 flex justify-end gap-3 shrink-0 border-t border-border-subtle pt-4">
                            <button type="button" @click="isNoteModalOpen = false" class="px-4 py-2 text-sm font-medium text-muted hover:text-primary">Batal</button>
                            <PremiumButton type="submit" :disabled="noteForm.processing || isAILoading">Simpan Catatan</PremiumButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: VIEW & EDIT NOTE -->
        <div v-if="viewNoteData" class="fixed inset-0 z-[100] flex items-end md:items-center justify-center px-0 md:px-4 pb-0 md:py-6">
            <div @click="viewNoteData = null" class="absolute inset-0 bg-canvas/60 backdrop-blur-md transition-opacity"></div>
            
            <div class="relative w-full max-w-4xl bg-surface md:bg-shell p-0 md:p-1.5 md:ring-1 md:ring-shell-ring shadow-2xl rounded-t-[1.5rem] md:rounded-[1.5rem] animate-fade-in-up mt-auto md:my-auto max-h-[95vh] md:max-h-[90vh] flex flex-col">
                <div class="md:rounded-[calc(1.5rem-0.375rem)] bg-surface md:ring-1 md:ring-border-subtle p-5 md:p-8 flex flex-col min-h-[75vh] md:min-h-[75vh] h-full rounded-t-[1.5rem]">
                    
                    <div class="flex items-center justify-between mb-6 shrink-0 border-b border-border-subtle pb-4">
                        <div class="flex items-center gap-2 md:gap-3">
                            <span class="font-mono text-[10px] uppercase tracking-widest text-muted">
                                {{ isEditingNote ? 'Mode Edit' : 'Jurnal Belajar' }} ({{ formatDate(viewNoteData.created_at) }})
                            </span>
                        </div>
                        <div class="flex items-center gap-4">
                            <button v-if="!isEditingNote" @click="downloadPdf" :disabled="isDownloadingPdf" class="flex items-center gap-2 text-xs font-medium bg-pastel-blue-bg/30 text-pastel-blue-text hover:bg-pastel-blue-bg/60 px-3 py-1.5 rounded-full transition-colors disabled:opacity-50">
                                <PhSpinner v-if="isDownloadingPdf" :size="14" class="animate-spin" />
                                <PhDownloadSimple v-else :size="14" weight="bold" /> PDF
                            </button>
                            <button v-if="!isEditingNote" @click="enableNoteEditMode" class="flex items-center gap-2 text-xs font-medium bg-primary/5 hover:bg-primary/10 text-primary px-3 py-1.5 rounded-full transition-colors">
                                <PhPencilSimple :size="14" /> Edit Catatan
                            </button>
                            <button @click="deleteNote(viewNoteData.id)" class="text-muted hover:text-pastel-red-text transition-colors" title="Hapus Catatan"><PhTrash :size="18" /></button>
                            <button @click="viewNoteData = null" class="text-muted hover:text-primary transition-colors"><PhX :size="20" /></button>
                        </div>
                    </div>

                    <!-- MODE BACA (VIEW) -->
                    <div v-if="!isEditingNote" class="flex flex-col flex-1 overflow-y-auto pr-2 custom-scrollbar">
                        <div id="note-print-container" class="bg-surface text-primary p-2">
                            <div class="flex items-center gap-3 mb-6">
                                <h1 class="text-3xl font-medium text-primary leading-tight">{{ viewNoteData.title }}</h1>
                                <span v-if="viewNoteData.course" class="text-[10px] font-medium text-primary bg-primary/5 px-2 py-1 rounded-full shrink-0 mt-1 border border-border-subtle">
                                    {{ viewNoteData.course.code || viewNoteData.course.name }}
                                </span>
                            </div>
                            
                            <!-- RENDER HTML MENTAH -->
                            <div class="note-content" v-html="renderedNoteContent"></div>
                        </div>
                    </div>

                    <!-- MODE EDIT (FORM) -->
                    <form v-else @submit.prevent="submitEditNote" class="flex flex-col gap-6 overflow-y-auto pr-2 custom-scrollbar flex-1">
                        <div class="flex flex-col gap-1.5">
                            <input v-model="editNoteForm.title" type="text" required placeholder="Judul Dokumen..." class="w-full border-0 border-b border-border-subtle bg-transparent py-3 text-3xl font-medium text-primary placeholder:text-muted/30 focus:border-primary focus:ring-0 px-0" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Mata Kuliah Terkait</label>
                            <PremiumSelect v-model="editNoteForm.course_id" :options="noteCourseOptions" placeholder="Pilih Mata Kuliah" />
                        </div>
                        
                        <div class="flex flex-col gap-2 flex-1 relative mt-2">
                            <div class="flex items-center justify-between">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Isi Catatan</label>
                                <button type="button" @click="formatNoteWithAI(editNoteForm, true)" :disabled="isAILoading || !editNoteForm.content" 
                                    class="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-full transition-colors"
                                    :class="(!editNoteForm.content || isAILoading) ? 'text-muted bg-primary/5 cursor-not-allowed' : 'text-pastel-blue-text bg-pastel-blue-bg hover:opacity-80'">
                                    <PhSpinner v-if="isAILoading" :size="14" class="animate-spin" />
                                    <PhMagicWand v-else :size="14" weight="bold" />
                                    {{ isAILoading ? 'AI Sedang Merapihkan...' : '✨ Rapihkan dengan AI' }}
                                </button>
                            </div>
                            
                            <div 
                                ref="editorEditRef"
                                contenteditable="true"
                                data-placeholder="Ketik tambahan catatan kasar Anda di sini..."
                                @input="e => editNoteForm.content = e.target.innerHTML"
                                class="note-content flex-1 border-0 bg-transparent py-2 focus:ring-0 px-0 resize-none outline-none overflow-y-auto min-h-[150px]"
                                :class="{'opacity-50 cursor-not-allowed': isAILoading}"
                                :contenteditable="!isAILoading"
                            ></div>
                        </div>

                        <div class="mt-2 flex justify-end gap-3 shrink-0 border-t border-border-subtle pt-4">
                            <button type="button" @click="isEditingNote = false" class="px-4 py-2 text-sm font-medium text-muted hover:text-primary">Batal Edit</button>
                            <PremiumButton type="submit" :disabled="editNoteForm.processing || isAILoading">Simpan Perubahan</PremiumButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: CREATE COURSE -->
        <div v-if="isCourseModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center px-4 py-6">
            <div @click="isCourseModalOpen = false" class="absolute inset-0 bg-canvas/60 backdrop-blur-md transition-opacity"></div>
            <div class="relative w-full max-w-sm rounded-[1.5rem] bg-shell p-1.5 ring-1 ring-shell-ring shadow-2xl animate-fade-in-up my-auto">
                <div class="rounded-[calc(1.5rem-0.375rem)] bg-surface ring-1 ring-border-subtle p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-medium text-primary">Tambah Mata Kuliah</h2>
                        <button @click="isCourseModalOpen = false" class="text-muted hover:text-primary transition-colors"><PhX :size="18" /></button>
                    </div>
                    <form @submit.prevent="submitCourse" class="flex flex-col gap-5 overflow-y-auto custom-scrollbar max-h-[70vh] pr-2">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Semester</label>
                            <PremiumSelect 
                                v-model="courseForm.semester" 
                                :options="[1,2,3,4,5,6,7,8].map(s => ({ value: s, label: `Semester ${s}`, icon: PhGraduationCap, iconColor: 'text-primary' }))" 
                                placeholder="Pilih Semester" 
                            />
                            <span v-if="courseForm.errors.semester" class="text-xs text-pastel-red-text">{{ courseForm.errors.semester }}</span>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Kode Mata Kuliah</label>
                            <input v-model="courseForm.code" type="text" required placeholder="Contoh: SI101" class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-sm font-medium text-primary focus:border-primary focus:ring-0 px-0" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Nama Mata Kuliah</label>
                            <input v-model="courseForm.name" type="text" required placeholder="Contoh: Sistem Informasi" class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-sm text-primary focus:border-primary focus:ring-0 px-0" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Dosen (Opsional)</label>
                            <input v-model="courseForm.lecturer" type="text" placeholder="Nama Dosen" class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-sm text-primary focus:border-primary focus:ring-0 px-0" />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 border-t border-border-subtle pt-4 mt-2">
                            <div class="col-span-2">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-primary flex items-center gap-1.5"><PhCalendarBlank :size="12"/> Jadwal & Ruangan</label>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Hari</label>
                                <PremiumSelect 
                                    v-model="courseForm.schedule_day" 
                                    :options="['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'].map(d => ({ value: d, label: d }))" 
                                    placeholder="Pilih Hari" 
                                />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Ruangan</label>
                                <input v-model="courseForm.room" type="text" placeholder="Contoh: A-301" class="w-full border-0 border-b border-border-subtle bg-transparent py-2 text-sm text-primary focus:border-primary focus:ring-0 px-0" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Jam Mulai</label>
                                <input v-model="courseForm.schedule_time_start" type="time" class="w-full rounded-xl bg-primary/[0.03] ring-1 ring-border-subtle px-3 py-2 text-sm font-medium text-primary focus:ring-primary/30 focus:bg-primary/[0.05] focus:shadow-[0_0_0_3px_rgba(0,0,0,0.03)] cursor-pointer transition-all duration-300 ease-spring [&::-webkit-calendar-picker-indicator]:opacity-50 [&::-webkit-calendar-picker-indicator]:cursor-pointer hover:[&::-webkit-calendar-picker-indicator]:opacity-100 dark:[&::-webkit-calendar-picker-indicator]:invert" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[10px] uppercase tracking-widest text-muted">Jam Selesai</label>
                                <input v-model="courseForm.schedule_time_end" type="time" class="w-full rounded-xl bg-primary/[0.03] ring-1 ring-border-subtle px-3 py-2 text-sm font-medium text-primary focus:ring-primary/30 focus:bg-primary/[0.05] focus:shadow-[0_0_0_3px_rgba(0,0,0,0.03)] cursor-pointer transition-all duration-300 ease-spring [&::-webkit-calendar-picker-indicator]:opacity-50 [&::-webkit-calendar-picker-indicator]:cursor-pointer hover:[&::-webkit-calendar-picker-indicator]:opacity-100 dark:[&::-webkit-calendar-picker-indicator]:invert" />
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-border-subtle">
                            <PremiumButton type="submit" class="w-full" :disabled="courseForm.processing">Tambah Mata Kuliah</PremiumButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.animate-fade-in-up { animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(150,150,150,0.2); border-radius: 10px; }

/* CSS UNTUK FAKE PLACEHOLDER DI CONTENTEDITABLE */
[contenteditable]:empty:before {
    content: attr(data-placeholder);
    color: inherit;
    opacity: 0.3;
    pointer-events: none;
    display: block;
}

/* CSS PROSE (WYSIWYG) */
.prose {
    white-space: normal !important;
    word-wrap: break-word !important;
}
.prose p, .prose div {
    display: block !important;
    margin-bottom: 0.75rem !important;
}
.prose p:last-child {
    margin-bottom: 0 !important;
}
.prose ul {
    display: block !important;
    list-style-type: disc !important;
    padding-left: 1.5rem !important;
    margin-top: 0.25rem !important;
    margin-bottom: 0.75rem !important;
}
.prose ol {
    display: block !important;
    list-style-type: decimal !important;
    padding-left: 1.5rem !important;
    margin-top: 0.5rem !important;
    margin-bottom: 0.75rem !important;
}
.prose li {
    display: list-item !important;
    margin-bottom: 0.5rem !important;
    line-height: 1.7 !important;
}
/* Nested ul di dalam ol (sub-bullet dalam item bernomor) */
.prose ol > li > ul,
.prose li > ul {
    margin-top: 0.35rem !important;
    margin-bottom: 0.35rem !important;
    padding-left: 1.25rem !important;
}
.prose ol > li > ul > li,
.prose li > ul > li {
    margin-bottom: 0.3rem !important;
}
.prose strong {
    font-weight: 600 !important;
    color: inherit !important;
}
</style>