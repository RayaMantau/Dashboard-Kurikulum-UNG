<?php

namespace App\Http\Controllers\Kaprodi;

use App\Enums\CurriculumStatus;
use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Services\Curriculum\CurriculumPublicationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurriculumPublicationController extends Controller
{
    public function index(Request $request): View
    {
        $this->requireScope($request);

        return view('kaprodi.submissions.approved', [
            'studyProgram' => $request->user()->studyProgram,
            'curricula' => Curriculum::query()->where('study_program_id', $request->user()->study_program_id)
                ->where('status', CurriculumStatus::APPROVED)
                ->orderBy('approved_at')->orderBy('id')->paginate(15),
        ]);
    }

    public function confirm(Request $request, Curriculum $curriculum): View
    {
        $this->requireScope($request);

        return view('kaprodi.submissions.publish', [
            'curriculum' => $curriculum,
            'studyProgram' => $request->user()->studyProgram,
            'previousCurricula' => Curriculum::query()->where('study_program_id', $request->user()->study_program_id)
                ->where('status', CurriculumStatus::PUBLISHED)->whereKeyNot($curriculum->id)
                ->orderByDesc('published_at')->orderByDesc('id')->get(['id', 'name', 'year', 'status']),
        ]);
    }

    public function store(Request $request, Curriculum $curriculum, CurriculumPublicationService $service): RedirectResponse
    {
        $this->requireScope($request);
        try {
            $service->publish($request->user(), $curriculum);
        } catch (DomainException $exception) {
            return redirect()->route('kaprodi.submissions.approved')->withErrors(['publication' => $exception->getMessage()]);
        }

        return redirect()->route('kaprodi.submissions.approved')->with('success', 'Kurikulum berhasil dipublikasikan.');
    }

    private function requireScope(Request $request): void
    {
        abort_unless($request->user()->study_program_id !== null && $request->user()->studyProgram !== null, 403);
    }
}
