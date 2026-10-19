<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as BladeView;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-internal-dashboard', fn (User $user): bool => $user->is_active
            && UserRole::tryFrom((string) $user->getRawOriginal('role')) !== null);

        Gate::define('access-role', fn (User $user, string ...$roles): bool => Gate::forUser($user)->allows('access-internal-dashboard')
            && in_array($user->getRawOriginal('role'), $roles, true));

        View::composer('layouts.application', function (BladeView $view): void {
            $user = request()->user();
            $scopeLabel = match ($user->role) {
                UserRole::UNIVERSITY_ADMIN => 'Universitas Negeri Gorontalo',
                UserRole::FACULTY_ADMIN => $user->faculty?->name ?? 'Fakultas belum ditetapkan',
                UserRole::STUDY_PROGRAM_ADMIN, UserRole::HEAD_OF_STUDY_PROGRAM => $user->studyProgram?->name ?? 'Program studi belum ditetapkan',
            };

            $view->with([
                'shellUser' => $user,
                'scopeLabel' => $scopeLabel,
                'unreadNotificationCount' => $user->notifications()->whereNull('read_at')->count(),
            ]);
        });
    }
}
