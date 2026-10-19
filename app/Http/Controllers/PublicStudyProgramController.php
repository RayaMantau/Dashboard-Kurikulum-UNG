<?php

namespace App\Http\Controllers;

use App\Enums\CurriculumStatus;
use App\Models\Faculty;
use App\Models\StudyProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicStudyProgramController extends Controller
{
    public function index(Request $request): View
    {
        $input = $request->query('faculty', '');
        $facultyFilter = is_string($input) ? trim($input) : null;
        $faculties = Faculty::where('is_active', true)->orderBy('name')->orderBy('id')->get(['name', 'slug']);
        $programs = StudyProgram::query()->where('is_active', true)
            ->whereHas('faculty', fn (Builder $query): Builder => $query->where('is_active', true))
            ->with('faculty:id,name,slug')
            ->withExists(['curricula as has_current_curriculum' => fn (Builder $query): Builder => $query->where('status', CurriculumStatus::PUBLISHED)]);

        if ($facultyFilter !== '') {
            $programs->whereHas('faculty', fn (Builder $query): Builder => $query->where('slug', $facultyFilter ?? '')->where('is_active', true));
        }

        $studyPrograms = $programs->orderBy('name')->orderBy('id')->paginate(12)
            ->appends(['faculty' => $facultyFilter]);

        return view('public.study-programs.index', compact('studyPrograms', 'faculties', 'facultyFilter'));
    }

    public function show(StudyProgram $studyProgram): View
    {
        $studyProgram->load('faculty:id,name,slug,is_active');
        abort_unless($studyProgram->is_active && $studyProgram->faculty?->is_active, 404);

        $curriculum = $studyProgram->curricula()->where('status', CurriculumStatus::PUBLISHED)
            ->orderByDesc('published_at')->orderByDesc('id')
            ->first(['name', 'year', 'effective_year', 'total_sks', 'total_semesters']);
        $hasPublicCurricula = $curriculum !== null || $studyProgram->curricula()
            ->where('status', CurriculumStatus::ARCHIVED)->exists();
        $website = $studyProgram->website;
        $website = is_string($website) && filter_var($website, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($website, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $website : null;

        return view('public.study-programs.show', compact('studyProgram', 'curriculum', 'website', 'hasPublicCurricula'));
    }
}
