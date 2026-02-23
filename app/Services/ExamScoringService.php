<?php

namespace App\Services;

use App\Models\ExamResultDetail;
use App\Enums\QuestionTypeEnum;
use Illuminate\Support\Facades\Log;

class ExamScoringService
{
    public function calculateDetailScore(ExamResultDetail $detail): array
    {
        $examQuestion = $detail->examQuestion;
        $maxScore = $examQuestion->score_value ?? 0;
        $keyAnswer = $examQuestion->key_answer;
        $studentAnswer = $detail->student_answer;

        // Decode student answer if it's a JSON string (Double Encoding Issue)
        if (is_string($studentAnswer)) {
            $decoded = json_decode($studentAnswer, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $studentAnswer = $decoded;
            }
        }

        if ($studentAnswer === null) {
            return ['score' => 0, 'is_correct' => false];
        }

        switch ($examQuestion->question_type) {
            case QuestionTypeEnum::MULTIPLE_CHOICE:
                return $this->scoreMultipleChoice($examQuestion, $studentAnswer, $keyAnswer, $maxScore);

            case QuestionTypeEnum::TRUE_FALSE:
                return $this->scoreTrueFalse($examQuestion, $studentAnswer, $keyAnswer, $maxScore);

            case QuestionTypeEnum::MULTIPLE_SELECTION:
                return $this->scoreMultipleSelection($examQuestion, $studentAnswer, $keyAnswer, $maxScore);

            case QuestionTypeEnum::MATCHING:
                return $this->scoreMatching($examQuestion, $studentAnswer, $keyAnswer, $maxScore);

            case QuestionTypeEnum::SEQUENCE:
                return $this->scoreSequence($examQuestion, $studentAnswer, $keyAnswer, $maxScore);

            case QuestionTypeEnum::MATH_INPUT:
                return $this->scoreMathInput($examQuestion, $studentAnswer, $keyAnswer, $maxScore);

            case QuestionTypeEnum::SHORT_ANSWER:
                return $this->scoreShortAnswer($examQuestion, $studentAnswer, $keyAnswer, $maxScore);

            case QuestionTypeEnum::ARABIC_RESPONSE:
                return $this->scoreArabicResponse($examQuestion, $studentAnswer, $keyAnswer, $maxScore);

            case QuestionTypeEnum::JAVANESE_RESPONSE:
                return $this->scoreJavaneseResponse($examQuestion, $studentAnswer, $keyAnswer, $maxScore);

            case QuestionTypeEnum::ESSAY:
                return ['score' => $detail->score_earned ?? 0, 'is_correct' => $detail->is_correct]; // Maintain existing

            case QuestionTypeEnum::ARRANGE_WORDS:
                return $this->scoreArrangeWords($examQuestion, $studentAnswer, $keyAnswer, $maxScore);

            default:
                return ['score' => 0, 'is_correct' => false];
        }
    }

    private function scoreArrangeWords($question, $studentAnswer, $keyAnswer, $maxScore): array
    {
        $correctWords = $keyAnswer['words'] ?? [];
        $studentWords = is_array($studentAnswer) ? $studentAnswer : [];

        // Strict comparison of the sequence
        $isCorrect = ($studentWords === $correctWords);

        return [
            'score' => $isCorrect ? $maxScore : 0,
            'is_correct' => $isCorrect
        ];
    }

    private function scoreMultipleChoice($question, $studentAnswer, $keyAnswer, $maxScore): array
    {
        $keyVal = $keyAnswer['answer'] ?? null;
        $isCorrect = ($studentAnswer == $keyVal);

        return [
            'score' => $isCorrect ? $maxScore : 0,
            'is_correct' => $isCorrect
        ];
    }

    private function scoreTrueFalse($question, $studentAnswer, $keyAnswer, $maxScore): array
    {
        $keyVal = $keyAnswer['answer'] ?? null;
        $isCorrect = ($studentAnswer == $keyVal);

        return [
            'score' => $isCorrect ? $maxScore : 0,
            'is_correct' => $isCorrect
        ];
    }

