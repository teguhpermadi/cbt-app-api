<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\LearningPath;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LearningPath>
 */
final class LearningPathFactory extends Factory
{
    protected $model = LearningPath::class;

    public function definition(): array
    {
        return [
            'subject_id' => Subject::first()?->id ?? Subject::factory(),
            'classroom_id' => Classroom::first()?->id ?? Classroom::factory(),
            'user_id' => User::first()?->id ?? User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'order' => 0,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    public function sequenceOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => $order,
        ]);
    }
}
