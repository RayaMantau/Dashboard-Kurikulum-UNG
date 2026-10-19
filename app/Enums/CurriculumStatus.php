<?php

namespace App\Enums;

enum CurriculumStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case REVISION = 'revision';
    case APPROVED = 'approved';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Menunggu Review',
            self::REVISION => 'Perlu Revisi',
            self::APPROVED => 'Disetujui',
            self::PUBLISHED => 'Dipublikasikan',
            self::ARCHIVED => 'Diarsipkan',
        };
    }
}
