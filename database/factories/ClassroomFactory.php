<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'classroom ' . fake()->randomElement([1, 2, 3, 4, 5, 6]),
            'code' => fake()->word(),
            'level' => fake()->randomElement([1, 2, 3, 4, 5, 6]),
            'academic_year_id' => AcademicYear::factory(),
        ];
    }
}
