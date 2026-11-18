<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\Notification;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Curriculum\MonevReminderService;
use App\Services\NotificationTargetResolver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonevReminderTest extends TestCase
{
    use RefreshDatabase;

    private function recipient(StudyProgram $program, UserRole $role, bool $active = true): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => $active, 'faculty_id' => $program->faculty_id,
            'study_program_id' => $role === UserRole::FACULTY_ADMIN ? null : $program->id]);
    }

    public static function dates(): array
    {
        return [
            ['2024-02-29', '2026-02-27', 0], ['2024-02-29', '2026-02-28', 1],
            ['2024-02-29', '2027-02-28', 1], ['2024-02-29', '2028-02-28', 1],
            ['2024-02-29', '2028-02-29', 2], ['2024-02-29', '2029-01-01', 2],
            [null, '2029-01-01', 0],
        ];
    }

    #[DataProvider('dates')]
    public function test_milestones_catch_up_once_using_date_boundaries(?string $ratified, string $today, int $count): void
    {
        $this->travelTo(CarbonImmutable::parse($today, config('app.timezone')));
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'ratified_at' => $ratified]);
        $recipient = $this->recipient($curriculum->studyProgram, UserRole::STUDY_PROGRAM_ADMIN);
        $this->artisan('curriculum:send-monev-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', $count);
        $this->artisan('curriculum:send-monev-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', $count);
        foreach (Notification::all() as $notification) {
            $this->assertSame($recipient->id, $notification->user_id);
            $this->assertSame($curriculum->id, $notification->data['curriculum_id']);
            $this->assertSame($notification->type, $notification->data['reminder_type']);
            $this->assertSame($notification->type === 'monev_minor_due' ? '2026-02-28' : '2028-02-29', $notification->data['due_date']);
        }
    }

    public function test_only_active_recipients_in_own_scope_receive_notifications_with_safe_targets(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-01'));
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'ratified_at' => '2020-01-01']);
        $program = $curriculum->studyProgram;
        $allowed = collect([UserRole::STUDY_PROGRAM_ADMIN, UserRole::HEAD_OF_STUDY_PROGRAM, UserRole::FACULTY_ADMIN])
            ->map(fn ($role) => $this->recipient($program, $role));
        foreach (UserRole::cases() as $role) {
            $this->recipient(StudyProgram::factory()->create(), $role);
            $this->recipient($program, $role, false);
        }
        $this->recipient($program, UserRole::UNIVERSITY_ADMIN);
        $this->artisan('curriculum:send-monev-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 6);
        $this->assertSame($allowed->pluck('id')->sort()->values()->all(), Notification::query()->distinct()->orderBy('user_id')->pluck('user_id')->all());
        foreach ($allowed as $user) {
            $notifications = Notification::where('user_id', $user->id)->get();
            $targets = app(NotificationTargetResolver::class)->resolve($user, $notifications);
            $this->assertCount(2, $targets);
            $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('Monev Minor Kurikulum')->assertSee('Rekonstruksi Kurikulum');
            foreach ($targets as $target) {
                $this->get($target)->assertOk();
            }
            $other = $this->recipient(StudyProgram::factory()->create(), $user->role);
            $this->assertSame([], app(NotificationTargetResolver::class)->resolve($other, $notifications));
        }
    }

    public static function statuses(): array
    {
        return array_map(fn ($status) => [$status], CurriculumStatus::cases());
    }

    #[DataProvider('statuses')]
    public function test_only_published_status_is_eligible(CurriculumStatus $status): void
    {
        $curriculum = Curriculum::factory()->create(['status' => $status, 'ratified_at' => '2000-01-01']);
        $this->recipient($curriculum->studyProgram, UserRole::STUDY_PROGRAM_ADMIN);
        $this->artisan('curriculum:send-monev-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', $status === CurriculumStatus::PUBLISHED ? 2 : 0);
        $this->assertSame($status, $curriculum->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_inactive_parents_are_excluded_and_only_current_version_receives_reminders(): void
    {
        foreach (['faculty', 'program'] as $inactive) {
            $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'ratified_at' => '2000-01-01']);
            $this->recipient($curriculum->studyProgram, UserRole::HEAD_OF_STUDY_PROGRAM);
            ($inactive === 'faculty' ? $curriculum->studyProgram->faculty : $curriculum->studyProgram)->update(['is_active' => false]);
        }
        $old = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'ratified_at' => '2000-01-01', 'published_at' => now()->subDay()]);
        $current = Curriculum::factory()->create(['study_program_id' => $old->study_program_id, 'status' => CurriculumStatus::PUBLISHED,
            'ratified_at' => '2000-01-01', 'published_at' => now()]);
        $this->recipient($old->studyProgram, UserRole::HEAD_OF_STUDY_PROGRAM);
        $this->artisan('curriculum:send-monev-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame([$current->id], Notification::all()->pluck('data.curriculum_id')->unique()->all());
    }

    public function test_command_is_scheduled_daily_in_application_timezone(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'curriculum:send-monev-reminders'));
        $this->assertNotNull($event);
        $this->assertSame('0 8 * * *', $event->expression);
        $this->assertSame(config('app.timezone'), $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_notification_failure_rolls_back_program_and_retry_delivers_each_milestone_once(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'ratified_at' => '2000-01-01']);
        $this->recipient($curriculum->studyProgram, UserRole::STUDY_PROGRAM_ADMIN);
        $dispatcher = Notification::getEventDispatcher();
        Notification::setEventDispatcher(clone $dispatcher);
        Notification::creating(function (Notification $notification): void {
            if ($notification->type === 'reconstruction_due') {
                throw new \RuntimeException('Notification unavailable');
            }
        });
        try {
            app(MonevReminderService::class)->send();
            $this->fail('Notification must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Notification unavailable', $exception->getMessage());
        } finally {
            Notification::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('notifications', 0);
        $this->artisan('curriculum:send-monev-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_reminder_stays_visible_without_target_after_scope_change_or_missing_curriculum(): void
    {
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'ratified_at' => '2000-01-01']);
        $user = $this->recipient($curriculum->studyProgram, UserRole::HEAD_OF_STUDY_PROGRAM);
        $this->artisan('curriculum:send-monev-reminders')->assertSuccessful();
        $user->update(['study_program_id' => StudyProgram::factory()->create()->id]);
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('Monev Minor Kurikulum')
            ->assertViewHas('targets', []);
        Notification::query()->update(['data' => json_encode(['curriculum_id' => 999999, 'url' => 'https://evil.test'])]);
        $this->get(route('notifications.index'))->assertOk()->assertSee('Rekonstruksi Kurikulum')
            ->assertViewHas('targets', [])->assertDontSee('https://evil.test');
    }
}
