<?php

namespace App\Http\Controllers;

use App\Enums\CurriculumStatus;
use App\Models\Curriculum;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Services\Curriculum\CurriculumMonitoringService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(CurriculumMonitoringService $monitoring): View
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
            'programs' => $monitoring->publicPrograms()->paginate(15)->fragment('curriculum-title'),
        ]);
    }
}
