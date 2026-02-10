<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuestionBank>
 */
class QuestionBankFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'user_id' => User::factory(),
            'subject_id' => Subject::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (QuestionBank $questionBank) {
            $questionBank->questions()->attach(Question::factory()->count(10)->create());
        });
    }
}
