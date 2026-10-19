<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PublicFacultyController extends Controller
{
    public function index(): View
    {
        $faculties = Faculty::query()->where('is_active', true)
            ->select(['id', 'name', 'code', 'slug', 'description'])
            ->withCount(['studyPrograms' => fn (Builder $query): Builder => $query->where('is_active', true)])
            ->orderBy('name')->orderBy('id')->paginate(12);

        return view('public.faculties.index', compact('faculties'));
    }

    public function show(Faculty $faculty): View
    {
        abort_unless($faculty->is_active, 404);

        $studyPrograms = $faculty->studyPrograms()->where('is_active', true)
            ->orderBy('name')->orderBy('id')
            ->get(['name', 'code', 'degree', 'accreditation', 'slug']);
        $studyProgramCount = $studyPrograms->count();

        return view('public.faculties.show', compact('faculty', 'studyPrograms', 'studyProgramCount'));
    }
}
