<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Services\Curriculum\CurriculumSubmissionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurriculumSubmissionController extends Controller
{
    public function confirm(Request $request, Curriculum $curriculum, CurriculumSubmissionService $service): View
    {
        abort_unless($request->user()->study_program_id !== null && $request->user()->studyProgram !== null, 403);

        return view('admin.curricula.submit', [
            'curriculum' => $curriculum,
            'studyProgram' => $request->user()->studyProgram,
            'readiness' => $service->readiness($curriculum),
        ]);
    }

    public function store(Request $request, Curriculum $curriculum, CurriculumSubmissionService $service): RedirectResponse
    {
        abort_unless($request->user()->study_program_id !== null && $request->user()->studyProgram !== null, 403);
        try {
            $service->submit($request->user(), $curriculum);
        } catch (DomainException $exception) {
            return redirect()->route('admin.curricula.submit.confirm', $curriculum)
                ->withErrors(['submission' => $exception->getMessage()]);
        }

        return redirect()->route('admin.curricula.index')
            ->with('success', 'Kurikulum berhasil diajukan untuk review Kaprodi.');
    }
}
