<?php

namespace App\Http\Controllers\UniversityAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStudyProgramRequest;
use App\Http\Requests\StudyProgramIndexRequest;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Services\StudyProgramService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudyProgramController extends Controller
{
    public function index(StudyProgramIndexRequest $request): View
    {
        $filters = $request->validated();
        $query = StudyProgram::query()->with('faculty:id,name');

        if (filled($filters['search'] ?? null)) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn (Builder $query): Builder => $query->whereLike('name', $search)
                ->orWhereLike('code', $search)
                ->orWhereHas('faculty', fn (Builder $query): Builder => $query->whereLike('name', $search)));
        }

        if (isset($filters['faculty_id'])) {
            $query->where('faculty_id', $filters['faculty_id']);
        }

        if (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === '1');
        }

        return view('university-admin.study-programs.index', [
            'studyPrograms' => $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString(),
            'faculties' => Faculty::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return $this->form(new StudyProgram(['is_active' => true]), 'create');
    }

    public function store(SaveStudyProgramRequest $request, StudyProgramService $service): RedirectResponse
    {
        $studyProgram = $service->save($request->user(), $request->studyProgramData());

        return redirect()->route('university-admin.study-programs.edit', $studyProgram)->with('success', 'Program Studi berhasil ditambahkan.');
    }

    public function edit(StudyProgram $studyProgram): View
    {
        return $this->form($studyProgram, 'edit');
    }

    public function update(SaveStudyProgramRequest $request, StudyProgram $studyProgram, StudyProgramService $service): RedirectResponse
    {
        $service->save($request->user(), $request->studyProgramData(), $studyProgram);

        return redirect()->route('university-admin.study-programs.edit', $studyProgram)->with('success', 'Data dan status Program Studi berhasil diperbarui.');
    }

    private function form(StudyProgram $studyProgram, string $view): View
    {
        return view('university-admin.study-programs.'.$view, [
            'studyProgram' => $studyProgram,
            'faculties' => Faculty::query()->where('is_active', true)->orWhere('id', $studyProgram->faculty_id)
                ->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }
}
