<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumStatus;
use App\Models\Curriculum;
use App\Models\StudyProgram;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumMonitoringService
{
    /**
     * @param  Builder<StudyProgram>  $programs  Caller supplies public or authorized internal scope.
     * @return Builder<StudyProgram>
     */
    public function withCurrentCurriculum(Builder $programs): Builder
    {
        return $programs->with(['faculty:id,name,is_active', 'curricula' => fn (HasMany $query) => $query
            ->select(['id', 'study_program_id', 'name', 'year', 'ratified_at', 'external_document_url'])
            ->where('status', CurriculumStatus::PUBLISHED)
            ->orderByDesc('published_at')->orderByDesc('id')->limit(1)->withCount('documents')]);
    }

    /** @return array{state: string, label: string, minor: ?CarbonImmutable, reconstruction: ?CarbonImmutable} */
    public function schedule(?Curriculum $curriculum): array
    {
        if ($curriculum?->ratified_at === null) {
            return ['state' => 'unknown', 'label' => 'Belum ada tanggal pengesahan', 'minor' => null, 'reconstruction' => null];
        }

        $minor = $curriculum->ratified_at->addYearsNoOverflow(2);
        $reconstruction = $curriculum->ratified_at->addYearsNoOverflow(4);
        $today = CarbonImmutable::today(config('app.timezone'));
        [$state, $label] = match (true) {
            $today->greaterThanOrEqualTo($reconstruction) => ['reconstruction', 'Rekonstruksi Kurikulum'],
            $today->greaterThanOrEqualTo($minor) => ['minor', 'Monev Minor'],
            default => ['upcoming', 'Belum memasuki periode Monev Minor'],
        };

        return compact('state', 'label', 'minor', 'reconstruction');
    }
}
