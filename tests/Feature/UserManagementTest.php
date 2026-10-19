<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Curriculum;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    private function payload(UserRole $role = UserRole::UNIVERSITY_ADMIN): array
    {
        return [
            'name' => 'Pengguna Baru', 'email' => 'baru@example.org', 'role' => $role->value,
            'password' => 'KataSandiAman123!', 'password_confirmation' => 'KataSandiAman123!', 'is_active' => '1',
        ];
    }

    /** @return array<int, array{string, string}> */
    private function endpoints(User $user): array
    {
        return [
            ['GET', route('university-admin.users.index')],
            ['GET', route('university-admin.users.create')],
            ['POST', route('university-admin.users.store')],
            ['GET', route('university-admin.users.edit', $user)],
            ['PUT', route('university-admin.users.update', $user)],
            ['PATCH', route('university-admin.users.update', $user)],
        ];
    }

    /** @return array<string, array{UserRole}> */
    public static function roles(): array
    {
        return array_combine(array_column(UserRole::cases(), 'value'), array_map(fn (UserRole $role): array => [$role], UserRole::cases()));
    }

    /** @return array<string, array{UserRole, bool}> */
    public static function deniedUsers(): array
    {
        return [
            'faculty' => [UserRole::FACULTY_ADMIN, true],
            'prodi' => [UserRole::STUDY_PROGRAM_ADMIN, true],
            'head' => [UserRole::HEAD_OF_STUDY_PROGRAM, true],
            'inactive university' => [UserRole::UNIVERSITY_ADMIN, false],
        ];
    }

    public function test_guests_redirect_from_every_endpoint(): void
    {
        $target = $this->administrator();
        foreach ($this->endpoints($target) as [$method, $url]) {
            $this->call($method, $url, $this->payload())->assertRedirect(route('login'));
        }
    }

    #[DataProvider('deniedUsers')]
    public function test_other_roles_and_inactive_admin_cannot_access_or_mutate(UserRole $role, bool $active): void
    {
        $target = $this->administrator();
        $before = $target->fresh()->getAttributes();
        $actor = User::factory()->create(['role' => $role, 'is_active' => $active]);
        $this->actingAs($actor);
        foreach ($this->endpoints($target) as [$method, $url]) {
            $this->call($method, $url, $this->payload())->assertForbidden();
        }
        $this->assertSame($before, $target->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_pages_show_safe_fields_and_accessible_forms(): void
    {
        $target = User::factory()->create(['role' => UserRole::FACULTY_ADMIN, 'name' => '<script>unsafe()</script>', 'is_active' => true]);
        $this->actingAs($this->administrator());
        $this->get(route('university-admin.users.index'))->assertOk()->assertSee('Manajemen Pengguna')
            ->assertSee($target->name)->assertDontSee($target->name, false)->assertSee('Admin Fakultas')->assertSee('Fakultas belum ditetapkan');
        $this->get(route('university-admin.users.create'))->assertOk()->assertSee('Tambah Pengguna')
            ->assertSee('name="_token"', false)->assertSee('data-user-role', false);
        $this->get(route('university-admin.users.edit', $target))->assertOk()->assertSee('value="PUT"', false)
            ->assertDontSee($target->password, false)->assertDontSee('Hapus Pengguna')
            ->assertDontSee('name="last_login_at"', false)->assertDontSee('name="remember_token"', false)
            ->assertSee('href="'.route('university-admin.users.index').'"  aria-current="page"', false);
        $this->assertStringEndsWith('/pengguna/tambah', route('university-admin.users.create'));
    }

    #[DataProvider('roles')]
    public function test_create_normalizes_role_scope_and_audits_only_safe_fields(UserRole $role): void
    {
        $program = StudyProgram::factory()->create();
        $forgedFaculty = Faculty::factory()->create();
        $actor = $this->administrator();
        $this->actingAs($actor)->post(route('university-admin.users.store'), [
            ...$this->payload($role), 'email' => ' BARU@EXAMPLE.ORG ',
            'faculty_id' => $forgedFaculty->id, 'study_program_id' => $program->id,
            'id' => 99999, 'last_login_at' => '2000-01-01', 'remember_token' => 'injected',
            'email_verified_at' => '2000-01-01', 'created_at' => '2000-01-01',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success')
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $user = User::query()->where('email', 'baru@example.org')->sole();
        $this->assertSame($role, $user->role);
        $this->assertSame(match ($role->scopeField()) {
            'faculty_id' => $forgedFaculty->id,
            'study_program_id' => $program->faculty_id,
            default => null,
        }, $user->faculty_id);
        $this->assertSame($role->scopeField() === 'study_program_id' ? $program->id : null, $user->study_program_id);
        $this->assertTrue(Hash::check($this->payload()['password'], $user->password));
        $this->assertNotSame($this->payload()['password'], $user->password);
        $this->assertNull($user->last_login_at);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->remember_token);
        $this->assertNotSame(99999, $user->id);
        $this->assertTrue($user->is_active);
        $audit = AuditLog::query()->sole();
        $this->assertSame('user.created', $audit->action);
        $this->assertSame(User::class, $audit->entity_type);
        $this->assertSame($user->id, $audit->entity_id);
        $this->assertSame($actor->id, $audit->user_id);
        $this->assertNull($audit->old_values);
        $this->assertEqualsCanonicalizing(['name', 'email', 'role', 'faculty_id', 'study_program_id', 'is_active'], array_keys($audit->new_values));
        $this->assertStringNotContainsString($user->password, $audit->toJson());
        $this->assertStringNotContainsString($this->payload()['password'], $audit->toJson());
    }

    public function test_unused_scope_fields_are_ignored_even_when_malformed(): void
    {
        $this->actingAs($this->administrator())->post(route('university-admin.users.store'), [
            ...$this->payload(), 'faculty_id' => ['invalid'], 'study_program_id' => ['invalid'],
        ])->assertSessionHasNoErrors();
        $user = User::query()->where('email', 'baru@example.org')->sole();
        $this->assertNull($user->faculty_id);
        $this->assertNull($user->study_program_id);
    }

    public function test_required_fields_email_uniqueness_and_password_errors_do_not_flash_secrets(): void
    {
        $this->actingAs($this->administrator());
        $this->post(route('university-admin.users.store'), [])->assertSessionHasErrors(['name', 'email', 'role', 'password']);
        User::factory()->create(['email' => 'baru@example.org']);
        $this->post(route('university-admin.users.store'), $this->payload())->assertSessionHasErrors('email')
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array<string, array{array<string, mixed>, array<string>}> */
    public static function invalidData(): array
    {
        return [
            'confirmation missing' => [['password_confirmation' => null], ['password', 'password_confirmation']],
            'confirmation wrong' => [['password_confirmation' => 'wrong'], ['password']],
            'short password' => [['password' => 'short', 'password_confirmation' => 'short'], ['password']],
            'long password' => [['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)], ['password']],
            'invalid email' => [['email' => 'not-an-email'], ['email']],
            'unknown role' => [['role' => 'student'], ['role']],
            'invalid role type' => [['role' => ['university_admin']], ['role']],
            'invalid status' => [['is_active' => 'yes'], ['is_active']],
            'long name' => [['name' => str_repeat('a', 256)], ['name']],
            'long email' => [['email' => str_repeat('a', 245).'@example.org'], ['email']],
        ];
    }

    #[DataProvider('invalidData')]
    public function test_invalid_input_is_rejected_safely(array $changes, array $errors): void
    {
        $this->actingAs($this->administrator())->post(route('university-admin.users.store'), [...$this->payload(), ...$changes])
            ->assertSessionHasErrors($errors)->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_errors_are_indonesian_and_password_inputs_remain_empty(): void
    {
        $this->actingAs($this->administrator())->followingRedirects()->from(route('university-admin.users.create'))
            ->post(route('university-admin.users.store'), [...$this->payload(), 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertOk()->assertSee('Kata sandi minimal 12 karakter.')->assertDontSee('value="short"', false)
            ->assertSee('value="Pengguna Baru"', false);
    }

    public function test_new_scope_assignments_reject_missing_nonexistent_and_inactive_units(): void
    {
        $inactiveFaculty = Faculty::factory()->create(['is_active' => false]);
        $inactiveProgram = StudyProgram::factory()->create(['is_active' => false]);
        $programInInactiveFaculty = StudyProgram::factory()->create(['faculty_id' => $inactiveFaculty->id]);
        $this->actingAs($this->administrator());
        foreach ([UserRole::FACULTY_ADMIN, UserRole::STUDY_PROGRAM_ADMIN, UserRole::HEAD_OF_STUDY_PROGRAM] as $role) {
            $field = $role->scopeField();
            $ids = $field === 'faculty_id' ? [null, 99999, $inactiveFaculty->id] : [null, 99999, $inactiveProgram->id, $programInInactiveFaculty->id];
            foreach ($ids as $id) {
                $this->post(route('university-admin.users.store'), [...$this->payload($role), $field => $id])
                    ->assertSessionHasErrors($field)->assertSessionMissing('_old_input.password');
            }
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_unchecked_status_creates_inactive_user_that_cannot_login(): void
    {
        $this->actingAs($this->administrator());
        $data = $this->payload();
        unset($data['is_active']);
        $this->post(route('university-admin.users.store'), $data)->assertSessionHasNoErrors();
        $target = User::query()->where('email', $data['email'])->sole();
        $this->assertFalse($target->is_active);
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $data['email'], 'password' => $data['password']])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_profile_update_preserves_blank_password_and_ignores_protected_fields(): void
    {
        $target = $this->administrator();
        $oldHash = $target->password;
        $oldToken = $target->remember_token;
        $this->actingAs($this->administrator())->put(route('university-admin.users.update', $target), [
            ...$this->payload(), 'name' => 'Nama Baru', 'email' => $target->email,
            'password' => '', 'password_confirmation' => '', 'last_login_at' => '2000-01-01',
            'id' => 9999, 'remember_token' => 'injected', 'email_verified_at' => '2000-01-01',
        ])->assertRedirect(route('university-admin.users.edit', $target))->assertSessionHasNoErrors();
        $fresh = $target->fresh();
        $this->assertSame('Nama Baru', $fresh->name);
        $this->assertSame($oldHash, $fresh->password);
        $this->assertSame($oldToken, $fresh->remember_token);
        $this->assertNull($fresh->last_login_at);
        $this->assertTrue($target->email_verified_at->equalTo($fresh->email_verified_at));
        $audit = AuditLog::query()->sole();
        $this->assertSame('user.updated', $audit->action);
        $this->assertSame($target->name, $audit->old_values['name']);
        $this->assertSame('Nama Baru', $audit->new_values['name']);
        foreach ([$audit->old_values, $audit->new_values] as $values) {
            $this->assertEqualsCanonicalizing(['name', 'email', 'role', 'faculty_id', 'study_program_id', 'is_active'], array_keys($values));
        }
    }

    public function test_duplicate_email_on_update_cannot_ignore_forged_user_id(): void
    {
        $target = $this->administrator();
        $other = $this->administrator();
        $this->actingAs($this->administrator())->put(route('university-admin.users.update', $target), [
            ...$this->payload(), 'email' => $other->email, 'id' => $other->id, 'user' => $other->id,
        ])->assertSessionHasErrors('email');
        $this->assertSame($target->email, $target->fresh()->email);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_password_update_authenticates_with_new_password_and_rejects_old_password(): void
    {
        $target = $this->administrator();
        $this->actingAs($this->administrator())->put(route('university-admin.users.update', $target), [
            ...$this->payload(), 'email' => $target->email,
        ])->assertSessionHasNoErrors()->assertSessionMissing('_old_input.password');
        $this->assertTrue(Hash::check($this->payload()['password'], $target->fresh()->password));
        $audit = AuditLog::query()->sole();
        $this->assertStringNotContainsString('password', $audit->toJson());
        $this->assertStringNotContainsString($target->fresh()->password, $audit->toJson());
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => $target->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['email' => $target->email, 'password' => $this->payload()['password']])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($target);
    }

    /** @return array<string, array{UserRole, UserRole}> */
    public static function roleChanges(): array
    {
        $cases = [];
        foreach (UserRole::cases() as $from) {
            foreach (UserRole::cases() as $to) {
                if ($from !== $to) {
                    $cases[$from->value.' to '.$to->value] = [$from, $to];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('roleChanges')]
    public function test_role_changes_normalize_all_stale_scope_fields(UserRole $from, UserRole $to): void
    {
        $oldProgram = StudyProgram::factory()->create();
        $newProgram = StudyProgram::factory()->create();
        $faculty = Faculty::factory()->create();
        $target = User::factory()->create(['role' => $from, 'faculty_id' => $oldProgram->faculty_id, 'study_program_id' => $oldProgram->id, 'is_active' => true]);
        $this->actingAs($this->administrator())->patch(route('university-admin.users.update', $target), [
            ...$this->payload($to), 'faculty_id' => $faculty->id, 'study_program_id' => $newProgram->id,
        ])->assertSessionHasNoErrors();
        $fresh = $target->fresh();
        $this->assertSame($to, $fresh->role);
        $this->assertSame(match ($to->scopeField()) {
            'faculty_id' => $faculty->id,
            'study_program_id' => $newProgram->faculty_id,
            default => null,
        }, $fresh->faculty_id);
        $this->assertSame($to->scopeField() === 'study_program_id' ? $newProgram->id : null, $fresh->study_program_id);
    }

    public function test_existing_inactive_scopes_can_be_retained_but_not_newly_assigned(): void
    {
        $faculty = Faculty::factory()->create(['is_active' => false]);
        $program = StudyProgram::factory()->create(['faculty_id' => $faculty->id, 'is_active' => false]);
        $otherFaculty = Faculty::factory()->create(['is_active' => false]);
        $otherProgram = StudyProgram::factory()->create(['is_active' => false]);
        $this->actingAs($this->administrator());
        foreach ([UserRole::FACULTY_ADMIN, UserRole::STUDY_PROGRAM_ADMIN, UserRole::HEAD_OF_STUDY_PROGRAM] as $role) {
            $target = User::factory()->create(['role' => $role, 'faculty_id' => $faculty->id, 'study_program_id' => $role->scopeField() === 'study_program_id' ? $program->id : null]);
            $field = $role->scopeField();
            $id = $field === 'faculty_id' ? $faculty->id : $program->id;
            $response = $this->get(route('university-admin.users.edit', $target))->assertOk()->assertSee('scope saat ini');
            $options = $response->viewData($field === 'faculty_id' ? 'faculties' : 'studyPrograms')->modelKeys();
            $this->assertContains($id, $options);
            $this->assertNotContains($field === 'faculty_id' ? $otherFaculty->id : $otherProgram->id, $options);
            $this->put(route('university-admin.users.update', $target), [...$this->payload($role), 'email' => $target->email, $field => $id])->assertSessionHasNoErrors();
            $this->assertSame($faculty->id, $target->fresh()->faculty_id);
            $this->put(route('university-admin.users.update', $target), [
                ...$this->payload($role), 'email' => $target->email, $field => $field === 'faculty_id' ? $otherFaculty->id : $otherProgram->id,
            ])->assertSessionHasErrors($field);
        }
    }

    public function test_self_deactivation_and_demotion_are_rejected_server_side(): void
    {
        $actor = $this->administrator();
        $faculty = Faculty::factory()->create();
        $this->actingAs($actor);
        $this->put(route('university-admin.users.update', $actor), [...$this->payload(), 'email' => $actor->email, 'is_active' => '0'])
            ->assertSessionHasErrors(['is_active' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        $this->patch(route('university-admin.users.update', $actor), [...$this->payload(UserRole::FACULTY_ADMIN), 'email' => $actor->email, 'faculty_id' => $faculty->id])
            ->assertSessionHasErrors('role');
        $this->assertTrue($actor->fresh()->is_active);
        $this->assertSame(UserRole::UNIVERSITY_ADMIN, $actor->fresh()->role);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->put(route('university-admin.users.update', $actor), [...$this->payload(), 'email' => $actor->email, 'faculty_id' => $faculty->id, 'study_program_id' => 999])
            ->assertSessionHasNoErrors();
        $this->assertNull($actor->fresh()->faculty_id);
        $this->assertNull($actor->fresh()->study_program_id);
    }

    public function test_deactivation_blocks_existing_session_and_preserves_history(): void
    {
        $target = $this->administrator();
        $curriculum = Curriculum::factory()->create(['created_by' => $target->id, 'updated_by' => $target->id]);
        $this->post(route('login.store'), ['email' => $target->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $sessionKey = Auth::guard('web')->getName();
        $this->get(route('dashboard'))->assertOk();
        $actor = $this->administrator();
        $this->actingAs($actor)->put(route('university-admin.users.update', $target), [
            ...$this->payload(), 'email' => $target->email, 'password' => '', 'password_confirmation' => '', 'is_active' => '0',
        ])->assertSessionHasNoErrors();
        Auth::forgetGuards();
        $this->withSession([$sessionKey => $target->id])->get(route('dashboard'))->assertForbidden();
        $this->get(route('university-admin.users.index'))->assertForbidden();
        $this->assertDatabaseHas('curricula', ['id' => $curriculum->id, 'created_by' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $target->id]);
        $this->actingAs($actor)->patch(route('university-admin.users.update', $target), [
            ...$this->payload(), 'email' => $target->email, 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_index_search_filters_scope_labels_and_pagination(): void
    {
        $program = StudyProgram::factory()->create();
        $facultyUser = User::factory()->create(['name' => 'Unik Fakultas', 'email' => 'faculty@example.org', 'role' => UserRole::FACULTY_ADMIN, 'faculty_id' => $program->faculty_id, 'is_active' => true]);
        $prodiUser = User::factory()->create(['name' => 'Unik Prodi', 'role' => UserRole::STUDY_PROGRAM_ADMIN, 'faculty_id' => $program->faculty_id, 'study_program_id' => $program->id, 'is_active' => false]);
        $this->actingAs($this->administrator());
        $this->get(route('university-admin.users.index'))->assertOk()->assertSee($program->faculty->name)->assertSee($program->name)
            ->assertSee('Admin Program Studi')->assertSee('Universitas Negeri Gorontalo')->assertSee('Tidak Aktif');
        foreach ([' Unik Fakultas ', ' faculty@example.org '] as $search) {
            $this->get(route('university-admin.users.index', ['search' => $search]))->assertOk()
                ->assertViewHas('users', fn ($users): bool => $users->total() === 1 && $users->first()->id === $facultyUser->id);
        }
        foreach (['1' => $facultyUser, '0' => $prodiUser] as $status => $expected) {
            $this->get(route('university-admin.users.index', ['faculty_id' => $program->faculty_id, 'status' => $status]))->assertOk()
                ->assertViewHas('users', fn ($users): bool => $users->total() === 1 && $users->first()->id === $expected->id);
        }
        User::factory()->count(17)->create(['name' => 'Pencarian', 'role' => UserRole::FACULTY_ADMIN, 'faculty_id' => $program->faculty_id, 'is_active' => false]);
        $filters = ['search' => 'Pencarian', 'role' => UserRole::FACULTY_ADMIN->value, 'faculty_id' => $program->faculty_id, 'status' => '0'];
        $rows = $this->get(route('university-admin.users.index', $filters))->assertOk()->viewData('users');
        $this->assertSame(17, $rows->total());
        $this->assertCount(15, $rows);
        parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertEquals([...$filters, 'page' => '2'], $query);
        $this->get($rows->nextPageUrl())->assertOk()->assertViewHas('users', fn ($users): bool => $users->count() === 2);
        $this->get(route('university-admin.users.index', ['search' => 'No match']))->assertSee('Tidak ada pengguna yang sesuai');
    }

    public function test_no_delete_or_state_changing_get_and_sidebar_is_university_only(): void
    {
        $target = $this->administrator();
        $this->actingAs($this->administrator())->delete(route('university-admin.users.update', $target))->assertMethodNotAllowed();
        $this->get(route('university-admin.users.update', [$target, 'is_active' => '0']))->assertMethodNotAllowed();
        $this->assertTrue($target->fresh()->is_active);
        foreach (UserRole::cases() as $role) {
            $actor = User::factory()->create(['role' => $role, 'is_active' => true]);
            $response = $this->actingAs($actor)->get(route('dashboard'))->assertOk();
            $link = 'href="'.route('university-admin.users.index').'"';
            if ($role === UserRole::UNIVERSITY_ADMIN) {
                $response->assertSee($link, false);
            } else {
                $response->assertDontSee($link, false);
            }
        }
    }

    /** @return array<string, array{bool}> */
    public static function mutations(): array
    {
        return ['create' => [false], 'update' => [true]];
    }

    #[DataProvider('mutations')]
    public function test_audit_failure_rolls_back_user_and_password(bool $update): void
    {
        $actor = $this->administrator();
        $target = $update ? $this->administrator() : null;
        $before = $target?->fresh()->getAttributes();
        $this->actingAs($actor)->withoutExceptionHandling();
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit unavailable');
        });
        try {
            if ($target) {
                $this->put(route('university-admin.users.update', $target), $this->payload());
            } else {
                $this->post(route('university-admin.users.store'), $this->payload());
            }
            $this->fail('Audit failure must roll back mutation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('users', $update ? 2 : 1);
        if ($target) {
            $this->assertSame($before, $target->fresh()->getAttributes());
        }
    }

    public function test_password_omission_preserves_hash_and_supplied_password_requires_confirmation_on_update(): void
    {
        $target = $this->administrator();
        $data = [...$this->payload(), 'email' => $target->email];
        unset($data['password'], $data['password_confirmation']);
        $this->actingAs($this->administrator())->put(route('university-admin.users.update', $target), $data)->assertSessionHasNoErrors();
        $this->assertSame($target->password, $target->fresh()->password);
        $this->put(route('university-admin.users.update', $target), [...$data, 'password' => 'NewPassword123!'])
            ->assertSessionHasErrors('password_confirmation')->assertSessionMissing('_old_input.password');
        $this->assertSame($target->password, $target->fresh()->password);
    }

    public function test_role_change_does_not_grant_new_responsibility_in_inactive_scope(): void
    {
        $program = StudyProgram::factory()->create(['is_active' => false]);
        $target = User::factory()->create([
            'role' => UserRole::STUDY_PROGRAM_ADMIN, 'faculty_id' => $program->faculty_id,
            'study_program_id' => $program->id, 'is_active' => true,
        ]);
        $this->actingAs($this->administrator())->put(route('university-admin.users.update', $target), [
            ...$this->payload(UserRole::HEAD_OF_STUDY_PROGRAM), 'study_program_id' => $program->id,
        ])->assertSessionHasErrors('study_program_id');
        $this->assertSame(UserRole::STUDY_PROGRAM_ADMIN, $target->fresh()->role);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_index_filters_return_indonesian_validation(): void
    {
        $this->actingAs($this->administrator())->from(route('university-admin.users.index'))
            ->get(route('university-admin.users.index', ['role' => 'student', 'faculty_id' => 99999, 'status' => 'invalid', 'search' => ['invalid'], 'page' => 0]))
            ->assertRedirect(route('university-admin.users.index'))->assertSessionHasErrors(['role', 'faculty_id', 'status', 'search', 'page']);
    }

    public function test_index_eager_loading_keeps_query_count_constant(): void
    {
        $program = StudyProgram::factory()->create();
        User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'faculty_id' => $program->faculty_id, 'study_program_id' => $program->id]);
        $this->actingAs($this->administrator());
        DB::enableQueryLog();
        $this->get(route('university-admin.users.index'))->assertOk();
        $before = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (StudyProgram::factory()->count(5)->create() as $program) {
            User::factory()->create(['role' => UserRole::HEAD_OF_STUDY_PROGRAM, 'faculty_id' => $program->faculty_id, 'study_program_id' => $program->id]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('university-admin.users.index'))->assertOk();
        $this->assertSame($before, count(DB::getQueryLog()));
        $this->assertArrayNotHasKey('password', $response->viewData('users')->first()->getAttributes());
        DB::disableQueryLog();
    }
}
