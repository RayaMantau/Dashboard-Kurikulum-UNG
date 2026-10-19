<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InternalAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{UserRole}> */
    public static function internalRoles(): array
    {
        return array_reduce(UserRole::cases(), function (array $roles, UserRole $role): array {
            $roles[$role->value] = [$role];

            return $roles;
        }, []);
    }

    public function test_guest_can_view_indonesian_login_form(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('Masuk ke akun Anda')
            ->assertSee('Alamat email')
            ->assertSee('Kata sandi')
            ->assertSee('name="_token"', false);
    }

    #[DataProvider('internalRoles')]
    public function test_active_internal_user_can_log_in_and_access_dashboard(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

        $this->get(route('login'));
        $sessionId = session()->getId();
        $token = session()->token();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'role' => 'university_admin',
            'faculty_id' => 999,
            'study_program_id' => 999,
            'is_active' => true,
        ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->assertSame($role, $user->fresh()->role);
        $this->assertNull($user->fresh()->faculty_id);
        $this->assertNull($user->fresh()->study_program_id);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.login']);
        $this->get(route('dashboard'))->assertOk()->assertSee($user->name);
    }

    public function test_wrong_password_is_rejected_and_not_flashed(): void
    {
        $user = User::factory()->create();

        $this->followingRedirects()->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertOk()->assertSee('Email atau kata sandi salah')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest('web');
        $this->assertNull($user->fresh()->last_login_at);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_inactive_user_cannot_log_in_even_with_correct_password(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'is_active' => true,
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest('web');
        $this->assertNull($user->fresh()->last_login_at);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_non_internal_role_cannot_log_in(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['role' => 'student']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create()->refresh();

        $this->actingAs($user, 'web')->get(route('dashboard'))->assertOk();
    }

    public function test_deactivated_user_cannot_access_dashboard_but_can_log_out(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user, 'web')->get(route('dashboard'))->assertForbidden();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_logout_invalidates_session_and_regenerates_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->withSession(['private_value' => 'secret'])
            ->get(route('dashboard'));
        $sessionId = session()->getId();
        $token = session()->token();

        $this->post(route('logout'))->assertRedirect(route('login'))
            ->assertSessionMissing('private_value');

        $this->assertGuest('web');
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.logout']);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->get(route('login'))->assertRedirect(route('dashboard'));
        $this->post(route('login.store'), [])->assertRedirect(route('dashboard'));
    }

    public function test_required_fields_have_indonesian_validation_errors(): void
    {
        $this->followingRedirects()->from(route('login'))->post(route('login.store'), [])
            ->assertOk()->assertSee('Alamat email wajib diisi.')->assertSee('Kata sandi wajib diisi.');

    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->post(route('login.store'), ['email' => 'invalid', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_login_attempts_are_limited_and_can_resume_after_cooldown(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'incorrect',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Terlalu banyak', session('errors')->first('email'));
        $this->assertGuest('web');

        $this->travel(61)->seconds();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_logout_requires_post_and_authentication(): void
    {
        $this->get('/logout')->assertMethodNotAllowed();
        $this->post(route('logout'))->assertRedirect(route('login'));
    }
}
