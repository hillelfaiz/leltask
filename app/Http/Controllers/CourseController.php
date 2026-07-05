<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'lecturer' => 'nullable|string|max:255',
            'color_theme' => 'nullable|string|max:50',
            'semester' => 'required|integer|min:1|max:8',
            'schedule_day' => 'nullable|string|max:20',
            'schedule_time_start' => 'nullable|string|max:10',
            'schedule_time_end' => 'nullable|string|max:10',
            'room' => 'nullable|string|max:100',
        ], [
            'semester.required' => 'Pilih semester terlebih dahulu.',
        ]);

        $request->user()->courses()->create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Course $course)
    {
        if ($course->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'lecturer' => 'nullable|string|max:255',
            'color_theme' => 'nullable|string|max:50',
            'semester' => 'required|integer|min:1|max:8',
            'schedule_day' => 'nullable|string|max:20',
            'schedule_time_start' => 'nullable|string|max:10',
            'schedule_time_end' => 'nullable|string|max:10',
            'room' => 'nullable|string|max:100',
        ], [
            'semester.required' => 'Pilih semester terlebih dahulu.',
        ]);

        $course->update($validated);

        return redirect()->back();
    }

    public function destroy(Request $request, Course $course)
    {
        if ($course->user_id !== $request->user()->id) {
            abort(403);
        }

        $course->delete();

        return redirect()->back();
    }
}