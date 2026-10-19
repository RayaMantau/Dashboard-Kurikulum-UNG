<?php

namespace App\Http\Controllers;

use App\Enums\CurriculumStatus;
use App\Models\Curriculum;
use App\Models\Document;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Services\Document\DocumentService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicCurriculumController extends Controller
{
    public function index(StudyProgram $studyProgram): View
    {
        $this->requirePublicProgram($studyProgram);
        $columns = ['id', 'name', 'year', 'effective_year', 'total_sks', 'total_semesters', 'status'];
        $current = $studyProgram->curricula()->where('status', CurriculumStatus::PUBLISHED)
            ->orderByDesc('published_at')->orderByDesc('id')->first($columns);
        $archives = $studyProgram->curricula()->where('status', CurriculumStatus::ARCHIVED)
            ->orderByDesc('archived_at')->orderByDesc('year')->orderByDesc('id')
            ->paginate(12, $columns);

        return view('public.curricula.index', compact('studyProgram', 'current', 'archives'));
    }

    public function show(StudyProgram $studyProgram, Curriculum $curriculum, DocumentService $documentService): View
    {
        $this->requirePublicCurriculum($studyProgram, $curriculum);
        $semesters = $curriculum->semesters()->orderBy('semester_number')->orderBy('id')
            ->with(['curriculumCourses' => fn (HasMany $query): HasMany => $this->structureAttachments($query, $curriculum)])
            ->get();

        $documents = $curriculum->documents()->latest('id')->get(['id', 'title', 'file_name', 'file_size'])
            ->each(function (Document $document) use ($documentService): void {
                $document->file_name = $documentService->safeFilename($document->file_name);
            });

        return view('public.curricula.show', compact('studyProgram', 'curriculum', 'semesters', 'documents'));
    }

    public function download(StudyProgram $studyProgram, Curriculum $curriculum, Document $document, DocumentService $service): StreamedResponse
    {
        return $service->downloadForPublic($studyProgram, $curriculum, $document);
    }

    public function semester(StudyProgram $studyProgram, Curriculum $curriculum, Semester $semester): View
    {
        $this->requirePublicCurriculum($studyProgram, $curriculum);
        abort_unless($semester->curriculum_id === $curriculum->id, 404);
        $semester->load(['curriculumCourses' => fn (HasMany $query): HasMany => $this->structureAttachments($query, $curriculum)]);

        return view('public.curricula.semester', compact('studyProgram', 'curriculum', 'semester'));
    }

    private function structureAttachments(HasMany $query, Curriculum $curriculum): HasMany
    {
        return $query->where('curriculum_id', $curriculum->id)->orderBy('course_code')->orderBy('id')
            ->select(['id', 'semester_id', 'course_code', 'course_name', 'sks', 'course_type', 'prerequisite_notes']);
    }

    private function requirePublicCurriculum(StudyProgram $studyProgram, Curriculum $curriculum): void
    {
        $this->requirePublicProgram($studyProgram);
        abort_unless($curriculum->study_program_id === $studyProgram->id
            && in_array($curriculum->status, [CurriculumStatus::PUBLISHED, CurriculumStatus::ARCHIVED], true), 404);
    }

    private function requirePublicProgram(StudyProgram $studyProgram): void
    {
        $studyProgram->load('faculty:id,name,slug,is_active');
        abort_unless($studyProgram->is_active && $studyProgram->faculty?->is_active, 404);
    }
}
