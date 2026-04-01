<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SequenceScoringLogicTest extends TestCase
{
    public function test_sequence_partial_scoring_perfect_match(): void
    {
        $correctOrder = ['A', 'B', 'C', 'D'];
        $studentOrder = ['A', 'B', 'C', 'D'];
        $maxScore = 10;

        $result = $this->calculateSequenceScore($correctOrder, $studentOrder, $maxScore);

        $this->assertEquals(10.0, $result['score']);
        $this->assertTrue($result['is_correct']);
    }

    public function test_sequence_partial_scoring_half_correct(): void
    {
        $correctOrder = ['A', 'B', 'C', 'D'];
        $studentOrder = ['A', 'B', 'X', 'Y'];
        $maxScore = 10;

        $result = $this->calculateSequenceScore($correctOrder, $studentOrder, $maxScore);

        $this->assertEquals(5.0, $result['score']);
        $this->assertFalse($result['is_correct']);
    }

    public function test_sequence_partial_scoring_two_of_four_correct(): void
    {
        $correctOrder = ['A', 'B', 'C', 'D'];
        $studentOrder = ['A', 'C', 'B', 'D'];
        $maxScore = 10;

        $result = $this->calculateSequenceScore($correctOrder, $studentOrder, $maxScore);

        $this->assertEquals(5.0, $result['score']);
        $this->assertFalse($result['is_correct']);
    }

    public function test_sequence_partial_scoring_none_correct(): void
    {
        $correctOrder = ['A', 'B', 'C', 'D'];
        $studentOrder = ['D', 'C', 'B', 'A'];
        $maxScore = 10;

        $result = $this->calculateSequenceScore($correctOrder, $studentOrder, $maxScore);

        $this->assertEquals(0.0, $result['score']);
        $this->assertFalse($result['is_correct']);
    }

    public function test_sequence_partial_scoring_three_of_four(): void
    {
        $correctOrder = ['A', 'B', 'C', 'D'];
        $studentOrder = ['A', 'B', 'C', 'X'];
        $maxScore = 10;

        $result = $this->calculateSequenceScore($correctOrder, $studentOrder, $maxScore);

        $this->assertEquals(7.5, $result['score']);
        $this->assertFalse($result['is_correct']);
    }

    public function test_sequence_partial_scoring_one_of_four(): void
    {
        $correctOrder = ['A', 'B', 'C', 'D'];
        $studentOrder = ['A', 'X', 'Y', 'Z'];
        $maxScore = 10;

        $result = $this->calculateSequenceScore($correctOrder, $studentOrder, $maxScore);

        $this->assertEquals(2.5, $result['score']);
        $this->assertFalse($result['is_correct']);
    }

    public function test_sequence_partial_scoring_empty_key(): void
    {
        $correctOrder = [];
        $studentOrder = ['A', 'B'];
        $maxScore = 10;

        $result = $this->calculateSequenceScore($correctOrder, $studentOrder, $maxScore);

        $this->assertEquals(0.0, $result['score']);
        $this->assertFalse($result['is_correct']);
    }

    public function test_multiple_selection_partial_scoring(): void
    {
        $correctKeys = ['A', 'B', 'C'];
        $selectedByStudent = ['A', 'B'];
        $maxScore = 10;

        $result = $this->calculateMultipleSelectionScore($correctKeys, $selectedByStudent, $maxScore);

        $this->assertEquals(6.7, $result['score']);
        $this->assertFalse($result['is_correct']);
    }

    public function test_multiple_selection_with_wrong_selection(): void
    {
        $correctKeys = ['A', 'B', 'C'];
        $selectedByStudent = ['A', 'B', 'D'];
        $maxScore = 10;

        $result = $this->calculateMultipleSelectionScore($correctKeys, $selectedByStudent, $maxScore);

        $this->assertEquals(3.3, $result['score']);
        $this->assertFalse($result['is_correct']);
    }

    private function calculateSequenceScore(array $correctOrder, array $studentOrder, float $maxScore): array
    {
        $totalItems = count($correctOrder);
        if ($totalItems === 0) {
            return ['score' => 0, 'is_correct' => false];
        }

        $correctPositionCount = 0;
        foreach ($correctOrder as $index => $value) {
            if (isset($studentOrder[$index]) && $studentOrder[$index] === $value) {
                $correctPositionCount++;
            }
        }

        $ratio = (float) $correctPositionCount / (float) $totalItems;
        $scoreEarned = round($ratio * $maxScore, 1);
        $isCorrect = ($ratio === 1.0);

        return [
            'score' => $scoreEarned,
            'is_correct' => $isCorrect,
        ];
    }

    private function calculateMultipleSelectionScore(array $correctKeys, array $selectedByStudent, float $maxScore): array
    {
        $totalCorrectOptions = count($correctKeys);

        $matches = array_intersect($correctKeys, $selectedByStudent);
        $countRight = count($matches);

        $countWrong = count($selectedByStudent) - $countRight;

        $netCorrect = $countRight - $countWrong;
        if ($netCorrect < 0) {
            $netCorrect = 0;
        }

        $ratio = $totalCorrectOptions > 0 ? $netCorrect / $totalCorrectOptions : 0;
        $scoreEarned = round($ratio * $maxScore, 1);
        $isCorrect = ($ratio === 1.0);

        return [
            'score' => $scoreEarned,
            'is_correct' => $isCorrect,
        ];
    }
}
