<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InternalShellTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{UserRole, string, string}> */
    public static function roles(): array
    {
        return [
            'university' => [UserRole::UNIVERSITY_ADMIN, 'Admin Universitas', 'university-admin.monitoring.index'],
            'faculty' => [UserRole::FACULTY_ADMIN, 'Admin Fakultas', 'faculty-admin.monitoring.index'],
            'prodi' => [UserRole::STUDY_PROGRAM_ADMIN, 'Admin Program Studi', 'admin.curricula.index'],
            'head' => [UserRole::HEAD_OF_STUDY_PROGRAM, 'Ketua Program Studi', 'kaprodi.submissions.index'],
        ];
    }

    #[DataProvider('roles')]
    public function test_shell_shows_identity_scope_and_only_relevant_navigation(UserRole $role, string $label, string $area): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create([
            'name' => 'Pengguna Internal Dengan Nama Panjang Untuk Pengujian Tampilan',
            'role' => $role,
            'is_active' => true,
            'faculty_id' => $program->faculty_id,
            'study_program_id' => $program->id,
        ]);
        $scope = match ($role) {
            UserRole::UNIVERSITY_ADMIN => 'Universitas Negeri Gorontalo',
            UserRole::FACULTY_ADMIN => $program->faculty->name,
            default => $program->name,
        };

        $response = $this->actingAs($user)->get(route('dashboard', ['faculty_id' => 999, 'study_program_id' => 999]));
        $response->assertOk()->assertSee('Menu utama')->assertSee($user->name)
            ->assertSee($label)->assertSee($scope)
            ->assertSee('href="'.route($area).'"', false)
            ->assertSee('method="POST" action="'.route('logout').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('aria-controls="internal-sidebar"', false);

        foreach (self::roles() as [$otherRole, $otherLabel, $otherArea]) {
            if ($otherRole !== $role) {
                $response->assertDontSee('href="'.route($otherArea).'"', false);
                $this->get(route($otherArea))->assertForbidden();
            }
        }

        $this->get(route($area))->assertOk()->assertSee('Menu utama')
            ->assertSee('Jejak navigasi')->assertSee($label)->assertSee($scope);
    }

    public function test_guests_cannot_access_shell_and_login_keeps_its_existing_layout(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Masuk ke akun Anda')
            ->assertDontSee('id="internal-sidebar"', false);
    }

    #[DataProvider('roles')]
    public function test_sidebar_icons_are_consistent_decorative_and_role_specific(UserRole $role, string $label, string $area): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['role' => $role, 'is_active' => true, 'faculty_id' => $program->faculty_id, 'study_program_id' => $program->id]);
        $expected = ['Dashboard' => 'dashboard'] + match ($role) {
            UserRole::UNIVERSITY_ADMIN => ['Monitoring Kurikulum' => 'monitoring', 'Fakultas' => 'faculty', 'Program Studi' => 'study-program', 'Pengguna' => 'users'],
            UserRole::FACULTY_ADMIN => ['Monitoring Kurikulum' => 'monitoring'],
            UserRole::STUDY_PROGRAM_ADMIN => ['Kurikulum' => 'curriculum', 'Mata Kuliah' => 'courses'],
            UserRole::HEAD_OF_STUDY_PROGRAM => ['Review Kurikulum' => 'review'],
        };
        $response = $this->actingAs($user)->get(route($area))->assertOk()->assertDontSee('nav-symbol')
            ->assertSee('data-sidebar-open', false)->assertSee('data-sidebar-close', false)
            ->assertSee('aria-controls="internal-sidebar"', false)->assertSee('internal.js');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//ul[@class="sidebar-menu"]/li/a');
        $this->assertCount(count($expected), $links);
        foreach ($links as $link) {
            $name = trim($link->textContent);
            $this->assertArrayHasKey($name, $expected);
            $icons = $xpath->query('./svg', $link);
            $this->assertSame(1, $icons->length);
            $icon = $icons->item(0);
            foreach (['data-icon' => $expected[$name], 'width' => '22', 'height' => '22', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'aria-hidden' => 'true', 'focusable' => 'false'] as $attribute => $value) {
                $this->assertSame($value, $icon->getAttribute($attribute));
            }
        }
        $active = $xpath->query('//ul[@class="sidebar-menu"]//a[@aria-current="page"]');
        $this->assertSame(1, $active->length);
        $this->assertSame(route($area), $active->item(0)->getAttribute('href'));
    }

    public function test_admin_sidebars_only_link_to_current_production_areas(): void
    {
        $program = StudyProgram::factory()->create();
        foreach ([UserRole::FACULTY_ADMIN, UserRole::UNIVERSITY_ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true, 'faculty_id' => $program->faculty_id]);
            $routes = $role === UserRole::FACULTY_ADMIN
                ? ['dashboard', 'faculty-admin.monitoring.index']
                : ['dashboard', 'university-admin.monitoring.index', 'university-admin.faculties.index', 'university-admin.study-programs.index', 'university-admin.users.index'];
            $response = $this->actingAs($user)->get(route($routes[1]))->assertOk()
                ->assertDontSee('Monitoring Fakultas')->assertDontSee('Administrasi Universitas');
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $links = $xpath->query('//ul[@class="sidebar-menu"]/li/a');
            $this->assertSame(array_map(fn (string $name): string => route($name), $routes), array_map(fn ($link) => $link->getAttribute('href'), iterator_to_array($links)));
            $active = $xpath->query('//ul[@class="sidebar-menu"]//a[@aria-current="page"]');
            $this->assertSame(1, $active->length);
            $this->assertSame(route($routes[1]), $active->item(0)->getAttribute('href'));
        }
    }

    public function test_removed_landing_routes_are_absent_and_resource_breadcrumbs_remain_valid(): void
    {
        foreach (['university-admin', 'faculty-admin'] as $prefix) {
            $this->assertFalse(Route::has($prefix.'.dashboard'));
            $this->get('/'.$prefix)->assertNotFound();
            $this->assertTrue(Route::has($prefix.'.curricula.show'));
        }
        $program = StudyProgram::factory()->create();
        $curriculum = Curriculum::factory()->create(['study_program_id' => $program->id]);
        foreach (['university-admin' => UserRole::UNIVERSITY_ADMIN, 'faculty-admin' => UserRole::FACULTY_ADMIN] as $prefix => $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true, 'faculty_id' => $program->faculty_id]);
            $this->actingAs($user)->get('/'.$prefix)->assertNotFound();
            $this->get(route($prefix.'.curricula.show', $curriculum))->assertOk()
                ->assertSee('href="'.route($prefix.'.monitoring.index').'"', false)
                ->assertDontSee('Administrasi Universitas')->assertDontSee('Monitoring Fakultas');
        }
    }

    #[DataProvider('roles')]
    public function test_missing_optional_scope_relationships_render_gracefully(UserRole $role, string $label, string $area): void
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $expected = match ($role) {
            UserRole::UNIVERSITY_ADMIN => 'Universitas Negeri Gorontalo',
            UserRole::FACULTY_ADMIN => 'Fakultas belum ditetapkan',
            default => 'Program studi belum ditetapkan',
        };

        $this->actingAs($user);
        if ($role !== UserRole::UNIVERSITY_ADMIN) {
            $this->get(route($area))->assertForbidden();
            $this->get(route('dashboard'))->assertOk()->assertSee($expected);
        } else {
            $this->get(route($area))->assertOk()->assertSee($expected);
        }
    }

    public function test_notifications_count_only_unread_records_owned_by_current_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]);
        $other = User::factory()->create();
        $user->notifications()->create(['type' => 'curriculum.submitted', 'title' => 'Pengajuan', 'message' => 'Pesan privat', 'read_at' => null]);
        $user->notifications()->create(['type' => 'curriculum.submitted', 'title' => 'Dibaca', 'message' => 'Pesan lama', 'read_at' => now()]);
        $other->notifications()->create(['type' => 'curriculum.submitted', 'title' => 'Rahasia pengguna lain', 'message' => 'Pesan lain']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Notifikasi: 1 belum dibaca')->assertSee('1 notifikasi belum dibaca.')
            ->assertDontSee('Rahasia pengguna lain');
        $this->assertNull($user->notifications()->where('title', 'Pengajuan')->first()->read_at);
    }

    public function test_notification_empty_state_and_constant_query_count(): void
    {
        $user = User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]);
        $this->actingAs($user);
        DB::enableQueryLog();

        $this->get(route('dashboard'))->assertOk()->assertSee('Tidak ada notifikasi yang belum dibaca.');
        $notificationQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'notifications'));
        $this->assertCount(1, $notificationQueries);

        DB::disableQueryLog();
    }

    public function test_resource_breadcrumbs_and_active_navigation_use_named_routes(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => $program->id]);
        $curriculum = Curriculum::factory()->create(['name' => 'Kurikulum 2025', 'study_program_id' => $program->id]);

        $response = $this->actingAs($user)->get(route('admin.curricula.edit', $curriculum))->assertOk()
            ->assertSeeInOrder(['Jejak navigasi', 'Dashboard', 'Kurikulum', 'Edit Kurikulum', 'Kurikulum 2025'])
            ->assertSee('href="'.route('admin.curricula.index').'"', false);

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.curricula.index'), '/').'"\s+aria-current="page"/', $response->getContent());
    }

    public function test_flash_messages_validation_errors_and_identity_are_escaped(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>', 'role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]);
        Route::post('/shell-message-test', fn () => redirect()->route('dashboard')
            ->with('status', 'Perubahan disimpan.')
            ->with('error', '<script>unsafe()</script>')
            ->withErrors(['name' => 'Nama wajib diisi.']))->middleware(['web', 'auth:web']);

        $this->actingAs($user)->followingRedirects()->post('/shell-message-test')
            ->assertOk()->assertSee('Perubahan disimpan.')
            ->assertSee('Nama wajib diisi.')->assertSee($user->name)
            ->assertDontSee($user->name, false)->assertDontSee('<script>unsafe()</script>', false);
    }

    public function test_scope_protection_still_rejects_foreign_resource_with_shell_navigation(): void
    {
        $program = StudyProgram::factory()->create();
        $user = User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true, 'study_program_id' => $program->id]);
        $foreign = Curriculum::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->get(route('admin.curricula.show', ['curriculum' => $foreign->id, 'study_program_id' => $program->id]))
            ->assertForbidden()->assertDontSee($foreign->name);
    }
}
