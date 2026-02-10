<?php

namespace Database\Factories;

use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamQuestion>
 */
class ExamQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $exam = Exam::factory()->create();
        $question = \App\Models\Question::factory()->create();

        return [
            'exam_id' => $exam->id,
            'question_id' => $question->id,
            'question_number' => $this->faker->numberBetween(1, 100),
            'content' => $question->content,
            'options' => $question->getOptionsForExam(),
            'key_answer' => $question->getKeyAnswerForExam(),
            'score_value' => (int) ($question->score->value ?? $question->score ?? 10),
            'question_type' => $question->type,
            'difficulty_level' => $question->difficulty,
            'media_path' => $question->media_path,
            'hint' => $question->hint,
        ];
    }
}
