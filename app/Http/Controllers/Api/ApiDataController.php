<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Note;
use App\Models\Task;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ApiDataController extends Controller
{
    // ========== DASHBOARD ==========
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $tasks = Task::with(['course', 'tags'])
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")
            ->orderBy('due_date', 'asc')
            ->get();

        $notes = Note::with('course')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $courses = Course::where('user_id', $user->id)->get();
        $tags = Tag::where('user_id', $user->id)->get();

        return response()->json([
            'tasks' => $tasks,
            'notes' => $notes,
            'courses' => $courses,
            'tags' => $tags,
            'active_semester' => $user->active_semester,
        ]);
    }

    // ========== TASKS ==========
    public function storeTask(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|exists:courses,id',
            'status' => 'required|in:todo,in_progress,review,done',
            'priority' => 'required|in:low,medium,high',
            'due_date' => 'nullable|date',
        ]);

        $task = $request->user()->tasks()->create($validated);

        return response()->json($task->load(['course', 'tags']), 201);
    }

    public function updateTask(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|exists:courses,id',
            'status' => 'sometimes|required|in:todo,in_progress,review,done',
            'priority' => 'sometimes|required|in:low,medium,high',
            'due_date' => 'nullable|date',
        ]);

        $task->update($validated);

        return response()->json($task->fresh()->load(['course', 'tags']));
    }

    public function destroyTask(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            abort(403);
        }
        $task->delete();
        return response()->json(['message' => 'Tugas berhasil dihapus.']);
    }

    // ========== NOTES ==========
    public function storeNote(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'course_id' => 'nullable|exists:courses,id',
        ]);

        $note = $request->user()->notes()->create($validated);

        return response()->json($note->load('course'), 201);
    }

    public function updateNote(Request $request, Note $note)
    {
        if ($note->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'course_id' => 'nullable|exists:courses,id',
        ]);

        $note->update($validated);

        return response()->json($note->fresh()->load('course'));
    }

    public function destroyNote(Request $request, Note $note)
    {
        if ($note->user_id !== $request->user()->id) {
            abort(403);
        }
        $note->delete();
        return response()->json(['message' => 'Catatan berhasil dihapus.']);
    }

    public function formatNoteWithAI(Request $request)
    {
        $request->validate(['content' => 'required|string']);

        $apiKey = env('GROQ_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'API Key Groq belum diatur.'], 500);
        }

        try {
            $systemPrompt = <<<'PROMPT'
Kamu adalah asisten akademik profesional yang bertugas merapikan catatan mentah mahasiswa menjadi catatan belajar yang terstruktur, rapi, dan mudah dipahami.

ATURAN FORMAT OUTPUT (WAJIB DIIKUTI):
1. Gunakan penomoran untuk setiap topik/konsep utama. Format: "1. **Nama Topik**" diikuti penjelasannya.
2. Jika di dalam satu topik ada sub-poin, gunakan bullet points dengan tanda "- " (strip spasi).
3. Gunakan **bold** (diapit dua bintang) untuk istilah penting, nama konsep, atau kata kunci.
4. Setiap topik/konsep harus dipisahkan oleh satu baris kosong agar mudah dibaca.
5. Perbaiki tata bahasa, ejaan, dan struktur kalimat agar profesional.
6. Gunakan Bahasa Indonesia yang baku dan akademis.

ATURAN YANG DILARANG:
- JANGAN menambahkan teks pembuka atau penutup.
- JANGAN menggunakan heading dengan tanda pagar (#).
- JANGAN mengubah makna asli dari catatan.

Langsung berikan hasil akhirnya saja.
PROMPT;

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => 'Rapikan catatan berikut ini:\n\n' . $request->input('content')]
                    ],
                    'temperature' => 0.3,
                ]);

            if ($response->successful()) {
                return response()->json([
                    'formatted_content' => $response->json('choices.0.message.content')
                ]);
            }

            return response()->json(['error' => 'Gagal menghubungi AI.'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    // ========== COURSES ==========
    public function storeCourse(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'semester' => 'required|integer|min:1|max:8',
            'schedule_day' => 'nullable|string|max:20',
            'schedule_time_start' => 'nullable|string|max:10',
            'schedule_time_end' => 'nullable|string|max:10',
            'room' => 'nullable|string|max:100',
        ]);

        $course = $request->user()->courses()->create($validated);

        return response()->json($course, 201);
    }

    public function updateCourse(Request $request, Course $course)
    {
        if ($course->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'semester' => 'required|integer|min:1|max:8',
            'schedule_day' => 'nullable|string|max:20',
            'schedule_time_start' => 'nullable|string|max:10',
            'schedule_time_end' => 'nullable|string|max:10',
            'room' => 'nullable|string|max:100',
        ]);

        $course->update($validated);

        return response()->json($course->fresh());
    }

    public function destroyCourse(Request $request, Course $course)
    {
        if ($course->user_id !== $request->user()->id) {
            abort(403);
        }
        $course->delete();
        return response()->json(['message' => 'Mata kuliah berhasil dihapus.']);
    }

    // ========== SEMESTER ==========
    public function updateActiveSemester(Request $request)
    {
        $validated = $request->validate([
            'semester' => 'required|integer|min:1|max:8',
        ]);

        $request->user()->update(['active_semester' => $validated['semester']]);

        return response()->json(['active_semester' => $validated['semester']]);
    }
}
