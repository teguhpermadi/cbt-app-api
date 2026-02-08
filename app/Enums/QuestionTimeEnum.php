<?php

namespace App\Enums;

enum QuestionTimeEnum: int
{
    case FIVE_SECONDS = 5000;
    case TEN_SECONDS = 10000;
    case THIRTY_SECONDS = 30000;
    case FORTY_FIVE_SECONDS = 45000;
    case ONE_MINUTE = 60000;
    case ONE_AND_HALF_MINUTES = 90000;
    case TWO_MINUTES = 120000;
    case THREE_MINUTES = 180000;
    case FIVE_MINUTES = 300000;
    case TEN_MINUTES = 600000;
    case FIFTEEN_MINUTES = 900000;

    public function description(): string
    {
        return match($this) {
            self::FIVE_SECONDS => '5 detik',
            self::TEN_SECONDS => '10 detik',
            self::THIRTY_SECONDS => '30 detik',
            self::FORTY_FIVE_SECONDS => '45 detik',
            self::ONE_MINUTE => '1 menit',
            self::ONE_AND_HALF_MINUTES => '1,5 menit',
            self::TWO_MINUTES => '2 menit',
            self::THREE_MINUTES => '3 menit',
            self::FIVE_MINUTES => '5 menit',
            self::TEN_MINUTES => '10 menit',
            self::FIFTEEN_MINUTES => '15 menit',
        };
    }
}