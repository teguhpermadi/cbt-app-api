<?php

namespace Database\Factories;

use App\Enums\ExamTimerTypeEnum;
use App\Enums\ExamTypeEnum;
use App\Models\AcademicYear;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'subject_id' => Subject::factory(),
            'user_id' => User::factory(),
            'question_bank_id' => QuestionBank::factory(),
            'title' => $this->faker->sentence(3),
            'type' => $this->faker->randomElement(ExamTypeEnum::cases()),
            'duration' => $this->faker->numberBetween(30, 120),
            'token' => $this->faker->unique()->regexify('[A-Z0-9]{6}'),
            'is_token_visible' => $this->faker->boolean(),
            'is_published' => $this->faker->boolean(),
            'is_randomized_question' => $this->faker->boolean(),
            'is_randomized_answer' => $this->faker->boolean(),
            'is_show_result' => $this->faker->boolean(),
            'is_visible_hint' => $this->faker->boolean(),
            'max_attempts' => $this->faker->numberBetween(1, 5),
            'timer_type' => $this->faker->randomElement(ExamTimerTypeEnum::cases()),
            'passing_score' => $this->faker->numberBetween(60, 90),
            'start_time' => $this->faker->dateTimeBetween('-1 week', '+1 week'),
            'end_time' => $this->faker->dateTimeBetween('+1 week', '+2 weeks'),
        ];
    }
}
