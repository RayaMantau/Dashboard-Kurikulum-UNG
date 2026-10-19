<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseIndexRequest;
use App\Http\Requests\SaveCourseRequest;
use App\Models\Course;
use App\Services\CourseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(CourseIndexRequest $request): View
    {
        $filters = $request->validated();
        $query = Course::query()->where('study_program_id', $request->user()->study_program_id);

        if (filled($filters['search'] ?? null)) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn (Builder $query): Builder => $query->whereLike('code', $search)->orWhereLike('name', $search));
        }

        return view('admin.courses.index', [
            'courses' => $query->orderBy('code')->orderBy('id')->paginate(15)->withQueryString(),
            'studyProgram' => $request->user()->studyProgram,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.courses.create', ['course' => new Course, 'studyProgram' => $request->user()->studyProgram]);
    }

    public function store(SaveCourseRequest $request, CourseService $service): RedirectResponse
    {
        $course = $service->save($request->user(), $request->courseData());

        return redirect()->route('admin.courses.edit', $course)->with('success', 'Mata kuliah berhasil ditambahkan.');
    }

    public function edit(Request $request, Course $course): View
    {
        return view('admin.courses.edit', ['course' => $course, 'studyProgram' => $request->user()->studyProgram]);
    }

    public function update(SaveCourseRequest $request, Course $course, CourseService $service): RedirectResponse
    {
        $service->save($request->user(), $request->courseData(), $course);

        return redirect()->route('admin.courses.edit', $course)->with('success', 'Mata kuliah berhasil diperbarui.');
    }
}
