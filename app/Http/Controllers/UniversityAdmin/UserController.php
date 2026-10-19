<?php

namespace App\Http\Controllers\UniversityAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserRequest;
use App\Http\Requests\UserIndexRequest;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(UserIndexRequest $request): View
    {
        $filters = $request->validated();
        $query = User::query()->select(['id', 'name', 'email', 'role', 'faculty_id', 'study_program_id', 'is_active', 'last_login_at'])
            ->whereIn('role', array_column(UserRole::cases(), 'value'))
            ->with(['faculty:id,name', 'studyProgram:id,name']);

        if (filled($filters['search'] ?? null)) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn (Builder $query): Builder => $query->whereLike('name', $search)->orWhereLike('email', $search));
        }
        foreach (['role', 'faculty_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === '1');
        }

        return view('university-admin.users.index', [
            'users' => $query->orderBy('name')->orderBy('id')->paginate(15)->withQueryString(),
            'faculties' => Faculty::query()->orderBy('name')->get(['id', 'name']),
            'roles' => UserRole::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return $this->form(new User(['is_active' => true]), 'create');
    }

    public function store(SaveUserRequest $request, UserService $service): RedirectResponse
    {
        $user = $service->save($request->user(), $request->userData());

        return redirect()->route('university-admin.users.edit', $user)->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        return $this->form($user, 'edit');
    }

    public function update(SaveUserRequest $request, User $user, UserService $service): RedirectResponse
    {
        $service->save($request->user(), $request->userData(), $user);

        return redirect()->route('university-admin.users.edit', $user)->with('success', 'Data pengguna berhasil diperbarui.');
    }

    private function form(User $user, string $view): View
    {
        return view('university-admin.users.'.$view, [
            'managedUser' => $user,
            'roles' => UserRole::cases(),
            'faculties' => Faculty::query()->where('is_active', true)
                ->orWhere('id', $user->role?->scopeField() === 'faculty_id' ? $user->faculty_id : null)
                ->orderBy('name')->get(['id', 'name', 'is_active']),
            'studyPrograms' => StudyProgram::query()->where(fn (Builder $query): Builder => $query->where('is_active', true)
                ->whereHas('faculty', fn (Builder $query): Builder => $query->where('is_active', true)))
                ->orWhere('id', $user->role?->scopeField() === 'study_program_id' ? $user->study_program_id : null)
                ->with('faculty:id,name,is_active')->orderBy('name')->get(['id', 'faculty_id', 'name', 'is_active']),
        ]);
    }
}
