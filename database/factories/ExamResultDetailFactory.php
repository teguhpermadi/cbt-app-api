<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamResultDetail>
 */
class ExamResultDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_session_id' => \App\Models\ExamSession::factory(),
            'exam_question_id' => \App\Models\ExamQuestion::factory(),
            'student_answer' => ['answer' => fake()->sentence()],
            'is_correct' => fake()->boolean(),
            'score_earned' => fake()->randomFloat(2, 0, 10),
            'question_number' => fake()->numberBetween(1, 50),
            'correction_notes' => fake()->sentence(),
            'answered_at' => now(),
        ];
    }
}
