<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Services\Curriculum\CurriculumHistoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurriculumHistoryController extends Controller
{
    public function __invoke(Request $request, Curriculum $curriculum, CurriculumHistoryService $service): View
    {
        $history = $service->history($request->user(), $curriculum);
        $curriculum->loadMissing('studyProgram');

        return view('admin.curricula.history', ['curriculum' => $curriculum, ...$history]);
    }
}
