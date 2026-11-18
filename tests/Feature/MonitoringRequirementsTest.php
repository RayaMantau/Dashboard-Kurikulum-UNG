<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Document;
use App\Models\Faculty;
use App\Models\Semester;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\CurriculumService;
use App\Services\Curriculum\CurriculumSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MonitoringRequirementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    private function actor(): User
    {
        return User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true,
            'study_program_id' => StudyProgram::factory()]);
    }

    private function data(): array
    {
        return ['name' => 'Kurikulum Baru', 'year' => 2026, 'total_sks' => 144, 'total_semesters' => 8,
            'ratified_at' => '2026-02-28', 'external_document_url' => 'https://example.test/kurikulum.pdf',
            'sk' => UploadedFile::fake()->createWithContent('sk.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF")];
    }

    public static function required(): array
    {
        return [['ratified_at'], ['external_document_url'], ['sk']];
    }

    #[DataProvider('required')]
    public function test_required_metadata_and_sk_are_enforced_on_create_and_legacy_edit(string $field): void
    {
        $actor = $this->actor();
        $data = $this->data();
        unset($data[$field]);
        $this->actingAs($actor)->post(route('admin.curricula.store'), $data)->assertSessionHasErrors($field);
        $this->assertDatabaseCount('curricula', 0);
        $legacy = Curriculum::factory()->create(['study_program_id' => $actor->study_program_id]);
        $before = $legacy->fresh()->getAttributes();
        $this->put(route('admin.curricula.update', $legacy), $data)->assertSessionHasErrors($field);
        $this->assertSame($before, $legacy->fresh()->getAttributes());
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_create_stores_private_sk_and_ignores_forged_identity_then_edit_does_not_require_reupload(): void
    {
        $actor = $this->actor();
        $data = $this->data();
        $this->actingAs($actor)->post(route('admin.curricula.store'), [...$data,
            'study_program_id' => StudyProgram::factory()->create()->id, 'document_type' => 'curriculum',
            'file_path' => 'public/forged.pdf', 'uploaded_by' => 999, 'status' => 'published'])
            ->assertSessionHasNoErrors();
        $curriculum = Curriculum::sole();
        $document = Document::sole();
        $this->assertSame($actor->study_program_id, $curriculum->study_program_id);
        $this->assertSame(CurriculumStatus::DRAFT, $curriculum->status);
        $this->assertSame(Document::RATIFICATION_DECREE, $document->document_type);
        $this->assertSame($curriculum->id, $document->curriculum_id);
        $this->assertSame($actor->id, $document->uploaded_by);
        $this->assertMatchesRegularExpression('~^curricula/'.$curriculum->id.'/documents/[a-f0-9-]{36}\.pdf$~', $document->file_path);
        Storage::disk('documents')->assertExists($document->file_path);
        $this->assertSame('private', config('filesystems.disks.documents.visibility'));
        $this->assertFalse(config('filesystems.disks.documents.serve'));
        $this->get(route('admin.curricula.edit', $curriculum))->assertOk()->assertSee('SK pengesahan sudah tersedia.')
            ->assertDontSee('name="sk"', false);
        unset($data['sk']);
        $this->patch(route('admin.curricula.update', $curriculum), [...$data, 'name' => 'Nama diperbarui'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('documents', 1);
        $this->assertSame('Nama diperbarui', $curriculum->fresh()->name);
        $this->get(route('admin.curricula.documents.download', [$curriculum, $document]))->assertOk()->assertDownload('sk.pdf');
    }

    public static function invalidFiles(): array
    {
        return [['text'], ['forged'], ['large']];
    }

    #[DataProvider('invalidFiles')]
    public function test_invalid_sk_is_rejected(string $kind): void
    {
        $file = match ($kind) {
            'text' => UploadedFile::fake()->createWithContent('sk.txt', 'not a PDF'),
            'forged' => UploadedFile::fake()->createWithContent('sk.pdf', '<html>not a PDF</html>'),
            'large' => UploadedFile::fake()->create('sk.pdf', 10241, 'application/pdf'),
        };
        $temporary = $file;
        if ($kind !== 'large') {
            $file = new UploadedFile($file->getPathname(), $file->getClientOriginalName(), 'application/pdf', null, true);
        }
        $this->actingAs($this->actor())->post(route('admin.curricula.store'), [...$this->data(), 'sk' => $file])->assertSessionHasErrors('sk');
        $this->assertDatabaseCount('curricula', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_audit_failure_compensates_sk_storage_and_rolls_back_curriculum(): void
    {
        $actor = $this->actor();
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        AuditLog::creating(function (AuditLog $audit): void {
            if ($audit->action === 'curriculum.created') {
                throw new RuntimeException('Audit failure after upload');
            }
        });
        try {
            app(CurriculumService::class)->saveMetadata($actor, $this->data());
            $this->fail('Audit must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit failure after upload', $exception->getMessage());
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('curricula', 0);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_submission_requires_metadata_sk_and_a_separate_curriculum_document(): void
    {
        $curriculum = Curriculum::factory()->create();
        $service = app(CurriculumSubmissionService::class);
        $errors = $service->readiness($curriculum)['errors'];
        $this->assertContains('Tanggal pengesahan wajib diisi.', $errors);
        $this->assertContains('Link file kurikulum HTTP/HTTPS wajib diisi.', $errors);
        $this->assertContains('SK pengesahan kurikulum wajib diunggah.', $errors);
        Document::factory()->create(['curriculum_id' => $curriculum->id, 'document_type' => Document::RATIFICATION_DECREE]);
        $checks = $service->readiness($curriculum)['checks'];
        $this->assertFalse($checks['documents']['passed']);
        Document::factory()->create(['curriculum_id' => $curriculum->id]);
        $this->assertTrue($service->readiness($curriculum)['checks']['documents']['passed']);
    }

    public function test_homepage_monitoring_has_buttons_faculty_order_and_never_exposes_sk(): void
    {
        $b = Faculty::factory()->create(['name' => 'B Fakultas']);
        $a = Faculty::factory()->create(['name' => 'A Fakultas']);
        $pb = StudyProgram::factory()->create(['faculty_id' => $b->id, 'name' => 'A Prodi']);
        $pa = StudyProgram::factory()->create(['faculty_id' => $a->id, 'name' => 'Z Prodi']);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $pa->id, 'status' => CurriculumStatus::PUBLISHED,
            'external_document_url' => 'https://example.test/file', 'ratified_at' => '2020-01-01']);
        $sk = Document::factory()->create(['curriculum_id' => $curriculum->id, 'document_type' => Document::RATIFICATION_DECREE, 'title' => 'PRIVATE-SK']);
        Document::factory()->create(['curriculum_id' => $curriculum->id, 'title' => 'Dokumen Publik']);
        $response = $this->get(route('home'))->assertOk()->assertSee('monitoring-table', false)
            ->assertSee('Fakultas')->assertSee('Tahun Perbaikan Kurikulum')->assertSee('Tanggal Pengesahan')
            ->assertSee('Lihat Dokumen (1)')->assertSee('Buka File Kurikulum')->assertSee('Rekonstruksi Kurikulum')
            ->assertDontSee('Cuplikan kurikulum yang telah dipublikasikan oleh program studi.')
            ->assertDontSee('class="curriculum-card"', false)->assertDontSee('href="'.route('monitoring.index').'"', false)
            ->assertDontSee('PRIVATE-SK')->assertDontSee($sk->file_path);
        $this->assertSame([$pa->id, $pb->id], $response->viewData('programs')->pluck('id')->all());
        $this->assertStringContainsString('class="public-button secondary"', $response->getContent());
        $this->get(route('study-programs.curricula.show', [$pa->slug, $curriculum]))->assertOk()->assertDontSee('PRIVATE-SK')->assertSee('Dokumen Publik');
        foreach ([CurriculumStatus::PUBLISHED, CurriculumStatus::ARCHIVED] as $status) {
            $curriculum->update(['status' => $status]);
            $this->get(route('study-programs.curricula.documents.download', [$pa->slug, $curriculum, $sk]))->assertNotFound();
        }
        $this->get(route('monitoring.index'))->assertOk();
    }

    public function test_failed_private_storage_rolls_back_metadata_without_orphan_records(): void
    {
        $actor = $this->actor();
        $disk = Storage::disk('documents');
        Storage::shouldReceive('disk')->with('documents')->andReturn($mock = \Mockery::mock($disk)->makePartial());
        $mock->shouldReceive('putFileAs')->once()->andReturn(false);
        $mock->shouldReceive('delete')->once()->andReturn(true);
        $this->actingAs($actor)->post(route('admin.curricula.store'), $this->data())->assertSessionHasErrors('sk');
        $this->assertDatabaseCount('curricula', 0);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_document_persistence_failure_cleans_uploaded_sk(): void
    {
        $actor = $this->actor();
        $dispatcher = Document::getEventDispatcher();
        Document::setEventDispatcher(clone $dispatcher);
        Document::creating(fn () => throw new RuntimeException('Document persistence failure'));
        try {
            app(CurriculumService::class)->saveMetadata($actor, $this->data());
            $this->fail('Document persistence must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Document persistence failure', $exception->getMessage());
        } finally {
            Document::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('curricula', 0);
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_ordinary_document_upload_cannot_forge_sk_type(): void
    {
        $actor = $this->actor();
        $curriculum = Curriculum::factory()->create(['study_program_id' => $actor->study_program_id]);
        $this->actingAs($actor)->post(route('admin.curricula.documents.store', $curriculum), [
            'title' => 'SK Pengesahan Kurikulum', 'file' => $this->data()['sk'],
            'document_type' => Document::RATIFICATION_DECREE,
        ])->assertSessionHasNoErrors();
        $this->assertSame(Document::CURRICULUM, Document::sole()->document_type);
        $this->assertContains('SK pengesahan kurikulum wajib diunggah.', app(CurriculumSubmissionService::class)->readiness($curriculum)['errors']);
    }

    public function test_submission_post_rechecks_missing_monitoring_requirements(): void
    {
        $actor = $this->actor();
        $curriculum = Curriculum::factory()->withRatification()->create(['study_program_id' => $actor->study_program_id, 'total_semesters' => 1]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id]);
        Document::factory()->create(['curriculum_id' => $curriculum->id]);
        $this->assertTrue(app(CurriculumSubmissionService::class)->readiness($curriculum)['is_ready']);
        foreach (['ratified_at', 'external_document_url'] as $field) {
            $original = $curriculum->{$field};
            $curriculum->update([$field => null]);
            $this->actingAs($actor)->post(route('admin.curricula.submit.store', $curriculum))->assertSessionHasErrors();
            $this->assertSame(CurriculumStatus::DRAFT, $curriculum->fresh()->status);
            $curriculum->update([$field => $original]);
        }
        $curriculum->documents()->where('document_type', Document::RATIFICATION_DECREE)->delete();
        $this->post(route('admin.curricula.submit.store', $curriculum))->assertSessionHasErrors();
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
