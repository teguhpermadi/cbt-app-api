<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LearningPath;
use App\Models\LearningUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LearningUnit>
 */
final class LearningUnitFactory extends Factory
{
    protected $model = LearningUnit::class;

    public function definition(): array
    {
        return [
            'learning_path_id' => LearningPath::first()?->id ?? LearningPath::factory(),
            'title' => fake()->sentence(3),
            'order' => 0,
            'xp_reward' => fake()->numberBetween(10, 100),
        ];
    }

    public function sequenceOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => $order,
        ]);
    }
}
