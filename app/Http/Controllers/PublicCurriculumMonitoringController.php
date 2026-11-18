<?php

namespace App\Http\Controllers;

use App\Services\Curriculum\CurriculumMonitoringService;
use Illuminate\View\View;

class PublicCurriculumMonitoringController extends Controller
{
    public function __invoke(CurriculumMonitoringService $monitoring): View
    {
        $programs = $monitoring->publicPrograms()->paginate(15);

        return view('public.monitoring', compact('programs'));
    }
}
