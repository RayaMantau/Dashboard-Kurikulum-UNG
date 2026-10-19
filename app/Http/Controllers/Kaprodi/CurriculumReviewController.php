<?php

namespace App\Http\Controllers\Kaprodi;

use App\Enums\CurriculumStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequestCurriculumRevisionRequest;
use App\Models\Curriculum;
use App\Models\Document;
use App\Services\Curriculum\CurriculumReviewService;
use App\Services\Document\DocumentService;
use DomainException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CurriculumReviewController extends Controller
{
    public function index(Request $request): View
    {
        $this->requireScope($request);

        return view('kaprodi.submissions.index', [
            'studyProgram' => $request->user()->studyProgram,
            'curricula' => Curriculum::query()->where('study_program_id', $request->user()->study_program_id)
                ->where('status', CurriculumStatus::SUBMITTED)
                ->orderBy('submitted_at')->orderBy('id')->paginate(15),
        ]);
    }

    public function show(Request $request, Curriculum $curriculum): View
    {
        $this->requireScope($request);
        $curriculum->load([
            'studyProgram',
            'semesters' => fn (HasMany $query): HasMany => $query->orderBy('semester_number'),
            'semesters.curriculumCourses' => fn (HasMany $query): HasMany => $query->where('curriculum_id', $curriculum->id)->orderBy('course_code')->orderBy('id'),
            'documents' => fn (HasMany $query): HasMany => $query->orderByDesc('id'),
        ]);

        return view('kaprodi.submissions.show', ['curriculum' => $curriculum]);
    }

    public function approve(Request $request, Curriculum $curriculum, CurriculumReviewService $service): RedirectResponse
    {
        $this->requireScope($request);
        try {
            $service->approve($request->user(), $curriculum);
        } catch (DomainException $exception) {
            return redirect()->route('kaprodi.submissions.index')->withErrors(['review' => $exception->getMessage()]);
        }

        return redirect()->route('kaprodi.submissions.index')->with('success', 'Kurikulum berhasil disetujui. Menunggu proses publikasi.');
    }

    public function revision(RequestCurriculumRevisionRequest $request, Curriculum $curriculum, CurriculumReviewService $service): RedirectResponse
    {
        try {
            $service->requestRevision($request->user(), $curriculum, $request->validated('notes'));
        } catch (DomainException $exception) {
            return redirect()->route('kaprodi.submissions.index')->withErrors(['review' => $exception->getMessage()]);
        }

        return redirect()->route('kaprodi.submissions.index')->with('success', 'Kurikulum dikembalikan untuk revisi.');
    }

    public function download(Request $request, Curriculum $curriculum, Document $document, DocumentService $service): StreamedResponse
    {
        return $service->downloadForReview($request->user(), $curriculum, $document);
    }

    private function requireScope(Request $request): void
    {
        abort_unless($request->user()->study_program_id !== null && $request->user()->studyProgram !== null, 403);
    }
}
