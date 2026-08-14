<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

final class MathQuestionTransformer
{
    private MathEngineService $mathEngine;

    public function __construct(MathEngineService $mathEngine)
    {
        $this->mathEngine = $mathEngine;
    }

    /**
     * Transform a single Math Engine response to CBT preview format.
     * Content is passed through raw (expression_latex or story), no wrapper text added.
     *
     * @param  array<string, mixed>  $engineResponse  Response from Python Math Engine
     * @param  array<string, mixed>  $overrides  User overrides (score, timer, hint, etc.)
     * @return array<string, mixed>
     */
    public function toPreview(array $engineResponse, array $overrides = []): array
    {
        $meta = $engineResponse['meta'] ?? [];
        $context = $engineResponse['context'] ?? [];
        $data = $engineResponse['data'] ?? [];

        $level = $meta['level'] ?? 1;
        $domain = $meta['operation'] ?? 'unknown';
        $story = $context['story'] ?? null;

        // Content: story if available, otherwise raw expression_latex
        $content = ($story !== null && $story !== '')
            ? $story
            : '$' . ($data['expression_latex'] ?? $data['expression'] ?? '') . '$';

        // Map difficulty
        $difficulty = $this->mathEngine->mapLevelToDifficulty((int) $level);

        // Map options: answer_choices_latex → A/B/C/D keys
        $options = $this->mapOptions(
            $data['answer_choices'] ?? [],
            $data['answer_choices_latex'] ?? [],
            $data['correct_answer'] ?? ''
        );

        return [
            'id' => (string) Str::uuid(),
            'content' => $content,
            'options' => $options,
            'correct_answer' => $data['correct_answer'] ?? '',
            'correct_answer_latex' => $data['correct_answer_latex'] ?? ($data['correct_answer'] ?? ''),
            'difficulty' => $difficulty,
            'answer_type' => $data['answer_type'] ?? 'natural',
            'domain' => $domain,
            'operation' => $meta['operation'] ?? null,
            'story' => $story,
            'score' => $overrides['score'] ?? config('mathengine.defaults.score', 2),
            'timer' => $overrides['timer'] ?? config('mathengine.defaults.timer', 60),
            'hint' => $overrides['hint'] ?? null,
            'tags' => $overrides['tags'] ?? [$domain],
            'math_metadata' => [
                'seed' => $meta['seed'] ?? null,
                'level' => $level,
                'number_type' => $meta['number_type'] ?? null,
                'expression' => $data['expression'] ?? '',
                'expression_latex' => $data['expression_latex'] ?? null,
                'blueprint' => $data['blueprint'] ?? [],
                'variables' => $data['variables'] ?? [],
            ],
        ];
    }

    /**
     * Map answer choices to CBT option format with A/B/C/D keys.
     *
     * @param  list<string>  $answerChoices
     * @param  list<string>  $answerChoicesLatex
     * @param  string  $correctAnswer
     * @return list<array{option_key: string, content: string, is_correct: bool, order: int}>
     */
    private function mapOptions(array $answerChoices, array $answerChoicesLatex, string $correctAnswer): array
    {
        $keys = ['A', 'B', 'C', 'D', 'E'];

        return array_map(function (int $index, string $choice) use ($keys, $answerChoicesLatex, $correctAnswer): array {
            $latex = $answerChoicesLatex[$index] ?? $choice;

            return [
                'option_key' => $keys[$index] ?? (string) ($index + 1),
                'content' => '$' . $latex . '$',
                'is_correct' => ((string) $choice) === ((string) $correctAnswer),
                'order' => $index,
            ];
        }, array_keys($answerChoices), $answerChoices);
    }

    /**
     * Transform preview to format suitable for saving to database.
     *
     * @param  array<string, mixed>  $preview
     * @return array{question: array<string, mixed>, options: list<array<string, mixed>>}
     */
    public function toSaveFormat(array $preview): array
    {
        $questionData = [
            'content' => $preview['content'] ?? '',
            'type' => 'multiple_choice',
            'difficulty' => $preview['difficulty'] ?? 'sedang',
            'timer' => (($preview['timer'] ?? 60) * 1000),
            'score' => $preview['score'] ?? config('mathengine.defaults.score', 2),
            'hint' => $preview['hint'] ?? null,
            'is_approved' => false,
            'math_metadata' => $preview['math_metadata'] ?? null,
        ];

        $optionsData = array_map(function (array $option): array {
            return [
                'option_key' => $option['option_key'] ?? 'A',
                'content' => $option['content'] ?? '',
                'is_correct' => $option['is_correct'] ?? false,
                'order' => $option['order'] ?? 0,
                'metadata' => [],
            ];
        }, $preview['options'] ?? []);

        return [
            'question' => $questionData,
            'options' => $optionsData,
        ];
    }
}
