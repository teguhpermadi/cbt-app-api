<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Classroom;
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
        $faker = \Faker\Factory::create();
        $faker->addProvider(new \Smknstd\FakerPicsumImages\FakerPicsumImagesProvider($faker));

        return [
            'name' => fake()->name(),
            'code' => fake()->word(),
            'description' => fake()->text(),
            'image_url' => $faker->imageUrl(width: 400, height: 400),
            'logo_url' => $faker->imageUrl(width: 400, height: 400),
            'user_id' => User::factory(),
            'color' => fake()->colorName(),
            'class_name' => fake()->word(),
            'academic_year_id' => AcademicYear::first()?->id ?? AcademicYear::factory(),
            'classroom_id' => Classroom::first()?->id ?? Classroom::factory(),
        ];
    }
}
