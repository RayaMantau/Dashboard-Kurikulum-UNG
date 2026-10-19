<?php

namespace App\Http\Controllers;

use App\Enums\CurriculumStatus;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Faculty;
use App\Models\StudyProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class PublicSearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = [];
        $invalid = false;
        foreach (['q', 'faculty', 'study_program', 'year'] as $field) {
            $value = $request->query($field, '');
            if (! is_string($value) || mb_strlen($value) > 255) {
                $invalid = true;
                $value = '';
            }
            $filters[$field] = trim($value);
        }
        if ($filters['year'] !== '' && (! ctype_digit($filters['year']) || (int) $filters['year'] > 65535)) {
            $invalid = true;
        }

        $faculties = Faculty::where('is_active', true)->orderBy('name')->orderBy('id')->get(['name', 'slug']);
        $programQuery = StudyProgram::where('is_active', true)
            ->whereHas('faculty', fn (Builder $query): Builder => $query->where('is_active', true))
            ->with('faculty:id,name')->orderBy('name')->orderBy('id');
        if ($filters['faculty'] !== '') {
            $programQuery->whereHas('faculty', fn (Builder $query): Builder => $query->where('slug', $filters['faculty']));
        }
        $studyPrograms = $programQuery->get(['id', 'faculty_id', 'name', 'slug']);
        $years = Curriculum::whereIn('status', [CurriculumStatus::PUBLISHED, CurriculumStatus::ARCHIVED])
            ->whereHas('studyProgram', fn (Builder $query): Builder => $query->where('is_active', true)
                ->whereHas('faculty', fn (Builder $query): Builder => $query->where('is_active', true)))
            ->distinct()->orderByDesc('year')->pluck('year');
        $searched = $invalid || count(array_filter($filters, fn (string $value): bool => $value !== '')) > 0;
        $results = null;
        if ($searched) {
            $results = $invalid ? new LengthAwarePaginator([], 0, 15, 1, ['path' => route('search')])
                : $this->results($filters)->paginate(15);
            $results->appends($filters);
        }

        return view('public.search', compact('filters', 'faculties', 'studyPrograms', 'years', 'searched', 'results'));
    }

    /** @param array{q: string, faculty: string, study_program: string, year: string} $filters */
    private function results(array $filters): Builder
    {
        $query = CurriculumCourse::query()
            ->join('curricula', 'curricula.id', '=', 'curriculum_courses.curriculum_id')
            ->join('study_programs', 'study_programs.id', '=', 'curricula.study_program_id')
            ->join('faculties', 'faculties.id', '=', 'study_programs.faculty_id')
            ->join('semesters', function (JoinClause $join): void {
                $join->on('semesters.id', '=', 'curriculum_courses.semester_id')
                    ->on('semesters.curriculum_id', '=', 'curricula.id');
            })
            ->whereIn('curricula.status', [CurriculumStatus::PUBLISHED, CurriculumStatus::ARCHIVED])
            ->where('study_programs.is_active', true)->where('faculties.is_active', true)
            ->select(['curriculum_courses.id', 'curriculum_courses.curriculum_id', 'curriculum_courses.semester_id',
                'curriculum_courses.course_code', 'curriculum_courses.course_name', 'curriculum_courses.sks', 'curriculum_courses.course_type'])
            ->with(['curriculum:id,study_program_id,name,year,status', 'curriculum.studyProgram:id,faculty_id,name,slug',
                'curriculum.studyProgram.faculty:id,name', 'semester:id,semester_number,name']);

        if ($filters['q'] !== '') {
            $pattern = '%'.$filters['q'].'%';
            $query->where(fn (Builder $query): Builder => $query->whereLike('curriculum_courses.course_code', $pattern)
                ->orWhereLike('curriculum_courses.course_name', $pattern));
        }
        foreach (['faculty' => 'faculties.slug', 'study_program' => 'study_programs.slug', 'year' => 'curricula.year'] as $field => $column) {
            if ($filters[$field] !== '') {
                $query->where($column, $filters[$field]);
            }
        }

        return $query->orderByRaw('CASE WHEN curricula.status = ? THEN 0 ELSE 1 END', [CurriculumStatus::PUBLISHED->value])
            ->orderByDesc('curricula.year')->orderBy('faculties.name')->orderBy('study_programs.name')
            ->orderBy('semesters.semester_number')->orderBy('curriculum_courses.course_code')->orderBy('curriculum_courses.id');
    }
}
