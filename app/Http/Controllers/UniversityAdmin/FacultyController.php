<?php

namespace App\Http\Controllers\UniversityAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FacultyIndexRequest;
use App\Http\Requests\SaveFacultyRequest;
use App\Models\Faculty;
use App\Services\FacultyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FacultyController extends Controller
{
    public function index(FacultyIndexRequest $request): View
    {
        $filters = $request->validated();
        $query = Faculty::query()->withCount('studyPrograms');

        if (filled($filters['search'] ?? null)) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn (Builder $query) => $query->whereLike('name', $search)->orWhereLike('code', $search));
        }

        if (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === '1');
        }

        return view('university-admin.faculties.index', [
            'faculties' => $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('university-admin.faculties.create', ['faculty' => new Faculty(['is_active' => true])]);
    }

    public function store(SaveFacultyRequest $request, FacultyService $service): RedirectResponse
    {
        $faculty = $service->save($request->user(), $request->facultyData());

        return redirect()->route('university-admin.faculties.edit', $faculty)->with('success', 'Fakultas berhasil ditambahkan.');
    }

    public function edit(Faculty $faculty): View
    {
        return view('university-admin.faculties.edit', compact('faculty'));
    }

    public function update(SaveFacultyRequest $request, Faculty $faculty, FacultyService $service): RedirectResponse
    {
        $service->save($request->user(), $request->facultyData(), $faculty);

        return redirect()->route('university-admin.faculties.edit', $faculty)->with('success', 'Data dan status fakultas berhasil diperbarui.');
    }
}
