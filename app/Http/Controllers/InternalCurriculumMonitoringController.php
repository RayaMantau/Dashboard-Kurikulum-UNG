<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\InternalMonitoringRequest;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Services\Curriculum\CurriculumMonitoringService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class InternalCurriculumMonitoringController extends Controller
{
    public function __invoke(InternalMonitoringRequest $request, CurriculumMonitoringService $monitoring): View
    {
        $university = $request->user()->role === UserRole::UNIVERSITY_ADMIN;
        $filters = $request->validated();
        $query = StudyProgram::query()->where('is_active', true)
            ->whereHas('faculty', fn (Builder $query) => $query->where('is_active', true));
        if (! $university) {
            abort_unless($request->user()->faculty_id !== null && $request->user()->faculty !== null, 403);
            $query->where('faculty_id', $request->user()->faculty_id);
        } elseif (isset($filters['faculty_id'])) {
            $query->where('faculty_id', $filters['faculty_id']);
        }
        if (filled($filters['search'] ?? null)) {
            $query->whereLike('name', '%'.$filters['search'].'%');
        }
        $programs = $monitoring->withCurrentCurriculum($query)
            ->orderBy(Faculty::select('name')->whereColumn('faculties.id', 'study_programs.faculty_id'))
            ->orderBy('faculty_id')->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();

        return view('internal.monitoring', [
            'programs' => $programs, 'university' => $university, 'filters' => $filters,
            'faculties' => $university ? Faculty::where('is_active', true)->orderBy('name')->orderBy('id')->get(['id', 'name']) : collect(),
        ]);
    }
}
