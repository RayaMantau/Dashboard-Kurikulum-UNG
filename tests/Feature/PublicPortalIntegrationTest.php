<?php

namespace Tests\Feature;

use App\Enums\CourseType;
use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Document;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPortalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";

    /** @return array{Curriculum, Semester, Document, Course} */
    private function fixture(CurriculumStatus $status): array
    {
        Storage::fake('documents');
        $curriculum = Curriculum::factory()->create(['status' => $status]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 1]);
        $course = Course::factory()->create(['study_program_id' => $curriculum->study_program_id, 'code' => 'IF101', 'name' => 'Algoritma Lama']);
        CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id, 'course_type' => CourseType::REQUIRED]);
        $document = Document::factory()->create(['curriculum_id' => $curriculum->id, 'file_name' => 'Kurikulum.pdf', 'file_path' => 'curricula/'.$curriculum->id.'/documents/'.Str::uuid().'.pdf']);
        Storage::disk('documents')->put($document->file_path, self::PDF);

        return [$curriculum, $semester, $document, $course];
    }

    /** @return array<string, string> */
    private function urls(Curriculum $curriculum, Semester $semester, Document $document): array
    {
        $program = $curriculum->studyProgram;
        $parameters = [$program->slug, $curriculum->id];

        return [
            'home' => route('home'),
            'faculties.index' => route('faculties.index'),
            'faculties.show' => route('faculties.show', $program->faculty->slug),
            'study-programs.index' => route('study-programs.index'),
            'study-programs.show' => route('study-programs.show', $program->slug),
            'study-programs.curricula.index' => route('study-programs.curricula.index', $program->slug),
            'study-programs.curricula.show' => route('study-programs.curricula.show', $parameters),
            'study-programs.curricula.semesters.show' => route('study-programs.curricula.semesters.show', [...$parameters, $semester->id]),
            'study-programs.curricula.documents.download' => route('study-programs.curricula.documents.download', [...$parameters, $document->id]),
            'search' => route('search', ['q' => 'IF101']),
        ];
    }

    /** @return array<string, array{CurriculumStatus}> */
    public static function publicStatuses(): array
    {
        return ['published' => [CurriculumStatus::PUBLISHED], 'archived' => [CurriculumStatus::ARCHIVED]];
    }

    #[DataProvider('publicStatuses')]
    public function test_complete_public_journey_preserves_snapshots_and_domain_records(CurriculumStatus $status): void
    {
        [$curriculum, $semester, $document, $course] = $this->fixture($status);
        $course->update(['code' => 'IF999', 'name' => 'Algoritma Baru']);
        $document->uploader->update(['name' => 'SECRET-UPLOADER', 'email' => 'SECRET-EMAIL@example.test']);
        $urls = $this->urls($curriculum, $semester, $document);
        $tables = ['faculties', 'study_programs', 'curricula', 'semesters', 'curriculum_courses', 'courses', 'documents', 'curriculum_reviews', 'notifications', 'audit_logs'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $links = [
            'home' => ['faculties.index', 'faculties.show', 'study-programs.index', 'search'],
            'faculties.index' => ['faculties.show'],
            'faculties.show' => ['study-programs.show'],
            'study-programs.index' => ['study-programs.show'],
            'study-programs.show' => ['study-programs.curricula.index'],
            'study-programs.curricula.index' => ['study-programs.curricula.show'],
            'study-programs.curricula.show' => ['study-programs.curricula.semesters.show', 'study-programs.curricula.documents.download'],
            'search' => ['study-programs.curricula.semesters.show'],
        ];
        foreach ($urls as $name => $url) {
            $response = $this->get($url)->assertOk();
            if ($name === 'study-programs.curricula.documents.download') {
                $response->assertDownload('Kurikulum.pdf')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/pdf');
                $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
                $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
                $this->assertSame(self::PDF, $response->streamedContent());

                continue;
            }
            $response->assertSee(' | Dashboard Kurikulum UNG</title>', false)->assertSee('class="public-portal"', false)
                ->assertSee('id="main"', false)->assertSee('Masuk')->assertDontSee('Segera')->assertDontSee('href="#"', false)
                ->assertDontSee('SECRET-')->assertDontSee($document->file_path)->assertDontSee('/storage/');
            foreach ($links[$name] ?? [] as $target) {
                $response->assertSee('href="'.($target === 'search' ? route('search') : $urls[$target]).'"', false);
            }
            if (in_array($name, ['study-programs.curricula.show', 'study-programs.curricula.semesters.show', 'search'], true)) {
                $response->assertSee('IF101')->assertSee('Algoritma Lama')->assertSee('Wajib')
                    ->assertDontSee('IF999')->assertDontSee('Algoritma Baru')
                    ->assertSee($status === CurriculumStatus::ARCHIVED ? 'Arsip' : 'Kurikulum Aktif');
            }
        }
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
        }
    }

    public function test_public_route_inventory_only_allows_get_head_without_authentication(): void
    {
        [$curriculum, $semester, $document] = $this->fixture(CurriculumStatus::PUBLISHED);
        $urls = $this->urls($curriculum, $semester, $document);
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => in_array($route->getName(), array_keys($urls), true));
        $this->assertCount(10, $routes);
        foreach ($routes as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            $this->assertSame(['web'], $route->gatherMiddleware());
        }
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->{$method}(route('search'))->assertStatus(405);
        }
        $this->get('/mata-kuliah/1')->assertNotFound();
    }

    public function test_status_visibility_changes_together_across_all_public_surfaces(): void
    {
        config(['app.debug' => false]);
        [$curriculum, $semester, $document] = $this->fixture(CurriculumStatus::PUBLISHED);
        $urls = $this->urls($curriculum, $semester, $document);
        $admin = User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN]);
        foreach ([false, true] as $authenticated) {
            if ($authenticated) {
                $this->actingAs($admin);
            }
            foreach (CurriculumStatus::cases() as $status) {
                $curriculum->update(['status' => $status]);
                $public = in_array($status, [CurriculumStatus::PUBLISHED, CurriculumStatus::ARCHIVED], true);
                foreach (['study-programs.curricula.show', 'study-programs.curricula.semesters.show', 'study-programs.curricula.documents.download'] as $name) {
                    $response = $this->get($urls[$name])->assertStatus($public ? 200 : 404);
                    if ($public && $name === 'study-programs.curricula.documents.download') {
                        $this->assertSame(self::PDF, $response->streamedContent());
                    }
                    if (! $public) {
                        $response->assertDontSee($curriculum->name)->assertDontSee($document->file_path)->assertDontSee('Stack trace');
                    }
                }
                $results = $this->get($urls['search'])->assertOk()->viewData('results');
                $this->assertSame($public ? 1 : 0, $results->total());
            }
        }
    }

    public function test_parent_deactivation_revokes_all_descendant_surfaces_without_login_bypass(): void
    {
        [$curriculum, $semester, $document] = $this->fixture(CurriculumStatus::ARCHIVED);
        $urls = $this->urls($curriculum, $semester, $document);
        $this->actingAs(User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN]));
        $program = $curriculum->studyProgram;
        foreach ([$program, $program->faculty] as $parent) {
            $parent->update(['is_active' => false]);
            foreach (['study-programs.show', 'study-programs.curricula.index', 'study-programs.curricula.show', 'study-programs.curricula.semesters.show', 'study-programs.curricula.documents.download'] as $name) {
                $this->get($urls[$name])->assertNotFound();
            }
            $this->assertSame(0, $this->get($urls['search'])->assertOk()->viewData('results')->total());
            $parent->update(['is_active' => true]);
            $this->get($urls['study-programs.curricula.show'])->assertOk()->assertSee('Arsip');
            $this->assertSame(1, $this->get($urls['search'])->viewData('results')->total());
        }
    }

    public function test_wrong_nested_pairs_fail_closed_on_all_deep_links(): void
    {
        [$curriculum, $semester, $document] = $this->fixture(CurriculumStatus::PUBLISHED);
        $foreign = Curriculum::factory()->create(['status' => CurriculumStatus::ARCHIVED]);
        $foreignSemester = Semester::factory()->create(['curriculum_id' => $foreign->id]);
        $foreignDocument = Document::factory()->create(['curriculum_id' => $foreign->id]);
        $pairs = [
            route('study-programs.curricula.show', [$foreign->studyProgram->slug, $curriculum->id]),
            route('study-programs.curricula.semesters.show', [$foreign->studyProgram->slug, $curriculum->id, $semester->id]),
            route('study-programs.curricula.documents.download', [$foreign->studyProgram->slug, $curriculum->id, $document->id]),
            route('study-programs.curricula.semesters.show', [$curriculum->studyProgram->slug, $curriculum->id, $foreignSemester->id]),
            route('study-programs.curricula.documents.download', [$curriculum->studyProgram->slug, $curriculum->id, $foreignDocument->id]),
        ];
        foreach ([false, true] as $authenticated) {
            if ($authenticated) {
                $this->actingAs(User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN]));
            }
            foreach ($pairs as $url) {
                $this->get($url)->assertNotFound();
            }
        }
    }

    public function test_duplicate_publications_use_the_same_current_order_without_repair(): void
    {
        $older = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'published_at' => now()->subDay()]);
        $newer = Curriculum::factory()->create(['study_program_id' => $older->study_program_id, 'status' => CurriculumStatus::PUBLISHED, 'published_at' => now()]);
        $tie = Curriculum::factory()->create(['study_program_id' => $older->study_program_id, 'status' => CurriculumStatus::PUBLISHED, 'published_at' => $newer->published_at]);
        $program = $older->studyProgram;
        $this->assertSame($tie->name, $this->get(route('study-programs.show', $program->slug))->assertOk()->viewData('curriculum')->name);
        $this->assertSame($tie->id, $this->get(route('study-programs.curricula.index', $program->slug))->assertOk()->viewData('current')->id);
        $this->assertSame([$tie->id, $newer->id, $older->id], $this->get(route('home'))->assertOk()->viewData('curricula')->modelKeys());
        $this->assertSame(3, $program->curricula()->where('status', CurriculumStatus::PUBLISHED)->count());
    }
}
