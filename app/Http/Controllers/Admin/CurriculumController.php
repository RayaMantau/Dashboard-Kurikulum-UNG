<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CurriculumStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CurriculumIndexRequest;
use App\Http\Requests\SaveCurriculumRequest;
use App\Models\Curriculum;
use App\Services\Curriculum\CurriculumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurriculumController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()->studyProgram !== null, 403);

        return view('admin.curricula.create', ['curriculum' => new Curriculum, 'studyProgram' => $request->user()->studyProgram]);
    }

    public function edit(Request $request, Curriculum $curriculum): View
    {
        abort_unless($request->user()->studyProgram !== null, 403);

        return view('admin.curricula.edit', ['curriculum' => $curriculum, 'studyProgram' => $request->user()->studyProgram]);
    }

    public function store(SaveCurriculumRequest $request, CurriculumService $service): RedirectResponse
    {
        $curriculum = $service->saveMetadata($request->user(), $request->validated());

        return redirect()->route('admin.curricula.edit', $curriculum)->with('success', 'Kurikulum berhasil ditambahkan.');
    }

    public function update(SaveCurriculumRequest $request, Curriculum $curriculum, CurriculumService $service): RedirectResponse
    {
        $service->saveMetadata($request->user(), $request->validated(), $curriculum);

        return redirect()->route('admin.curricula.edit', $curriculum)->with('success', 'Kurikulum berhasil diperbarui.');
    }

    public function index(CurriculumIndexRequest $request, CurriculumService $service): View
    {
        $filters = $request->validated();

        return view('admin.curricula.index', [
            ...$service->overview($request->user(), $filters),
            'studyProgram' => $request->user()->studyProgram,
            'filters' => $filters,
            'statuses' => CurriculumStatus::cases(),
        ]);
    }
}
