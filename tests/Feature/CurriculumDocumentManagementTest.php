<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\CurriculumReview;
use App\Models\Document;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\CurriculumSubmissionService;
use App\Services\Document\DocumentService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CurriculumDocumentManagementTest extends TestCase
{
    use DatabaseMigrations;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";

    /** @var array<File> */
    private array $temporaryUploads = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    /** @return array{User, Curriculum} */
    private function context(): array
    {
        $actor = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => StudyProgram::factory()]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $actor->study_program_id, 'total_semesters' => 1]);

        return [$actor, $curriculum];
    }

    private function pdf(string $name = 'Kurikulum.pdf'): UploadedFile
    {
        return $this->realUpload($name, self::PDF);
    }

    private function realUpload(string $name, string $content, ?string $clientMime = null): UploadedFile
    {
        $temporary = UploadedFile::fake()->createWithContent($name, $content);
        $this->temporaryUploads[] = $temporary;

        return new UploadedFile($temporary->getPathname(), $name, $clientMime, null, true);
    }

    private function url(string $action, Curriculum $curriculum, ?Document $document = null): string
    {
        return route('admin.curricula.documents.'.$action, $document ? [$curriculum, $document] : $curriculum);
    }

    private function upload(User $actor, Curriculum $curriculum): Document
    {
        return app(DocumentService::class)->upload($actor, $curriculum, 'Dokumen Resmi', $this->pdf());
    }

    public function test_pdf_upload_uses_server_metadata_and_private_generated_path(): void
    {
        [$actor, $curriculum] = $this->context();
        $other = Curriculum::factory()->create();
        $file = $this->pdf('../../folder/Kurikulum.pdf');
        $this->assertSame('application/pdf', $file->getMimeType());
        $this->actingAs($actor)->post($this->url('store', $curriculum), [
            'title' => 'Dokumen Resmi', 'file' => $file, 'curriculum_id' => $other->id,
            'file_name' => 'hacked.exe', 'file_path' => '../../public/hacked', 'file_type' => 'application/x-php',
            'file_size' => 123456, 'uploaded_by' => $other->created_by,
        ])->assertSessionHasNoErrors()->assertRedirect($this->url('index', $curriculum));
        $document = Document::sole();
        $this->assertSame($curriculum->id, $document->curriculum_id);
        $this->assertSame($actor->id, $document->uploaded_by);
        $this->assertSame('Kurikulum.pdf', $document->file_name);
        $this->assertSame('application/pdf', $document->file_type);
        $this->assertSame(strlen(self::PDF), $document->file_size);
        $this->assertMatchesRegularExpression('~^curricula/'.$curriculum->id.'/documents/[a-f0-9-]{36}\.pdf$~', $document->file_path);
        Storage::disk('documents')->assertExists($document->file_path);
        $this->assertSame('private', config('filesystems.disks.documents.visibility'));
        $audit = AuditLog::sole();
        $this->assertSame('document.created', $audit->action);
        $this->assertSame(Document::class, $audit->entity_type);
        $this->assertSame($document->id, $audit->entity_id);
        $this->assertSame($actor->id, $audit->new_values['uploaded_by']);
        $this->assertArrayNotHasKey('file_path', $audit->new_values);
        $this->assertNull($audit->old_values);
        $second = $this->upload($actor, $curriculum);
        $this->assertNotSame($document->file_path, $second->file_path);
        $this->get($this->url('index', $curriculum))->assertOk()->assertSee('Dokumen Resmi')->assertSee($actor->name)->assertSee('KB')
            ->assertDontSee($document->file_path)->assertDontSee('/storage/');
    }

    public static function invalidFiles(): array
    {
        return [
            'script' => ['malware.php', '<?php echo "unsafe";', null],
            'fake pdf content' => ['malware.pdf', '<?php echo "unsafe";', null],
            'text with claimed client MIME' => ['text.pdf', 'This is plain text, not PDF.', 'application/pdf'],
            'pdf with wrong extension' => ['file.exe', self::PDF, null],
        ];
    }

    #[DataProvider('invalidFiles')]
    public function test_content_and_extension_validation(string $name, string $content, ?string $claimedMime): void
    {
        [$actor, $curriculum] = $this->context();
        $file = $this->realUpload($name, $content, $claimedMime);
        $this->actingAs($actor)->followingRedirects()->from($this->url('create', $curriculum))
            ->post($this->url('store', $curriculum), ['title' => 'Dokumen', 'file' => $file])->assertOk()->assertSee('role="alert"', false)->assertSee('aria-invalid="true"', false);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_required_title_and_file_and_size_boundary(): void
    {
        [$actor, $curriculum] = $this->context();
        $this->actingAs($actor)->post($this->url('store', $curriculum), [])->assertSessionHasErrors(['title', 'file']);
        $this->post($this->url('store', $curriculum), ['title' => str_repeat('x', 256), 'file' => $this->pdf()])->assertSessionHasErrors('title');
        $this->post($this->url('store', $curriculum), ['title' => ['invalid'], 'file' => $this->pdf()])->assertSessionHasErrors('title');
        $this->get($this->url('create', $curriculum))->assertOk();
        $oversized = $this->realUpload('large.pdf', self::PDF.str_repeat(' ', 10 * 1024 * 1024));
        $this->post($this->url('store', $curriculum), ['title' => 'Besar', 'file' => $oversized])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
        $limit = $this->realUpload('limit.pdf', self::PDF.str_repeat(' ', 10 * 1024 * 1024 - strlen(self::PDF)));
        $this->post($this->url('store', $curriculum), ['title' => 'Batas', 'file' => $limit])->assertSessionHasNoErrors();
        $this->assertSame(10 * 1024 * 1024, Document::sole()->file_size);
    }

    public function test_download_is_authorized_streamed_and_ignores_path_query(): void
    {
        [$actor, $curriculum] = $this->context();
        $document = $this->upload($actor, $curriculum);
        Storage::disk('documents')->put('secret.txt', 'NOT DOCUMENT');
        $response = $this->actingAs($actor)->get($this->url('download', $curriculum, $document).'?path=secret.txt')
            ->assertOk()->assertDownload('Kurikulum.pdf')->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(self::PDF, $response->streamedContent());
        Storage::disk('documents')->delete($document->file_path);
        $this->get($this->url('download', $curriculum, $document))->assertNotFound();
        $this->delete($this->url('destroy', $curriculum, $document))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    public function test_suspicious_filename_is_safe_and_corrupt_stored_path_is_not_read_or_deleted(): void
    {
        [$actor, $curriculum] = $this->context();
        $document = app(DocumentService::class)->upload($actor, $curriculum, 'Aman', $this->pdf("..\\..\\evil\r\n%name.pdf"));
        $this->assertSame('evilname.pdf', $document->file_name);
        $this->actingAs($actor)->get($this->url('download', $curriculum, $document))->assertDownload('evilname.pdf');
        Storage::disk('documents')->put('secret.pdf', self::PDF);
        $document->update(['file_path' => '../secret.pdf']);
        $this->get($this->url('download', $curriculum, $document))->assertNotFound();
        $this->delete($this->url('destroy', $curriculum, $document))->assertNotFound();
        Storage::disk('documents')->assertExists('secret.pdf');
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public static function statuses(): array
    {
        return array_map(fn (CurriculumStatus $status): array => [$status], CurriculumStatus::cases());
    }

    #[DataProvider('statuses')]
    public function test_status_matrix(CurriculumStatus $status): void
    {
        [$actor, $curriculum] = $this->context();
        $document = $this->upload($actor, $curriculum);
        $curriculum->update(['status' => $status]);
        $editable = in_array($status, [CurriculumStatus::DRAFT, CurriculumStatus::REVISION], true);
        $response = $this->actingAs($actor)->get($this->url('index', $curriculum))->assertOk()->assertSee('Unduh');
        if ($editable) {
            $response->assertSee('Unggah Dokumen')->assertSee('Hapus Dokumen');
        } else {
            $response->assertDontSee('Unggah Dokumen')->assertDontSee('Hapus Dokumen');
        }
        $this->get($this->url('download', $curriculum, $document))->assertOk();
        $this->get($this->url('create', $curriculum))->assertStatus($editable ? 200 : 403);
        $this->post($this->url('store', $curriculum), ['title' => 'Baru', 'file' => $this->pdf()])->assertStatus($editable ? 302 : 403);
        $this->delete($this->url('destroy', $curriculum, $document))->assertStatus($editable ? 302 : 403);
        $this->assertSame($status, $curriculum->fresh()->status);
        if (! $editable) {
            Storage::disk('documents')->assertExists($document->file_path);
            $this->assertDatabaseCount('documents', 1);
            $this->assertDatabaseCount('audit_logs', 1);
        }
    }

    public function test_guests_other_roles_inactive_and_missing_scope_cannot_use_management(): void
    {
        [$actor, $curriculum] = $this->context();
        $document = $this->upload($actor, $curriculum);
        foreach (['index', 'create', 'download'] as $action) {
            $this->get($this->url($action, $curriculum, $action === 'download' ? $document : null))->assertRedirect(route('login'));
        }
        $this->post($this->url('store', $curriculum), [])->assertRedirect(route('login'));
        $this->delete($this->url('destroy', $curriculum, $document))->assertRedirect(route('login'));
        foreach ([
            ['role' => UserRole::UNIVERSITY_ADMIN], ['role' => UserRole::FACULTY_ADMIN],
            ['role' => UserRole::HEAD_OF_STUDY_PROGRAM], ['is_active' => false], ['study_program_id' => null],
        ] as $changes) {
            $user = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => $actor->study_program_id, ...$changes]);
            $this->actingAs($user)->get($this->url('index', $curriculum))->assertForbidden();
            $this->get($this->url('create', $curriculum))->assertForbidden();
            $this->get($this->url('download', $curriculum, $document))->assertForbidden();
            $this->post($this->url('store', $curriculum), [])->assertForbidden();
            $this->delete($this->url('destroy', $curriculum, $document))->assertForbidden();
        }
        $this->assertDatabaseCount('documents', 1);
        Storage::disk('documents')->assertExists($document->file_path);
    }

    public function test_cross_curriculum_and_cross_program_access_are_forbidden(): void
    {
        [$actor, $curriculum] = $this->context();
        $document = $this->upload($actor, $curriculum);
        [$foreignActor, $foreignCurriculum] = $this->context();
        $foreignDocument = $this->upload($foreignActor, $foreignCurriculum);
        $sameProgramCurriculum = Curriculum::factory()->create(['study_program_id' => $actor->study_program_id]);
        $sameProgramDocument = $this->upload($actor, $sameProgramCurriculum);
        $this->actingAs($actor);
        foreach ([$sameProgramDocument, $foreignDocument] as $other) {
            $this->get($this->url('download', $curriculum, $other))->assertForbidden();
            $this->delete($this->url('destroy', $curriculum, $other))->assertForbidden();
            Storage::disk('documents')->assertExists($other->file_path);
        }
        $this->get($this->url('index', $foreignCurriculum))->assertForbidden();
        $this->get($this->url('create', $foreignCurriculum))->assertForbidden();
        $this->post($this->url('store', $foreignCurriculum), ['title' => 'Forged', 'file' => $this->pdf()])->assertForbidden();
        $this->get($this->url('download', $foreignCurriculum, $foreignDocument))->assertForbidden();
        $this->delete($this->url('destroy', $foreignCurriculum, $foreignDocument))->assertForbidden();
        $this->assertDatabaseCount('documents', 3);
    }

    public function test_upload_and_delete_preserve_structure_snapshots_metadata_and_workflow(): void
    {
        [$actor, $curriculum] = $this->context();
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        $course = Course::factory()->create(['study_program_id' => $actor->study_program_id]);
        $assignment = CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        $review = CurriculumReview::create(['curriculum_id' => $curriculum->id, 'reviewer_id' => $actor->id, 'status' => 'revision', 'notes' => 'Periksa kembali']);
        $models = [$curriculum, $semester, $course, $assignment, $review];
        $before = array_map(fn ($model): array => $model->fresh()->getAttributes(), $models);
        $document = $this->upload($actor, $curriculum);
        $this->actingAs($actor)->delete($this->url('destroy', $curriculum, $document))->assertRedirect($this->url('index', $curriculum))->assertSessionHasNoErrors();
        $this->assertSame($before, array_map(fn ($model): array => $model->fresh()->getAttributes(), $models));
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('notifications', 0);
        Storage::disk('documents')->assertMissing($document->file_path);
        $audit = AuditLog::where('action', 'document.deleted')->sole();
        $this->assertSame($document->id, $audit->entity_id);
        $this->assertSame('Dokumen Resmi', $audit->old_values['title']);
        $this->assertArrayNotHasKey('file_path', $audit->old_values);
        $this->assertNull($audit->new_values);
        try {
            app(CurriculumSubmissionService::class)->submit($actor, $curriculum);
            $this->fail('Document is required.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Dokumen kurikulum resmi wajib diunggah.', $exception->getMessage());
        }
        $this->assertSame(CurriculumStatus::DRAFT, $curriculum->fresh()->status);
        $this->upload($actor, $curriculum);
        app(CurriculumSubmissionService::class)->submit($actor, $curriculum);
        $this->assertSame(CurriculumStatus::SUBMITTED, $curriculum->fresh()->status);
    }

    public static function mutations(): array
    {
        return [['upload'], ['delete']];
    }

    #[DataProvider('mutations')]
    public function test_audit_failure_preserves_database_and_files(string $operation): void
    {
        [$actor, $curriculum] = $this->context();
        $document = $operation === 'delete' ? $this->upload($actor, $curriculum) : null;
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::creating(fn () => throw new RuntimeException('Audit unavailable'));
        try {
            if ($document) {
                app(DocumentService::class)->delete($actor, $curriculum, $document);
            } else {
                $this->upload($actor, $curriculum);
            }
            $this->fail('Audit should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('documents', $document ? 1 : 0);
        $this->assertDatabaseCount('audit_logs', $document ? 1 : 0);
        $this->assertCount($document ? 1 : 0, Storage::disk('documents')->allFiles());
        if ($document) {
            $this->assertSame(self::PDF, Storage::disk('documents')->get($document->file_path));
        }
    }

    public function test_storage_write_failure_does_not_create_document_or_audit(): void
    {
        [$actor, $curriculum] = $this->context();
        $disk = Storage::disk('documents');
        $mock = Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::set('documents', $mock);
        $this->actingAs($actor)->post($this->url('store', $curriculum), ['title' => 'Gagal', 'file' => $this->pdf()])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_outer_transaction_rollback_cleans_upload_and_preserves_deleted_file(): void
    {
        [$actor, $curriculum] = $this->context();
        DB::beginTransaction();
        $uploaded = $this->upload($actor, $curriculum);
        Storage::disk('documents')->assertExists($uploaded->file_path);
        DB::rollBack();
        Storage::disk('documents')->assertMissing($uploaded->file_path);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('audit_logs', 0);

        $document = $this->upload($actor, $curriculum);
        DB::beginTransaction();
        app(DocumentService::class)->delete($actor, $curriculum, $document);
        Storage::disk('documents')->assertExists($document->file_path);
        DB::rollBack();
        Storage::disk('documents')->assertExists($document->file_path);
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_document_database_failure_compensates_uploaded_file(): void
    {
        [$actor, $curriculum] = $this->context();
        $dispatcher = Document::getEventDispatcher();
        Document::setEventDispatcher(clone $dispatcher);
        Document::creating(fn () => throw new RuntimeException('Document persistence failed'));
        try {
            $this->upload($actor, $curriculum);
            $this->fail('Document creation should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Document persistence failed', $exception->getMessage());
        } finally {
            Document::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_failed_post_commit_file_cleanup_is_reported_without_claiming_rollback(): void
    {
        [$actor, $curriculum] = $this->context();
        $document = $this->upload($actor, $curriculum);
        $disk = Storage::disk('documents');
        $mock = Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('delete')->with($document->file_path)->once()->andReturn(false);
        Storage::set('documents', $mock);
        Log::spy();
        $this->actingAs($actor)->followingRedirects()->from($this->url('index', $curriculum))->delete($this->url('destroy', $curriculum, $document))
            ->assertOk()->assertSee('pembersihan file belum berhasil');
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.deleted', 'entity_id' => $document->id]);
        $disk->assertExists($document->file_path);
        Log::shouldHaveReceived('error')->once();
    }

    #[DataProvider('mutations')]
    public function test_stale_status_is_rechecked_before_mutation(string $operation): void
    {
        [$actor, $curriculum] = $this->context();
        $document = $this->upload($actor, $curriculum);
        Curriculum::whereKey($curriculum->id)->update(['status' => CurriculumStatus::SUBMITTED]);
        try {
            if ($operation === 'delete') {
                app(DocumentService::class)->delete($actor, $curriculum, $document);
            } else {
                $this->upload($actor, $curriculum);
            }
            $this->fail('Fresh status must deny mutation.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('documents', 1);
            $this->assertDatabaseCount('audit_logs', 1);
            $this->assertCount(1, Storage::disk('documents')->allFiles());
        }
    }

    public function test_index_empty_states_pagination_escaping_navigation_and_eager_loading(): void
    {
        [$actor, $curriculum] = $this->context();
        $this->actingAs($actor)->get($this->url('index', $curriculum))->assertOk()->assertSee('Belum ada dokumen pada kurikulum ini.')->assertSee('Unggah Dokumen');
        $this->get($this->url('create', $curriculum))->assertOk()->assertSee('multipart/form-data')->assertSee('Maksimum 10 MB')->assertSee($actor->studyProgram->name);
        $this->get(route('admin.curricula.index'))->assertSee($this->url('index', $curriculum), false);
        $document = $this->upload($actor, $curriculum);
        $document->update(['title' => '<script>alert(1)</script>']);
        $this->actingAs($actor->fresh());
        DB::enableQueryLog();
        $this->get($this->url('index', $curriculum))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        Document::factory()->count(16)->create(['curriculum_id' => $curriculum->id, 'uploaded_by' => $actor->id]);
        $this->actingAs($actor->fresh());
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get($this->url('index', $curriculum))->assertOk();
        $this->assertSame($count, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertSame(17, $response->viewData('documents')->total());
        $this->assertCount(15, $response->viewData('documents')->items());
        $this->get($this->url('index', $curriculum).'?page=2')->assertOk()->assertSee('&lt;script&gt;', false);
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->getName() ?? '', 'admin.curricula.documents.')) {
                $this->assertNotContains('PUT', $route->methods());
                $this->assertNotContains('PATCH', $route->methods());
            }
        }
        $this->assertFalse(config('filesystems.disks.documents.serve'));
        $this->assertNotSame(config('filesystems.disks.public.root'), config('filesystems.disks.documents.root'));
    }
}