    private function scoreMultipleSelection($question, $studentAnswer, $keyAnswer, $maxScore): array
    {
        $correctKeys = $keyAnswer['answers'] ?? [];
        $totalCorrectOptions = count($correctKeys);
        $selectedByStudent = is_array($studentAnswer) ? $studentAnswer : [];

        // Calculate Intersect
        $matches = array_intersect($correctKeys, $selectedByStudent);
        $countRight = count($matches);

        // Calculate Wrong Selections
        $countWrong = count($selectedByStudent) - $countRight;

        // Net Correct Logic
        $netCorrect = $countRight - $countWrong;
        if ($netCorrect < 0) $netCorrect = 0;

        $ratio = $totalCorrectOptions > 0 ? $netCorrect / $totalCorrectOptions : 0;
        $scoreEarned = round($ratio * $maxScore, 1);
        $isCorrect = ($ratio == 1.0);

        return [
            'score' => $scoreEarned,
            'is_correct' => $isCorrect
        ];
    }

    private function scoreMatching($question, $studentAnswer, $keyAnswer, $maxScore): array
    {
        $correctPairs = $keyAnswer['pairs'] ?? [];
        $totalPairs = count($correctPairs);
        $studentPairs = is_array($studentAnswer) ? $studentAnswer : [];

        $correctMatchCount = 0;

        foreach ($correctPairs as $key => $val) {
            $l = null;
            $r = null;

            if (is_array($val) && isset($val['left'])) {
                $l = $val['left'];
                $r = $val['right'] ?? null;
            } else {
                $l = $key;
                $r = $val;
            }

            if ($l && isset($studentPairs[$l]) && $studentPairs[$l] == $r) {
                $correctMatchCount++;
            }
        }

        $ratio = $totalPairs > 0 ? $correctMatchCount / $totalPairs : 0;
        $scoreEarned = round($ratio * $maxScore, 1);
        $isCorrect = ($ratio == 1.0);

        return [
            'score' => $scoreEarned,
            'is_correct' => $isCorrect
        ];
    }

    private function scoreSequence($question, $studentAnswer, $keyAnswer, $maxScore): array
    {
        $correctOrder = $keyAnswer['order'] ?? [];
        $studentOrder = is_array($studentAnswer) ? $studentAnswer : [];

        $isCorrect = ($studentOrder == $correctOrder);

        return [
            'score' => $isCorrect ? $maxScore : 0,
            'is_correct' => $isCorrect
        ];
    }

    private function scoreMathInput($question, $studentAnswer, $keyAnswer, $maxScore): array
    {
        $correctVal = (float)($keyAnswer['answer'] ?? 0);
        $tolerance = (float)($keyAnswer['tolerance'] ?? 0);
        $studentVal = (float)$studentAnswer;

        $isCorrect = abs($studentVal - $correctVal) <= $tolerance;

        return [
            'score' => $isCorrect ? $maxScore : 0,
            'is_correct' => $isCorrect
        ];
    }

    private function scoreShortAnswer($question, $studentAnswer, $keyAnswer, $maxScore): array
    {
        $correctAnswers = $keyAnswer['answers'] ?? [];
        // Support legacy single answer format just in case
        if (isset($keyAnswer['answer']) && !in_array($keyAnswer['answer'], $correctAnswers)) {
            $correctAnswers[] = $keyAnswer['answer'];
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

    private function scoreArabicResponse($question, $studentAnswer, $keyAnswer, $maxScore): array
    {
        // For now, same as short answer but could include normalization later
        return $this->scoreShortAnswer($question, $studentAnswer, $keyAnswer, $maxScore);
    }

    private function scoreJavaneseResponse($question, $studentAnswer, $keyAnswer, $maxScore): array
    {
        // For now, same as short answer but could include normalization later
        return $this->scoreShortAnswer($question, $studentAnswer, $keyAnswer, $maxScore);
    }
}
