<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamResult>
 */
class ExamResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => \App\Models\Exam::factory(),
            'user_id' => \App\Models\User::factory(),
            'exam_session_id' => \App\Models\ExamSession::factory(),
            'total_score' => $this->faker->numberBetween(0, 100),
            'score_percent' => $this->faker->randomFloat(2, 0, 100),
            'is_passed' => $this->faker->boolean,
            'result_type' => \App\Enums\ExamResultTypeEnum::OFFICIAL,
        ];
    }
}
