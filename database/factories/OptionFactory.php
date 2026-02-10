<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Option>
 */
class OptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => \App\Models\Question::factory()->withoutOptions(),
            'option_key' => $this->faker->unique()->lexify('?'),
            'content' => $this->faker->sentence(),
            'order' => $this->faker->numberBetween(0, 10),
            'is_correct' => $this->faker->boolean(20),
            'metadata' => null,
        ];
    }
}
