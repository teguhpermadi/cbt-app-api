<?php

declare(strict_types=1);

namespace App\Enums;

enum LearningContentType: string
{
    case READING = 'reading';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case WEB_LINK = 'web_link';
    case QUIZ = 'quiz';
    case SURVEY = 'survey';

    public function label(): string
    {
        return match ($this) {
            self::READING => 'Reading',
            self::VIDEO => 'Video',
            self::AUDIO => 'Audio',
            self::WEB_LINK => 'Web Link',
            self::QUIZ => 'Quiz',
            self::SURVEY => 'Survey',
        };
    }
}
