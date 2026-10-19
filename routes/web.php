<?php

use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\CurriculumCourseController;
use App\Http\Controllers\Admin\CurriculumSubmissionController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\SemesterController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\CurriculumHistoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Kaprodi\CurriculumPublicationController;
use App\Http\Controllers\Kaprodi\CurriculumReviewController;
use App\Http\Controllers\PublicCurriculumController;
use App\Http\Controllers\PublicFacultyController;
use App\Http\Controllers\PublicSearchController;
use App\Http\Controllers\PublicStudyProgramController;
use App\Http\Controllers\UniversityAdmin\FacultyController;
use App\Http\Controllers\UniversityAdmin\StudyProgramController;
use App\Http\Controllers\UniversityAdmin\UserController;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', PublicSearchController::class)->name('search');
Route::get('/fakultas', [PublicFacultyController::class, 'index'])->name('faculties.index');
Route::get('/fakultas/{faculty:slug}', [PublicFacultyController::class, 'show'])->name('faculties.show');
Route::get('/prodi', [PublicStudyProgramController::class, 'index'])->name('study-programs.index');
Route::get('/prodi/{studyProgram:slug}', [PublicStudyProgramController::class, 'show'])->name('study-programs.show');
Route::get('/prodi/{studyProgram:slug}/kurikulum', [PublicCurriculumController::class, 'index'])->name('study-programs.curricula.index');
Route::get('/prodi/{studyProgram:slug}/kurikulum/{curriculum}', [PublicCurriculumController::class, 'show'])->name('study-programs.curricula.show');
Route::get('/prodi/{studyProgram:slug}/kurikulum/{curriculum}/semester/{semester}', [PublicCurriculumController::class, 'semester'])->name('study-programs.curricula.semesters.show');
Route::get('/prodi/{studyProgram:slug}/kurikulum/{curriculum}/dokumen/{document}/download', [PublicCurriculumController::class, 'download'])->name('study-programs.curricula.documents.download');

