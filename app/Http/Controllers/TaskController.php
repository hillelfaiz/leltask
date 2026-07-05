<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|exists:courses,id',
            'status' => 'required|in:todo,in_progress,review,done',
            'priority' => 'required|in:low,medium,high',
            'due_date' => 'nullable|date',
            'attachment' => 'nullable|file|max:10240', // Max 10MB
        ]);

        $task = $request->user()->tasks()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'course_id' => $validated['course_id'] ?? null,
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'due_date' => $validated['due_date'] ?? null,
        ]);

        // Handle File Upload
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('attachments', 'public');
            $task->update([
                'attachment_path' => $path,
                'attachment_name' => $request->file('attachment')->getClientOriginalName(),
            ]);
        }

        return redirect()->back();
    }

    public function update(Request $request, Task $task)
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
            'attachment' => 'nullable|file|max:10240',
            'remove_attachment' => 'nullable|boolean',
        ]);

        $task->update([
            'title' => $validated['title'] ?? $task->title,
            'description' => $validated['description'] ?? $task->description,
            'course_id' => array_key_exists('course_id', $validated) ? $validated['course_id'] : $task->course_id,
            'status' => $validated['status'] ?? $task->status,
            'priority' => $validated['priority'] ?? $task->priority,
            'due_date' => array_key_exists('due_date', $validated) ? $validated['due_date'] : $task->due_date,
        ]);

        // Handle menghapus file lama jika user meminta
        if ($request->remove_attachment && $task->attachment_path) {
            Storage::disk('public')->delete($task->attachment_path);
            $task->update(['attachment_path' => null, 'attachment_name' => null]);
        }

        // Handle upload file baru (akan menimpa yang lama)
        if ($request->hasFile('attachment')) {
            if ($task->attachment_path) {
                Storage::disk('public')->delete($task->attachment_path);
            }
            $path = $request->file('attachment')->store('attachments', 'public');
            $task->update([
                'attachment_path' => $path,
                'attachment_name' => $request->file('attachment')->getClientOriginalName(),
            ]);
        }

        return redirect()->back();
    }

    public function destroy(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            abort(403);
        }

        // Hapus file fisik jika ada sebelum menghapus data dari database
        if ($task->attachment_path) {
            Storage::disk('public')->delete($task->attachment_path);
        }

        $task->delete();

        return redirect()->back();
    }
}