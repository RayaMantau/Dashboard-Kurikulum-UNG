<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCurriculumCourseRequest;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Semester;
use App\Services\Curriculum\CurriculumCourseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurriculumCourseController extends Controller
{
    public function index(Request $request, Curriculum $curriculum, CurriculumCourseService $service): View
    {
        $service->authorize($request->user(), $curriculum);
        $selectedSemester = $this->selectedSemester($request, $curriculum);

        return view('admin.curriculum-courses.index', [
            'curriculum' => $curriculum->loadMissing('studyProgram'),
            'selectedSemester' => $selectedSemester,
            'semesters' => $curriculum->semesters()->orderBy('semester_number')
                ->when($selectedSemester, fn (Builder $query): Builder => $query->whereKey($selectedSemester->id))
                ->with(['curriculumCourses' => fn (HasMany $query): HasMany => $query->where('curriculum_id', $curriculum->id)->orderBy('course_code')->orderBy('id')])->get(),
        ]);
    }

    public function create(Request $request, Curriculum $curriculum, CurriculumCourseService $service): View
    {
        $service->authorize($request->user(), $curriculum, null, true);
        $selectedSemester = $this->selectedSemester($request, $curriculum);

        return view('admin.curriculum-courses.create', [
            ...$this->formData($curriculum), 'assignment' => new CurriculumCourse(['semester_id' => $selectedSemester?->id]),
            'selectedSemester' => $selectedSemester,
            'courses' => Course::query()->where('study_program_id', $request->user()->study_program_id)->orderBy('code')->orderBy('id')->get(['id', 'code', 'name']),
        ]);
    }

    public function edit(Request $request, Curriculum $curriculum, CurriculumCourse $curriculumCourse, CurriculumCourseService $service): View
    {
        $service->authorize($request->user(), $curriculum, $curriculumCourse, true);

        return view('admin.curriculum-courses.edit', [...$this->formData($curriculum), 'assignment' => $curriculumCourse,
            'selectedSemester' => $this->selectedSemester($request, $curriculum)]);
    }

    public function store(SaveCurriculumCourseRequest $request, Curriculum $curriculum, CurriculumCourseService $service): RedirectResponse
    {
        $assignment = $service->save($request->user(), $curriculum, $request->validated());

        return redirect()->route('admin.curricula.courses.index', [$curriculum, 'semester' => $request->query->has('semester') ? $assignment->semester_id : null])->with('success', 'Mata kuliah berhasil ditambahkan ke kurikulum.');
    }

    public function update(SaveCurriculumCourseRequest $request, Curriculum $curriculum, CurriculumCourse $curriculumCourse, CurriculumCourseService $service): RedirectResponse
    {
        $assignment = $service->save($request->user(), $curriculum, $request->validated(), $curriculumCourse);

        return redirect()->route('admin.curricula.courses.index', [$curriculum, 'semester' => $request->query->has('semester') ? $assignment->semester_id : null])->with('success', 'Mata kuliah kurikulum berhasil diperbarui.');
    }

    public function destroy(Request $request, Curriculum $curriculum, CurriculumCourse $curriculumCourse, CurriculumCourseService $service): RedirectResponse
    {
        $service->delete($request->user(), $curriculum, $curriculumCourse);

        return redirect()->route('admin.curricula.courses.index', [$curriculum, 'semester' => $request->query->has('semester') ? $curriculumCourse->semester_id : null])->with('success', 'Mata kuliah berhasil dihapus dari kurikulum.');
    }

    private function selectedSemester(Request $request, Curriculum $curriculum): ?Semester
    {
        if (! $request->query->has('semester')) {
            return null;
        }

        $value = $request->query('semester');
        abort_unless(is_string($value) && ctype_digit($value), 403);
        $semester = $curriculum->semesters()->whereKey($value)->first();
        abort_unless($semester !== null, 403);

        return $semester;
    }

    /** @return array{curriculum: Curriculum, semesters: Collection, types: array<CourseType>} */
    private function formData(Curriculum $curriculum): array
    {
        return [
            'curriculum' => $curriculum->loadMissing('studyProgram'),
            'semesters' => $curriculum->semesters()->orderBy('semester_number')->get(),
            'types' => CourseType::cases(),
        ];
    }
}
