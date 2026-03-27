<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LearningContentType;
use App\Models\LearningLesson;
use App\Models\LearningUnit;
use App\Models\QuestionBank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LearningLesson>
 */
final class LearningLessonFactory extends Factory
{
    protected $model = LearningLesson::class;

    public function definition(): array
    {
        return [
            'learning_unit_id' => LearningUnit::first()?->id ?? LearningUnit::factory(),
            'question_bank_id' => null,
            'title' => fake()->sentence(3),
            'content_type' => LearningContentType::READING->value,
            'content_data' => [
                'content' => fake()->paragraph(),
            ],
            'order' => 0,
            'xp_reward' => fake()->numberBetween(5, 50),
        ];
    }

    public function reading(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => LearningContentType::READING->value,
            'content_data' => ['content' => fake()->paragraph()],
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => LearningContentType::VIDEO->value,
            'content_data' => ['url' => fake()->url(), 'duration' => fake()->numberBetween(60, 3600)],
        ]);
    }

    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => LearningContentType::AUDIO->value,
            'content_data' => ['url' => fake()->url(), 'duration' => fake()->numberBetween(30, 1800)],
        ]);
    }

    public function webLink(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => LearningContentType::WEB_LINK->value,
            'content_data' => ['url' => fake()->url(), 'title' => fake()->sentence(2)],
        ]);
    }

    public function quiz(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => LearningContentType::QUIZ->value,
            'content_data' => ['question_count' => fake()->numberBetween(5, 20)],
        ]);
    }

    public function survey(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => LearningContentType::SURVEY->value,
            'content_data' => ['questions' => fake()->numberBetween(3, 10)],
        ]);
    }

    public function withQuestionBank(): static
    {
        return $this->state(fn (array $attributes) => [
            'question_bank_id' => QuestionBank::first()?->id ?? QuestionBank::factory(),
        ]);
    }

    public function sequenceOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => $order,
        ]);
    }
}
