<?php

namespace App\Http\Controllers;

use App\Models\StudyProgram;
use App\Services\Curriculum\CurriculumMonitoringService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PublicCurriculumMonitoringController extends Controller
{
    public function __invoke(CurriculumMonitoringService $monitoring): View
    {
        $programs = StudyProgram::query()->where('is_active', true)
            ->whereHas('faculty', fn (Builder $query) => $query->where('is_active', true));
        $programs = $monitoring->withCurrentCurriculum($programs)
            ->orderBy('name')->orderBy('id')->paginate(15);

        return view('public.monitoring', compact('programs'));
    }
}
