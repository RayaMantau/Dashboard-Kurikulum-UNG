<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSemesterRequest;
use App\Models\Curriculum;
use App\Models\Semester;
use App\Services\Curriculum\SemesterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SemesterController extends Controller
{
    public function index(Request $request, Curriculum $curriculum, SemesterService $service): View
    {
        $service->authorize($request->user(), $curriculum);

        return view('admin.semesters.index', [
            'curriculum' => $curriculum,
            'semesters' => $curriculum->semesters()->withExists('curriculumCourses')->orderBy('semester_number')->get(),
        ]);
    }

    public function create(Request $request, Curriculum $curriculum, SemesterService $service): View
    {
        $service->authorize($request->user(), $curriculum, null, true);

        return view('admin.semesters.create', ['curriculum' => $curriculum, 'semester' => new Semester]);
    }

    public function edit(Request $request, Curriculum $curriculum, Semester $semester, SemesterService $service): View
    {
        $service->authorize($request->user(), $curriculum, $semester, true);

        return view('admin.semesters.edit', compact('curriculum', 'semester'));
    }

    public function store(SaveSemesterRequest $request, Curriculum $curriculum, SemesterService $service): RedirectResponse
    {
        $service->save($request->user(), $curriculum, $request->validated());

        return redirect()->route('admin.curricula.semesters.index', $curriculum)->with('success', 'Semester berhasil ditambahkan.');
    }

    public function update(SaveSemesterRequest $request, Curriculum $curriculum, Semester $semester, SemesterService $service): RedirectResponse
    {
        $service->save($request->user(), $curriculum, $request->validated(), $semester);

        return redirect()->route('admin.curricula.semesters.index', $curriculum)->with('success', 'Semester berhasil diperbarui.');
    }

    public function destroy(Request $request, Curriculum $curriculum, Semester $semester, SemesterService $service): RedirectResponse
    {
        $service->delete($request->user(), $curriculum, $semester);

        return redirect()->route('admin.curricula.semesters.index', $curriculum)->with('success', 'Semester berhasil dihapus.');
    }
}
