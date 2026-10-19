<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicCurriculumDocumentTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    private function document(Curriculum $curriculum): Document
    {
        $document = Document::factory()->create(['curriculum_id' => $curriculum->id,
            'file_path' => 'curricula/'.$curriculum->id.'/documents/'.Str::uuid().'.pdf',
            'file_name' => 'Kurikulum.pdf', 'file_type' => 'application/pdf', 'file_size' => strlen(self::PDF)]);
        Storage::disk('documents')->put($document->file_path, self::PDF);

        return $document;
    }

    private function url(Curriculum $curriculum, Document $document): string
    {
        return route('study-programs.curricula.documents.download', [$curriculum->studyProgram->slug, $curriculum->id, $document->id]);
    }

    /** @return array<string, array{CurriculumStatus}> */
    public static function publicStatuses(): array
    {
        return ['published' => [CurriculumStatus::PUBLISHED], 'archived' => [CurriculumStatus::ARCHIVED]];
    }

    #[DataProvider('publicStatuses')]
    public function test_public_documents_are_listed_privately_and_streamed_for_guests_and_internal_users(CurriculumStatus $status): void
    {
        $curriculum = Curriculum::factory()->create(['status' => $status]);
        $document = $this->document($curriculum);
        $document->uploader->update(['name' => 'SECRET-UPLOADER', 'email' => 'secret@example.test']);
        $detail = route('study-programs.curricula.show', [$curriculum->studyProgram->slug, $curriculum->id]);
        $this->get($detail)->assertOk()->assertSee($document->title)->assertSee('Kurikulum.pdf')
            ->assertSee('href="'.$this->url($curriculum, $document).'"', false)
            ->assertDontSee($document->file_path)->assertDontSee('SECRET-UPLOADER')->assertDontSee('secret@example.test')
            ->assertDontSee('private-documents')->assertDontSee('/storage/');
        $response = $this->get($this->url($curriculum, $document).'?path=secret.txt&file_path=secret.txt')
            ->assertOk()->assertDownload('Kurikulum.pdf')->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(self::PDF, $response->streamedContent());
        foreach (UserRole::cases() as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $response = $this->get($this->url($curriculum, $document))->assertOk()->assertDownload('Kurikulum.pdf');
            $this->assertSame(self::PDF, $response->streamedContent());
        }
    }

    /** @return array<string, array{CurriculumStatus}> */
    public static function privateStatuses(): array
    {
        return ['draft' => [CurriculumStatus::DRAFT], 'submitted' => [CurriculumStatus::SUBMITTED], 'revision' => [CurriculumStatus::REVISION], 'approved' => [CurriculumStatus::APPROVED]];
    }

    #[DataProvider('privateStatuses')]
    public function test_private_documents_remain_404_for_guests_and_all_internal_roles(CurriculumStatus $status): void
    {
        $curriculum = Curriculum::factory()->create(['status' => $status]);
        $document = $this->document($curriculum);
        $actors = [null];
        foreach (UserRole::cases() as $role) {
            $actors[] = User::factory()->create(['role' => $role, 'study_program_id' => $curriculum->study_program_id]);
        }
        foreach ($actors as $actor) {
            if ($actor) {
                $this->actingAs($actor);
            }
            $this->get($this->url($curriculum, $document))->assertNotFound()->assertDontSee($document->file_path);
            $this->get(route('study-programs.curricula.show', [$curriculum->studyProgram->slug, $curriculum->id]))
                ->assertNotFound()->assertDontSee($document->title);
        }
    }

    public function test_nested_ownership_unknown_document_and_inactive_parents_return_404(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED]);
        $document = $this->document($curriculum);
        $foreign = Curriculum::factory()->create(['status' => CurriculumStatus::ARCHIVED]);
        $this->get($this->url($foreign, $document))->assertNotFound();
        $this->get(route('study-programs.curricula.documents.download', [$foreign->studyProgram->slug, $curriculum->id, $document->id]))->assertNotFound();
        $this->get(route('study-programs.curricula.documents.download', [$curriculum->studyProgram->slug, $curriculum->id, 999999]))->assertNotFound();
        foreach (['program', 'faculty'] as $parent) {
            $curriculum->studyProgram->update(['is_active' => $parent !== 'program']);
            $curriculum->studyProgram->faculty->update(['is_active' => $parent !== 'faculty']);
            $this->get($this->url($curriculum, $document))->assertNotFound();
            $this->get(route('study-programs.curricula.show', [$curriculum->studyProgram->slug, $curriculum->id]))->assertNotFound();
        }
    }

    public function test_missing_file_is_controlled_404_but_metadata_can_still_be_listed(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED]);
        $document = $this->document($curriculum);
        Storage::disk('documents')->delete($document->file_path);
        $this->get($this->url($curriculum, $document))->assertNotFound()->assertDontSee($document->file_path)->assertDontSee('UnableToReadFile');
        $this->get(route('study-programs.curricula.show', [$curriculum->studyProgram->slug, $curriculum->id]))->assertOk()->assertSee($document->title);
    }

    public function test_unsafe_stored_paths_cannot_stream_arbitrary_files(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED]);
        $document = $this->document($curriculum);
        Storage::disk('documents')->put('secret.txt', 'SECRET-CONTENT');
        foreach (['secret.txt', '../secret.txt', '/etc/passwd', 'C:\\private.pdf', 'curricula/999999/documents/'.Str::uuid().'.pdf', 'curricula/'.$curriculum->id.'/documents/../../secret.txt'] as $path) {
            $document->update(['file_path' => $path]);
            $this->get($this->url($curriculum, $document))->assertNotFound()->assertDontSee('SECRET-CONTENT');
        }
    }

    public function test_safe_filename_headers_escaping_order_and_empty_state(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED]);
        $detail = route('study-programs.curricula.show', [$curriculum->studyProgram->slug, $curriculum->id]);
        $this->get($detail)->assertOk()->assertSee('Belum ada dokumen kurikulum yang tersedia.');
        $first = $this->document($curriculum);
        $first->update(['title' => 'Older document']);
        $document = $this->document($curriculum);
        $document->update(['title' => '<script>TITLE</script>', 'file_name' => "../evil\r\nname.pdf", 'file_size' => 1048576]);
        $this->get($detail)->assertOk()->assertSee($document->title)->assertDontSee($document->title, false)
            ->assertSeeInOrder(['evilname.pdf', 'Older document'])->assertSee('1,00 MB');
        $response = $this->get($this->url($curriculum, $document))->assertOk()->assertDownload('evilname.pdf');
        $this->assertSame(self::PDF, $response->streamedContent());
        $this->assertSame("../evil\r\nname.pdf", $document->fresh()->file_name);
    }

    public function test_detail_and_download_are_read_only_and_listing_does_not_inspect_storage(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::ARCHIVED]);
        $document = $this->document($curriculum);
        $tables = ['documents', 'curricula', 'audit_logs', 'notifications', 'curriculum_reviews', 'semesters', 'curriculum_courses'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $detail = route('study-programs.curricula.show', [$curriculum->studyProgram->slug, $curriculum->id]);
        Storage::shouldReceive('disk')->never();
        $this->get($detail)->assertOk()->assertSee($document->title);
        Storage::clearResolvedInstances();
        $this->app->forgetInstance('filesystem');
        Storage::fake('documents');
        Storage::disk('documents')->put($document->file_path, self::PDF);
        $response = $this->get($this->url($curriculum, $document))->assertOk();
        $this->assertSame(self::PDF, $response->streamedContent());
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
        }
    }

    public function test_document_listing_query_count_does_not_grow_per_document(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED]);
        $this->document($curriculum);
        $url = route('study-programs.curricula.show', [$curriculum->studyProgram->slug, $curriculum->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        Document::factory()->count(10)->create(['curriculum_id' => $curriculum->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get($url)->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame($count, count($queries));
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/\b(users|audit_logs|notifications|curriculum_reviews)\b/i', $query['query']);
        }
    }
}