Route::middleware('guest:web')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth:web')->group(function (): void {
    Route::get('/admin', DashboardController::class)->middleware('can:access-internal-dashboard')->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth:web', 'can:access-internal-dashboard'])->group(function (): void {
    Route::get('/internal/kurikulum/{curriculum}/riwayat', CurriculumHistoryController::class)->can('view', 'curriculum')->name('internal.curricula.history');
    Route::prefix('university-admin')->name('university-admin.')->middleware('can:access-role,"university_admin"')->group(function (): void {
        Route::get('/pengguna', [UserController::class, 'index'])->can('viewAny', User::class)->name('users.index');
        Route::get('/pengguna/tambah', [UserController::class, 'create'])->can('create', User::class)->name('users.create');
        Route::post('/pengguna', [UserController::class, 'store'])->can('create', User::class)->name('users.store');
        Route::get('/pengguna/{user}/edit', [UserController::class, 'edit'])->can('update', 'user')->name('users.edit');
        Route::match(['put', 'patch'], '/pengguna/{user}', [UserController::class, 'update'])->can('update', 'user')->name('users.update');
        Route::get('/program-studi', [StudyProgramController::class, 'index'])->can('viewAny', StudyProgram::class)->name('study-programs.index');
        Route::get('/program-studi/tambah', [StudyProgramController::class, 'create'])->can('create', StudyProgram::class)->name('study-programs.create');
        Route::post('/program-studi', [StudyProgramController::class, 'store'])->can('create', StudyProgram::class)->name('study-programs.store');
        Route::get('/program-studi/{studyProgram}/edit', [StudyProgramController::class, 'edit'])->can('update', 'studyProgram')->name('study-programs.edit');
        Route::match(['put', 'patch'], '/program-studi/{studyProgram}', [StudyProgramController::class, 'update'])->can('update', 'studyProgram')->name('study-programs.update');
        Route::get('/fakultas', [FacultyController::class, 'index'])->can('viewAny', Faculty::class)->name('faculties.index');
        Route::get('/fakultas/tambah', [FacultyController::class, 'create'])->can('create', Faculty::class)->name('faculties.create');
        Route::post('/fakultas', [FacultyController::class, 'store'])->can('create', Faculty::class)->name('faculties.store');
        Route::get('/fakultas/{faculty}/edit', [FacultyController::class, 'edit'])->can('update', 'faculty')->name('faculties.edit');
        Route::match(['put', 'patch'], '/fakultas/{faculty}', [FacultyController::class, 'update'])->can('update', 'faculty')->name('faculties.update');
        Route::view('/', 'admin.authorization-placeholder', ['title' => 'Administrasi Universitas'])->name('dashboard');
        Route::get('/kurikulum/{curriculum}', function (Curriculum $curriculum): View {
            return view('admin.authorization-placeholder', ['title' => 'Monitoring Kurikulum', 'resourceName' => $curriculum->name]);
        })->can('view', 'curriculum')->name('curricula.show');
    });

    Route::prefix('faculty-admin')->name('faculty-admin.')->middleware('can:access-role,"faculty_admin"')->group(function (): void {
        Route::view('/', 'admin.authorization-placeholder', ['title' => 'Monitoring Fakultas'])->name('dashboard');
        Route::get('/program-studi/{studyProgram}', function (StudyProgram $studyProgram): View {
            return view('admin.authorization-placeholder', ['title' => 'Monitoring Program Studi', 'resourceName' => $studyProgram->name]);
        })->can('view', 'studyProgram')->name('study-programs.show');
        Route::get('/kurikulum/{curriculum}', function (Curriculum $curriculum): View {
            return view('admin.authorization-placeholder', ['title' => 'Monitoring Kurikulum', 'resourceName' => $curriculum->name]);
        })->can('view', 'curriculum')->name('curricula.show');
    });

    Route::prefix('admin')->name('admin.')->middleware('can:access-role,"study_program_admin"')->group(function (): void {
        Route::middleware('can:viewAny,App\Models\Course')->group(function (): void {
            Route::get('/mata-kuliah', [CourseController::class, 'index'])->name('courses.index');
            Route::get('/mata-kuliah/tambah', [CourseController::class, 'create'])->can('create', Course::class)->name('courses.create');
            Route::post('/mata-kuliah', [CourseController::class, 'store'])->can('create', Course::class)->name('courses.store');
            Route::get('/mata-kuliah/{course}/edit', [CourseController::class, 'edit'])->can('update', 'course')->name('courses.edit');
            Route::match(['put', 'patch'], '/mata-kuliah/{course}', [CourseController::class, 'update'])->can('update', 'course')->name('courses.update');
        });
        Route::get('/kurikulum', [CurriculumController::class, 'index'])->can('viewAny', Curriculum::class)->name('curricula.index');
        Route::get('/kurikulum/{curriculum}/submit', [CurriculumSubmissionController::class, 'confirm'])->can('submit', 'curriculum')->name('curricula.submit.confirm');
        Route::post('/kurikulum/{curriculum}/submit', [CurriculumSubmissionController::class, 'store'])->can('submit', 'curriculum')->name('curricula.submit.store');
        Route::prefix('kurikulum/{curriculum}/dokumen')->name('curricula.documents.')->group(function (): void {
            Route::get('/', [DocumentController::class, 'index'])->name('index');
            Route::get('/tambah', [DocumentController::class, 'create'])->name('create');
            Route::post('/', [DocumentController::class, 'store'])->name('store');
            Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
            Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('kurikulum/{curriculum}/mata-kuliah')->name('curricula.courses.')->group(function (): void {
            Route::get('/', [CurriculumCourseController::class, 'index'])->name('index');
            Route::get('/tambah', [CurriculumCourseController::class, 'create'])->name('create');
            Route::post('/', [CurriculumCourseController::class, 'store'])->name('store');
            Route::get('/{curriculumCourse}/edit', [CurriculumCourseController::class, 'edit'])->name('edit');
            Route::match(['put', 'patch'], '/{curriculumCourse}', [CurriculumCourseController::class, 'update'])->name('update');
            Route::delete('/{curriculumCourse}', [CurriculumCourseController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('kurikulum/{curriculum}/semester')->name('curricula.semesters.')->group(function (): void {
            Route::get('/', [SemesterController::class, 'index'])->name('index');
            Route::get('/tambah', [SemesterController::class, 'create'])->name('create');
            Route::post('/', [SemesterController::class, 'store'])->name('store');
            Route::get('/{semester}/edit', [SemesterController::class, 'edit'])->name('edit');
            Route::match(['put', 'patch'], '/{semester}', [SemesterController::class, 'update'])->name('update');
            Route::delete('/{semester}', [SemesterController::class, 'destroy'])->name('destroy');
        });
        Route::get('/kurikulum/tambah', [CurriculumController::class, 'create'])->can('create', Curriculum::class)->name('curricula.create');
        Route::post('/kurikulum', [CurriculumController::class, 'store'])->can('create', Curriculum::class)->name('curricula.store');
        Route::match(['put', 'patch'], '/kurikulum/{curriculum}', [CurriculumController::class, 'update'])->can('update', 'curriculum')->name('curricula.update');
        Route::get('/kurikulum/{curriculum}', function (Curriculum $curriculum): View {
            return view('admin.authorization-placeholder', ['title' => 'Kurikulum Prodi', 'resourceName' => $curriculum->name]);
        })->can('view', 'curriculum')->name('curricula.show');
        Route::get('/kurikulum/{curriculum}/edit', [CurriculumController::class, 'edit'])->can('update', 'curriculum')->name('curricula.edit');
    });

    Route::prefix('kaprodi')->name('kaprodi.')->middleware('can:access-role,"head_of_study_program"')->group(function (): void {
        Route::view('/', 'admin.authorization-placeholder', ['title' => 'Review Kaprodi'])->name('dashboard');
        Route::get('/pengajuan', [CurriculumReviewController::class, 'index'])->name('submissions.index');
        Route::get('/pengajuan/disetujui', [CurriculumPublicationController::class, 'index'])->name('submissions.approved');
        Route::get('/pengajuan/{curriculum}/publish', [CurriculumPublicationController::class, 'confirm'])->can('publish', 'curriculum')->name('submissions.publish.confirm');
        Route::post('/pengajuan/{curriculum}/publish', [CurriculumPublicationController::class, 'store'])->can('publish', 'curriculum')->name('submissions.publish.store');
        Route::get('/pengajuan/{curriculum}', [CurriculumReviewController::class, 'show'])->can('review', 'curriculum')->name('submissions.show');
        Route::post('/pengajuan/{curriculum}/approve', [CurriculumReviewController::class, 'approve'])->can('review', 'curriculum')->name('submissions.approve');
        Route::post('/pengajuan/{curriculum}/revision', [CurriculumReviewController::class, 'revision'])->can('review', 'curriculum')->name('submissions.revision');
        Route::get('/pengajuan/{curriculum}/dokumen/{document}/download', [CurriculumReviewController::class, 'download'])->can('review', 'curriculum')->name('submissions.documents.download');
    });
});
