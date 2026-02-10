<?php

namespace App\Enums;

enum ExamTypeEnum: string
{
    case Daily = 'daily';
    case Midterm = 'midterm';
    case Final = 'final';
    case Tryout = 'tryout';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Ulangan Harian',
            self::Midterm => 'Ulangan Tengah Semester',
            self::Final => 'Ulangan Akhir Semester',
            self::Tryout => 'Tryout',
        };
    }
}
