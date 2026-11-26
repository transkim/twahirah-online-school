<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\View\View;

class CourseController extends Controller
{
    /**
     * List every course with its levels, teachers and how many students study it now.
     */
    public function index(): View
    {
        return view('admin.courses', [
            'courses' => Course::with('levels')
                ->withCount(['enrolments as active_students' => fn ($query) => $query->where('status', 'active')])
                ->withCount('teacherCourses as teachers')
                ->orderBy('name')->orderBy('mode')->get(),
        ]);
    }
}
