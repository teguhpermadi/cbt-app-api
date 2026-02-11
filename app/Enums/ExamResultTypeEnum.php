<?php

namespace App\Enums;

enum ExamResultTypeEnum: string
{
    case OFFICIAL = 'official';
    case BEST_ATTEMPT = 'best_attempt';
    case LATEST_ATTEMPT = 'latest_attempt';

    public function label(): string
    {
        return match ($this) {
            self::OFFICIAL => 'Pengujian Pertama',
            self::BEST_ATTEMPT => 'Pengujian Terbaik',
            self::LATEST_ATTEMPT => 'Pengujian Terakhir',
        };
    }
}
