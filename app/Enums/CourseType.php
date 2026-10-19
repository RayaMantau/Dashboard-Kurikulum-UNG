<?php

namespace App\Enums;

enum CourseType: string
{
    case REQUIRED = 'wajib';
    case ELECTIVE = 'pilihan';

    public function label(): string
    {
        return match ($this) {
            self::REQUIRED => 'Wajib',
            self::ELECTIVE => 'Pilihan',
        };
    }
}
