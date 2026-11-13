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
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_logo_is_available_on_public_login_and_internal_branding(): void
    {
        $path = public_path('images/ung-logo.png');
        $this->assertFileExists($path);
        $this->assertSame('image/png', getimagesize($path)['mime']);
        foreach (['home', 'login', 'dashboard'] as $page) {
            if ($page === 'dashboard') {
                $this->actingAs(User::factory()->create(['is_active' => true, 'role' => UserRole::UNIVERSITY_ADMIN]));
            }
            $response = $this->get(route($page))->assertOk()->assertSee('SIMKUR UNG');
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $logos = $xpath->query('//img[@src="'.asset('images/ung-logo.png').'" and @alt="Logo Universitas Negeri Gorontalo"]');
            $this->assertSame(1, $logos->length);
            $this->assertSame($page === 'dashboard' ? '40' : '48', $logos->item(0)->getAttribute('width'));
        }
    }

    public function test_public_branding_and_cleanup_preserve_navigation_and_login(): void
    {
        $response = $this->get(route('home'))->assertOk()->assertSee('SIMKUR UNG')
            ->assertDontSee('menu-toggle')->assertDontSee('Jelajahi Fakultas')
            ->assertDontSee('Terbuka untuk semua.')->assertDontSee('Tanpa perlu akun.')
            ->assertDontSee('Cari mata kuliah dalam kurikulum aktif dan arsip.')
            ->assertDontSee('Monitoring Kurikulum dan jadwal Monev Program Studi.');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//footer//a[normalize-space(.)="Beranda"]')->length);
        foreach (['home', 'faculties.index', 'study-programs.index', 'search', 'monitoring.index'] as $name) {
            $this->assertSame(1, $xpath->query('//nav[@id="public-navigation"]//a[@href="'.route($name).'"]')->length);
        }
        $this->assertSame(1, $xpath->query('//header//a[@href="'.route('login').'"]')->length);
        $this->assertSame(1, $xpath->query('//div[@class="header-top"]//a[@href="'.route('login').'"]')->length);
        $this->assertSame(0, $xpath->query('//nav[@id="public-navigation"]//a[@href="'.route('login').'"]')->length);
        $response->assertSee('Sistem Informasi Monitoring Kurikulum UNG')->assertDontSee('Sistem Informasi Manajemen Kurikulum');
        $this->get(route('login'))->assertOk()->assertSee('SIMKUR UNG')->assertDontSee('>Beranda</a>', false)
            ->assertSee('name="email"', false)->assertSee('name="password"', false)->assertSee('name="_token"', false);
    }

    private function assertStyledLink(string $html, string $label, string $url): void
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//a[normalize-space(.)="'.$label.'" and @href="'.$url.'"]');
        $this->assertGreaterThan(0, $links->length, $label.' must navigate to '.$url);
        foreach ($links as $link) {
            $this->assertStringContainsString('action-button', $link->getAttribute('class'));
            $this->assertStringContainsString('secondary-button', $link->getAttribute('class'));
        }
    }

    public function test_master_management_lists_and_forms_use_consistent_actions(): void
    {
        $program = StudyProgram::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]);
        $this->actingAs($admin);
        foreach (['faculties' => $program->faculty, 'study-programs' => $program, 'users' => $admin] as $area => $record) {
            $prefix = 'university-admin.'.$area;
            $index = route($prefix.'.index');
            $response = $this->get($index)->assertOk()->assertDontSee('>Reset<', false);
            $this->assertStyledLink($response->getContent(), 'Edit', route($prefix.'.edit', $record));
            foreach (['create', 'edit'] as $action) {
                $response = $this->get(route($prefix.'.'.$action, $action === 'edit' ? $record : []))->assertOk();
                $this->assertStyledLink($response->getContent(), 'Batal', $index);
                foreach (['Fakultas', 'Program Studi', 'Pengguna'] as $label) {
                    $response->assertDontSee('Kembali ke '.$label);
                }
            }
        }
    }

    public function test_prodi_management_forms_keep_cancel_destinations_and_styled_actions(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id]);
        $course = Course::factory()->create(['study_program_id' => $program->id]);
        $assignment = CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        $this->actingAs($user);
        foreach (['admin.courses' => $course, 'admin.curricula' => $curriculum] as $prefix => $record) {
            $response = $this->get(route($prefix.'.index'))->assertOk()->assertDontSee('>Reset<', false);
            $this->assertStyledLink($response->getContent(), 'Edit', route($prefix.'.edit', $record));
            foreach (['create', 'edit'] as $action) {
                $response = $this->get(route($prefix.'.'.$action, $action === 'edit' ? $record : []))->assertOk();
                $this->assertStyledLink($response->getContent(), 'Batal', route($prefix.'.index'));
                $response->assertDontSee('Kembali ke Kurikulum')->assertDontSee('Kembali ke Mata Kuliah');
            }
        }
        foreach (['semesters' => $semester, 'courses' => $assignment] as $area => $record) {
            foreach (['create', 'edit'] as $action) {
                $params = $action === 'edit' ? [$curriculum, $record] : [$curriculum];
                $params['semester'] = $semester->id;
                $response = $this->get(route('admin.curricula.'.$area.'.'.$action, $params))->assertOk();
                $indexParams = $area === 'courses' ? [$curriculum, 'semester' => $semester->id] : [$curriculum];
                $this->assertStyledLink($response->getContent(), 'Batal', route('admin.curricula.'.$area.'.index', $indexParams));
            }
        }
        $response = $this->get(route('admin.curricula.documents.create', $curriculum))->assertOk();
        $this->assertStyledLink($response->getContent(), 'Batal', route('admin.curricula.documents.index', $curriculum));
    }

    public function test_public_login_and_internal_layouts_load_shared_brand_before_surface_styles(): void
    {
        foreach (['home', 'login'] as $route) {
            $this->get(route($route))->assertOk()->assertSeeInOrder(['brand.css', $route === 'home' ? 'public.css' : 'internal.css']);
        }
        $this->actingAs(User::factory()->create(['is_active' => true, 'role' => UserRole::UNIVERSITY_ADMIN]))
            ->get(route('dashboard'))->assertOk()->assertSeeInOrder(['brand.css', 'internal.css']);
        foreach (['brand.css', 'public.css', 'internal.css', 'public.js', 'internal.js'] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }

    public function test_destructive_buttons_keep_delete_forms_and_explicit_danger_identity(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id]);
        $semester = Semester::factory()->create(['curriculum_id' => $curriculum->id]);
        $emptySemester = Semester::factory()->create(['curriculum_id' => $curriculum->id, 'semester_number' => 255]);
        $course = Course::factory()->create(['study_program_id' => $program->id]);
        CurriculumCourse::factory()->create(['curriculum_id' => $curriculum->id, 'semester_id' => $semester->id, 'course_id' => $course->id]);
        Document::factory()->create(['curriculum_id' => $curriculum->id]);
        $this->actingAs($user);
        $this->get(route('admin.curricula.semesters.edit', [$curriculum, $emptySemester]))->assertOk()
            ->assertSee('danger-button', false)->assertSee('value="DELETE"', false)->assertSee('name="_token"', false);
        foreach (['courses', 'documents'] as $area) {
            $this->get(route('admin.curricula.'.$area.'.index', $curriculum))->assertOk()
                ->assertSee('danger-button', false)->assertSee('value="DELETE"', false)->assertSee('name="_token"', false);
        }
    }

    public function test_review_actions_are_distinct_and_error_is_connected_to_revision_notes(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::SUBMITTED]);
        $url = route('kaprodi.submissions.show', $curriculum);
        $this->actingAs($user)->from($url)->post(route('kaprodi.submissions.revision', $curriculum), [])->assertSessionHasErrors('notes');
        $this->withViewErrors(['notes' => 'Catatan revisi wajib diisi.'])->view('kaprodi.submissions.show', ['curriculum' => $curriculum])->assertSee('success-button', false)->assertSee('warning-button', false)
            ->assertSee('aria-describedby="notes-error"', false)->assertSee('id="notes-error"', false);
    }

    public function test_kaprodi_review_buttons_submit_separate_forms_outside_the_card(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id, 'status' => CurriculumStatus::SUBMITTED, 'description' => '<b>Deskripsi</b>']);
        $this->actingAs($user);
        $response = $this->get(route('kaprodi.submissions.show', $curriculum))->assertOk()
            ->assertSee('<b>Deskripsi</b>')->assertDontSee('<b>Deskripsi</b>', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(6, $xpath->query('//table[contains(@class,"review-information")]/tbody/tr')->length);
        $actions = '//div[@class="review-actions"]';
        $this->assertSame(0, $xpath->query($actions.'/ancestor::form | '.$actions.'/ancestor::section[contains(@class,"portal-card")]')->length);
        $this->assertSame(['Setujui Kurikulum', 'Minta Revisi', 'Batal'], array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query($actions.'/*'))));
        foreach (['approve-curriculum' => 'approve', 'revise-curriculum' => 'revision'] as $id => $action) {
            $this->assertSame(1, $xpath->query($actions.'/button[@form="'.$id.'" and @type="submit"]')->length);
            $form = '//form[@id="'.$id.'" and @method="POST" and @action="'.route('kaprodi.submissions.'.$action, $curriculum).'"]';
            $this->assertSame(1, $xpath->query($form.'//input[@name="_token"]')->length);
        }
        $this->assertSame(1, $xpath->query('//form[@id="revise-curriculum"]//textarea[@name="notes" and @required]')->length);
        $this->assertSame(0, $xpath->query('//form[@id="approve-curriculum"]//textarea')->length);
        $this->assertStyledLink($response->getContent(), 'Riwayat Kurikulum', route('internal.curricula.history', $curriculum));
        $this->assertStyledLink($response->getContent(), 'Batal', route('kaprodi.submissions.index'));
        foreach (['dashboard', 'kaprodi.submissions.index'] as $route) {
            $page = $this->get(route($route))->assertOk();
            @$dom->loadHTML($page->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertSame(1, $xpath->query('//a[@href="'.route('kaprodi.submissions.show', $curriculum).'" and contains(@class,"action-button")]')->length);
            $this->assertSame(1, $xpath->query('//main//a[@href="'.route('kaprodi.submissions.approved').'" and contains(@class,"secondary-button")]')->length);
        }
    }

    public function test_assignment_validation_messages_have_field_associations(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['is_active' => true, 'role' => UserRole::STUDY_PROGRAM_ADMIN, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id]);
        Semester::factory()->create(['curriculum_id' => $curriculum->id]);
        Course::factory()->create(['study_program_id' => $program->id]);
        $fields = ['semester_id', 'course_id', 'sks', 'course_type', 'prerequisite_notes'];
        $url = route('admin.curricula.courses.create', $curriculum);
        $this->actingAs($user)->from($url)->post(route('admin.curricula.courses.store', $curriculum), ['prerequisite_notes' => ['invalid']])->assertSessionHasErrors($fields);
        $response = $this->withViewErrors(array_fill_keys($fields, 'Periksa nilai ini.'))->view('admin.curriculum-courses.form', ['assignment' => new CurriculumCourse, 'semesters' => $curriculum->semesters, 'courses' => $program->courses, 'types' => CourseType::cases()]);
        foreach ($fields as $field) {
            $response->assertSee('aria-describedby="'.$field.'-error"', false)->assertSee('id="'.$field.'-error"', false);
        }
    }

    public function test_success_flash_is_semantic_and_escaped(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true, 'role' => UserRole::UNIVERSITY_ADMIN]))
            ->withSession(['success' => '<script>Success</script>'])->get(route('dashboard'))->assertOk()
            ->assertSee('shell-alert-success', false)->assertSee('role="status"', false)
            ->assertSee('<script>Success</script>')->assertDontSee('<script>Success</script>', false);
    }
}
