<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\User;
use Database\Seeders\DashboardKurikulumSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string, string}> */
    public static function restrictedSeeders(): array
    {
        return [
            'default production' => [DatabaseSeeder::class, 'production'],
            'dashboard production' => [DashboardKurikulumSeeder::class, 'production'],
            'default staging' => [DatabaseSeeder::class, 'staging'],
            'dashboard staging' => [DashboardKurikulumSeeder::class, 'staging'],
        ];
    }

    #[DataProvider('restrictedSeeders')]
    public function test_demo_seeders_reject_non_development_environments_before_writing(string $seeder, string $environment): void
    {
        $this->app['env'] = $environment;
        try {
            $this->app->make($seeder)->run();
            $this->fail('Demo seeding must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local atau testing', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('faculties', 0);
        $this->assertDatabaseCount('curricula', 0);
    }

    public function test_demo_seeders_still_work_in_isolated_testing_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(5, User::query()->count());
        $this->assertSame(1, Faculty::query()->count());
    }

    public function test_production_error_responses_do_not_expose_exception_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/__release-test-error', function (): never {
            throw new RuntimeException('PRIVATE_SENTINEL SQLSTATE secret /srv/private/example.php');
        });
        foreach (['text/html', 'application/json'] as $accept) {
            $this->get('/__release-test-error', ['Accept' => $accept])
                ->assertStatus(500)
                ->assertDontSee('PRIVATE_SENTINEL')
                ->assertDontSee('SQLSTATE')
                ->assertDontSee('/srv/private/example.php');
        }
    }
}
