<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamSession>
 */
class ExamSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'user_id' => User::factory(),
            'attempt_number' => fake()->numberBetween(1, 10),
            'total_score' => fake()->numberBetween(0, 100),
            'total_max_score' => fake()->numberBetween(0, 100),
            'is_finished' => fake()->boolean(),
            'is_corrected' => fake()->boolean(),
            'start_time' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'finish_time' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'duration_taken' => fake()->numberBetween(0, 100),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
