<?php

namespace App\Enums;

enum UserRole: string
{
    case UNIVERSITY_ADMIN = 'university_admin';
    case FACULTY_ADMIN = 'faculty_admin';
    case STUDY_PROGRAM_ADMIN = 'study_program_admin';
    case HEAD_OF_STUDY_PROGRAM = 'head_of_study_program';

    public function scopeField(): ?string
    {
        return match ($this) {
            self::UNIVERSITY_ADMIN => null,
            self::FACULTY_ADMIN => 'faculty_id',
            self::STUDY_PROGRAM_ADMIN, self::HEAD_OF_STUDY_PROGRAM => 'study_program_id',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::UNIVERSITY_ADMIN => 'Admin Universitas',
            self::FACULTY_ADMIN => 'Admin Fakultas',
            self::STUDY_PROGRAM_ADMIN => 'Admin Program Studi',
            self::HEAD_OF_STUDY_PROGRAM => 'Ketua Program Studi',
        };
    }
}
