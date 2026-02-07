<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'code' => fake()->word(),
            'description' => fake()->text(),
            'image_url' => fake()->imageUrl(),
            'logo_url' => fake()->imageUrl(),
            'user_id' => User::factory(),
            'color' => fake()->colorName(),
            'class_name' => fake()->word(),
        ];
    }
}
