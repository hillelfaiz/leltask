<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
   public function index(Request $request)
    {
        $user = $request->user();

        $tasks = Task::with(['course', 'tags'])
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")
            ->orderBy('due_date', 'asc')
            ->get();

        $notes = \App\Models\Note::with('course')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $courses = Course::where('user_id', $user->id)->get();
        $tags = Tag::where('user_id', $user->id)->get();
        $activeSemester = $user->active_semester;

        return Inertia::render('Dashboard', [
            'tasks' => $tasks,
            'notes' => $notes,
            'courses' => $courses,
            'tags' => $tags,
            'activeSemester' => $activeSemester,
        ]);
    }

    public function updateActiveSemester(Request $request)
    {
        $validated = $request->validate([
            'semester' => 'required|integer|min:1|max:8',
        ]);

        $request->user()->update([
            'active_semester' => $validated['semester']
        ]);

        return redirect()->back();
    }
}