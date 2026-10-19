<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Curriculum;
use App\Models\Document;
use App\Services\Document\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request, Curriculum $curriculum, DocumentService $service): View
    {
        $documents = $service->listing($request->user(), $curriculum);

        return view('admin.documents.index', [
            'curriculum' => $curriculum->loadMissing('studyProgram'), 'documents' => $documents,
        ]);
    }

    public function create(Request $request, Curriculum $curriculum, DocumentService $service): View
    {
        $service->authorize($request->user(), $curriculum, null, true);

        return view('admin.documents.create', ['curriculum' => $curriculum->loadMissing('studyProgram')]);
    }

    public function store(StoreDocumentRequest $request, Curriculum $curriculum, DocumentService $service): RedirectResponse
    {
        $service->upload($request->user(), $curriculum, $request->validated('title'), $request->file('file'));

        return redirect()->route('admin.curricula.documents.index', $curriculum)->with('success', 'Dokumen berhasil diunggah.');
    }

    public function download(Request $request, Curriculum $curriculum, Document $document, DocumentService $service): StreamedResponse
    {
        return $service->download($request->user(), $curriculum, $document);
    }

    public function destroy(Request $request, Curriculum $curriculum, Document $document, DocumentService $service): RedirectResponse
    {
        $service->delete($request->user(), $curriculum, $document);

        return redirect()->route('admin.curricula.documents.index', $curriculum)->with('success', 'Dokumen berhasil dihapus.');
    }
}
