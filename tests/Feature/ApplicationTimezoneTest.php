<?php

namespace Tests\Feature;

use App\Enums\CurriculumStatus;
use App\Enums\UserRole;
use App\Models\Curriculum;
use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApplicationTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_uses_makassar_timezone(): void
    {
        $this->assertSame('Asia/Makassar', config('app.timezone'));
        $this->assertSame('Asia/Makassar', date_default_timezone_get());
    }

    public function test_daily_reminder_runs_at_eight_wita_not_eight_utc(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'curriculum:send-monev-reminders'));
        $this->assertNotNull($event);
        $this->assertSame('Asia/Makassar', $event->timezone);
        foreach (['2026-11-19 00:00:00' => true, '2026-11-19 08:00:00' => false, '2026-11-18 23:59:00' => false] as $utc => $due) {
            $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
            $this->assertSame($due, $event->isDue($this->app), $utc);
        }
    }

    public static function midnightBoundaries(): array
    {
        return [
            'before minor in WITA' => ['2026-02-27 15:59:59', 0],
            'exact minor in WITA' => ['2026-02-27 16:00:00', 1],
            'UTC catches up to minor date' => ['2026-02-28 00:00:00', 1],
            'before reconstruction in WITA' => ['2028-02-28 15:59:59', 1],
            'exact reconstruction leap day in WITA' => ['2028-02-28 16:00:00', 2],
            'UTC catches up to reconstruction date' => ['2028-02-29 00:00:00', 2],
        ];
    }

    #[DataProvider('midnightBoundaries')]
    public function test_reminders_follow_local_date_without_shifting_ratification(string $utc, int $count): void
    {
        $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
        $curriculum = Curriculum::factory()->create(['status' => CurriculumStatus::PUBLISHED, 'ratified_at' => '2024-02-29']);
        User::factory()->create(['role' => UserRole::STUDY_PROGRAM_ADMIN, 'is_active' => true,
            'faculty_id' => $curriculum->studyProgram->faculty_id, 'study_program_id' => $curriculum->study_program_id]);

        $this->artisan('curriculum:send-monev-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', $count);
        $this->artisan('curriculum:send-monev-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', $count);
        $this->assertSame('2024-02-29', $curriculum->fresh()->ratified_at->toDateString());
        foreach (Notification::all() as $notification) {
            $this->assertSame($notification->type === 'monev_minor_due' ? '2026-02-28' : '2028-02-29', $notification->data['due_date']);
        }
    }

    public function test_notification_storage_and_display_use_local_time_without_rewriting_legacy_values(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 16:05:00', 'UTC'));
        $user = User::factory()->create(['role' => UserRole::UNIVERSITY_ADMIN, 'is_active' => true]);
        $notification = Notification::create(['user_id' => $user->id, 'type' => 'test', 'title' => 'Waktu lokal', 'message' => 'Pesan']);
        $this->assertSame('2026-11-19 00:05:00', $notification->fresh()->getRawOriginal('created_at'));
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()
            ->assertSee('19/11/2026 00:05')->assertSee('2026-11-19T00:05:00+08:00');

        $notification->forceFill(['created_at' => '2026-01-01 12:30:00'])->save();
        $this->get(route('notifications.index'))->assertOk()->assertSee('01/01/2026 12:30');
        $this->assertSame('2026-01-01 12:30:00', $notification->fresh()->getRawOriginal('created_at'));
    }
}
