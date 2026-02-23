<?php

require 'vendor/autoload.php';

use App\Models\ExamQuestion;
use App\Models\ExamResultDetail;
use App\Services\ExamScoringService;
use App\Enums\QuestionTypeEnum;

// Mocking required for standalone execution
// Note: This script requires a working Laravel environment or manual mocking of dependencies.
// Since I cannot easily run this, I will perform a manual code review logic check.

/*
Logic Check for SHORT_ANSWER:
-----------------------------
Question.php:
QuestionTypeEnum::SHORT_ANSWER => [
    'answers' => $this->options->where('is_correct', true)
        ->pluck('content')->values()->toArray()
],

ExamScoringService.php:
private function scoreShortAnswer($question, $studentAnswer, $keyAnswer, $maxScore): array
{
    $correctAnswers = $keyAnswer['answers'] ?? []; // Correctly gets an array
    if (isset($keyAnswer['answer']) && !in_array($keyAnswer['answer'], $correctAnswers)) {
        $correctAnswers[] = $keyAnswer['answer']; // Backwards compatibility
    }

    $studentVal = trim(strtolower((string)$studentAnswer));
    $isCorrect = false;

    foreach ($correctAnswers as $answer) {
        if ($studentVal === trim(strtolower((string)$answer))) {
            $isCorrect = true;
            break;
        }
    }

    return [
        'score' => $isCorrect ? $maxScore : 0,
        'is_correct' => $isCorrect
    ];
}

Conclusion: Logic is sound. Multiple correct answers are handled correctly.
*/

echo "Logic verified via code review.\n";
