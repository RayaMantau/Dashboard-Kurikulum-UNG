<?php

namespace App\Http\Controllers;

use App\Enums\CurriculumStatus;
use App\Models\Curriculum;
use App\Models\Faculty;
use App\Models\StudyProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $faculties = Faculty::query()->where('is_active', true);
        $programs = StudyProgram::query()->where('is_active', true)
            ->whereHas('faculty', fn (Builder $query): Builder => $query->where('is_active', true));
        $published = Curriculum::query()->where('status', CurriculumStatus::PUBLISHED)
            ->whereIn('study_program_id', (clone $programs)->select('id'));

        return view('public.home', [
            'statistics' => [
                'Fakultas Aktif' => (clone $faculties)->count(),
                'Program Studi Aktif' => (clone $programs)->count(),
                'Kurikulum Aktif' => (clone $published)->count(),
            ],
            'faculties' => $faculties->orderBy('name')->orderBy('id')->limit(6)->get(['id', 'name', 'code', 'slug']),
            'curricula' => $published->with(['studyProgram:id,faculty_id,name', 'studyProgram.faculty:id,name'])
                ->orderByDesc('published_at')->orderByDesc('id')->limit(3)
                ->get(['id', 'study_program_id', 'name', 'year']),
        ]);
    }
}
