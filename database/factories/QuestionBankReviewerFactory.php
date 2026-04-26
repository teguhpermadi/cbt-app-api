<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuestionBank;
use App\Models\QuestionBankReviewer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class QuestionBankReviewerFactory extends Factory
{
    protected $model = QuestionBankReviewer::class;

    public function definition(): array
    {
        return [
            'question_bank_id' => QuestionBank::factory(),
            'user_id' => User::factory(),
            'state' => $this->faker->randomElement([
                'pending',
                'approved',
                'rejected',
            ]),
            'rating' => $this->faker->optional()->numberBetween(1, 5),
            'notes' => $this->faker->optional()->text(),
            'suggested_questions_count' => $this->faker->numberBetween(0, 20),
        ];
    }
}
