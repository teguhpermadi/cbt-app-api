<?php

namespace App\Enums;

enum ExamTimerTypeEnum : string
{
    case Strict = 'strict';
    case Flexible = 'flexible';

    public function label(): string
    {
        return match ($this) {
            self::Strict => 'Strict',
            self::Flexible => 'Flexible',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Strict => 'Timer berjalan terus meskipun siswa keluar dari ujian',
            self::Flexible => 'Timer berhenti saat siswa keluar dari ujian',
        };
    }
}
