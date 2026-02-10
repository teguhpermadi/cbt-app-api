<?php

namespace App\Enums;

enum ExamResultTypeEnum: string
{
    const OFFICIAL = 'official';
    const BEST_ATTEMPT = 'best_attempt';
    const LATEST_ATTEMPT = 'latest_attempt';

    public function label(): string
    {
        return match ($this) {
            self::OFFICIAL => 'Pengujian Pertama',
            self::BEST_ATTEMPT => 'Pengujian Terbaik',
            self::LATEST_ATTEMPT => 'Pengujian Terakhir',
        };
    }
}
