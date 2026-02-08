<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassroomUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassroomUser>
 */
class ClassroomUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => fn() => \App\Models\Classroom::inRandomOrder()->first()?->id ?? \App\Models\Classroom::factory(),
            'user_id' => fn() => \App\Models\User::where('user_type', \App\Enums\UserTypeEnum::STUDENT)->inRandomOrder()->first()?->id ?? \App\Models\User::factory()->student(),
            'academic_year_id' => fn() => \App\Models\AcademicYear::inRandomOrder()->first()?->id ?? \App\Models\AcademicYear::factory(),
        ];
    }
}
