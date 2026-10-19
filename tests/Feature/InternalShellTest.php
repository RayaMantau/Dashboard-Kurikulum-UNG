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
            'university' => [UserRole::UNIVERSITY_ADMIN, 'Admin Universitas', 'university-admin.dashboard'],
            'faculty' => [UserRole::FACULTY_ADMIN, 'Admin Fakultas', 'faculty-admin.dashboard'],
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
    public function test_missing_optional_scope_relationships_render_gracefully(UserRole $role, string $label, string $area): void
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $expected = match ($role) {
            UserRole::UNIVERSITY_ADMIN => 'Universitas Negeri Gorontalo',
            UserRole::FACULTY_ADMIN => 'Fakultas belum ditetapkan',
            default => 'Program studi belum ditetapkan',
        };

        $this->actingAs($user);
        if (in_array($role, [UserRole::STUDY_PROGRAM_ADMIN, UserRole::HEAD_OF_STUDY_PROGRAM], true)) {
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
