<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuestionSuggestion>
 */
class QuestionSuggestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => \App\Models\Question::factory(),
            'user_id' => \App\Models\User::factory(),
            'data' => [
                'question' => $this->faker->sentence,
                // 'options' => [
                //     $this->faker->word,
                //     $this->faker->word,
                //     $this->faker->word,
                //     $this->faker->word,
                // ],
                // 'correct_answer' => $this->faker->numberBetween(0, 3),
            ],
            'description' => $this->faker->sentence,
            'state' => \App\Enums\QuestionSuggestionStateEnum::PENDING,
        ];
    }
}
