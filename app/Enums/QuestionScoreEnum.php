<?php

namespace App\Enums;

enum QuestionScoreEnum: int
{
    case ONE = 1;
    case TWO = 2;
    case THREE = 3;
    case FOUR = 4;
    case FIVE = 5;

    public function description(): string
    {
        return match($this) {
            self::ONE => '1 poin',
            self::TWO => '2 poin',
            self::THREE => '3 poin', 
            self::FOUR => '4 poin',
            self::FIVE => '5 poin',
        };
    }
}