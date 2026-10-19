<?php

namespace App\Services\Document;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\Document;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentService
{
    private const AUDIT_FIELDS = ['curriculum_id', 'title', 'file_name', 'file_type', 'file_size', 'uploaded_by'];

    public function authorize(User $actor, Curriculum $curriculum, ?Document $document = null, bool $mutating = false): void
    {
        Gate::forUser($actor)->authorize('access-role', UserRole::STUDY_PROGRAM_ADMIN->value);
        abort_unless($actor->study_program_id !== null && $actor->studyProgram !== null, 403);
        Gate::forUser($actor)->authorize($mutating ? 'update' : 'view', $curriculum);
        abort_if($document !== null && $document->curriculum_id !== $curriculum->id, 403);
    }

    public function listing(User $actor, Curriculum $curriculum): LengthAwarePaginator
    {
        $this->authorize($actor, $curriculum);

        return $curriculum->documents()->with('uploader:id,name')->latest('id')->paginate(15);
    }

    public function upload(User $actor, Curriculum $curriculum, string $title, UploadedFile $file): Document
    {
        $path = null;
        $cleanup = function () use (&$path): void {
            if ($path === null) {
                return;
            }
            try {
                if (! Storage::disk('documents')->delete($path)) {
                    throw new RuntimeException('Failed upload cleanup returned false.');
                }
            } catch (Throwable $cleanupException) {
                Log::error('Document upload cleanup failed.', ['path' => $path, 'exception' => $cleanupException]);
            }
            $path = null;
        };
        try {
            return DB::transaction(function () use ($actor, $curriculum, $title, $file, &$path, $cleanup): Document {
                $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
                $curriculum = Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
                $this->authorize($actor, $curriculum, null, true);
                $path = 'curricula/'.$curriculum->id.'/documents/'.Str::uuid().'.pdf';
                DB::afterRollBack($cleanup);
                try {
                    $stored = Storage::disk('documents')->putFileAs(dirname($path), $file, basename($path), ['visibility' => 'private']);
                    if ($stored === false) {
                        throw new RuntimeException('Document storage returned false.');
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    throw ValidationException::withMessages(['file' => 'Dokumen gagal disimpan. Silakan coba kembali.']);
                }

                $document = Document::create([
                    'curriculum_id' => $curriculum->id,
                    'title' => $title,
                    'file_name' => $this->safeFilename($file->getClientOriginalName()),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $actor->id,
                ]);
                $this->audit($actor, $document, 'document.created', null, Arr::only($document->getAttributes(), self::AUDIT_FIELDS));

                return $document;
            });
        } catch (Throwable $exception) {
            $cleanup();

            throw $exception;
        }
    }

    public function download(User $actor, Curriculum $curriculum, Document $document): StreamedResponse
    {
        $this->authorize($actor, $curriculum, $document);

        return $this->streamDocument($document);
    }

    public function downloadForReview(User $actor, Curriculum $curriculum, Document $document): StreamedResponse
    {
        Gate::forUser($actor)->authorize('access-role', UserRole::HEAD_OF_STUDY_PROGRAM->value);
        abort_unless($actor->study_program_id !== null && $actor->studyProgram !== null, 403);
        Gate::forUser($actor)->authorize('review', $curriculum);
        abort_unless($document->curriculum_id === $curriculum->id, 403);

        return $this->streamDocument($document);
    }

    public function downloadForPublic(StudyProgram $studyProgram, Curriculum $curriculum, Document $document): StreamedResponse
    {
        $studyProgram->load('faculty:id,is_active');
        abort_unless($studyProgram->is_active && $studyProgram->faculty?->is_active
            && $curriculum->study_program_id === $studyProgram->id
            && in_array($curriculum->status, [CurriculumStatus::PUBLISHED, CurriculumStatus::ARCHIVED], true)
            && $document->curriculum_id === $curriculum->id, 404);

        return $this->streamDocument($document);
    }

    private function streamDocument(Document $document): StreamedResponse
    {
        abort_unless($this->hasSafePath($document), 404);
        try {
            $stream = Storage::disk('documents')->readStream($document->file_path);
        } catch (Throwable $exception) {
            report($exception);
            abort(404);
        }
        abort_unless(is_resource($stream), 404);

        return response()->streamDownload(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $this->safeFilename($document->file_name), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function delete(User $actor, Curriculum $curriculum, Document $document): void
    {
        DB::transaction(function () use ($actor, $curriculum, $document): void {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $curriculum = Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);
            $this->authorize($actor, $curriculum, $document, true);
            abort_unless($this->hasSafePath($document), 404);
            $old = Arr::only($document->getAttributes(), self::AUDIT_FIELDS);
            $document->delete();
            $this->audit($actor, $document, 'document.deleted', $old, null);

            DB::afterCommit(function () use ($document): void {
                try {
                    if (! Storage::disk('documents')->delete($document->file_path)) {
                        throw new RuntimeException('Document deletion returned false.');
                    }
                } catch (Throwable $exception) {
                    Log::error('Document post-commit cleanup failed.', [
                        'document_id' => $document->id, 'path' => $document->file_path, 'exception' => $exception,
                    ]);
                    throw ValidationException::withMessages([
                        'document' => 'Data dokumen telah dihapus, tetapi pembersihan file belum berhasil. Hubungi administrator.',
                    ]);
                }
            });
        });
    }

    private function hasSafePath(Document $document): bool
    {
        return preg_match('~\Acurricula/'.preg_quote((string) $document->curriculum_id, '~').'/documents/[a-f0-9-]{36}\.pdf\z~D', $document->file_path) === 1;
    }

    public function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F<>:"|?*%]/u', '', $name) ?? '';
        $name = trim(pathinfo($name, PATHINFO_FILENAME), ". \t");
        $name = mb_substr($name, 0, 240);

        return ($name !== '' ? $name : 'dokumen').'.pdf';
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(User $actor, Document $document, string $action, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => $actor->id, 'action' => $action, 'entity_type' => Document::class,
            'entity_id' => $document->id, 'old_values' => $old, 'new_values' => $new,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(),
        ]);
    }
}
