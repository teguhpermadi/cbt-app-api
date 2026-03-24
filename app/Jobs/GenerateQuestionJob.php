<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionScoreEnum;
use App\Enums\QuestionTimeEnum;
use App\Enums\QuestionTypeEnum;
use App\Models\Curriculum;
use App\Models\QuestionDraft;
use App\Models\Taxonomy;
use App\Services\AI\QuestionGeneratorContextService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Throwable;

final class GenerateQuestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ?Curriculum $curriculum = null,
        public ?string $subjectCode = null,
        public QuestionTypeEnum $type = QuestionTypeEnum::MULTIPLE_CHOICE,
        public QuestionDifficultyLevelEnum $difficulty = QuestionDifficultyLevelEnum::Easy,
        public int $count = 5,
        public ?string $customMaterial = null,
        public ?Taxonomy $taxonomy = null,
        public ?string $taxonomyCategory = null,
        public ?string $userId = null,
        public string $model = 'openai/gpt-4o-mini',
        public ?string $customPrompt = null
    ) {}

    public function handle(QuestionGeneratorContextService $contextService): array
    {
        $systemPrompt = $this->buildSystemPrompt();

        if ($this->customPrompt) {
            $userPrompt = $this->customPrompt."\n\n".$this->getJsonSchemaPrompt();
        } else {
            $context = $contextService->getFullContextForGeneration(
                $this->curriculum,
                $this->subjectCode,
                $this->taxonomy?->taxonomy_type,
                $this->taxonomy?->code
            );

            $contextPrompt = $contextService->formatForPrompt($context);
            $userPrompt = $this->buildUserPrompt($contextPrompt);
        }

        try {
            $response = $this->callAI($systemPrompt, $userPrompt);
            $responseArray = $response->toArray();
            $structured = $responseArray['structured'] ?? null;

            if (! $structured) {
                Log::warning('GenerateQuestionJob: No structured response', [
                    'curriculum' => $this->curriculum?->id,
                    'subject' => $this->subjectCode,
                    'type' => $this->type->value,
                    'custom_prompt' => $this->customPrompt !== null,
                ]);

                return [];
            }

            $questions = $structured['questions'] ?? $structured;

            if (empty($questions)) {
                Log::warning('GenerateQuestionJob: No questions generated', [
                    'curriculum' => $this->curriculum?->id,
                    'subject' => $this->subjectCode,
                    'type' => $this->type->value,
                ]);

                return [];
            }

            return $this->saveDrafts($questions, $userPrompt);

        } catch (Throwable $e) {
            Log::error('GenerateQuestionJob failed', [
                'error' => $e->getMessage(),
                'curriculum' => $this->curriculum?->id,
                'subject' => $this->subjectCode,
            ]);
            throw $e;
        }
    }

    protected function getLearningOutcomes(): array
    {
        return $this->curriculum->getLearningOutcomesBySubject($this->subjectCode);
    }

    protected function getTaxonomyInfo(): ?array
    {
        if (! $this->taxonomy) {
            return null;
        }

        return [
            'type' => $this->taxonomy->taxonomy_type,
            'name' => $this->taxonomy->name,
            'code' => $this->taxonomy->code,
            'description' => $this->taxonomy->description,
            'verbs' => $this->taxonomy->verbs ?? [],
            'subcategories' => $this->taxonomy->subcategories ?? [],
        ];
    }

    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah asisten guru pakar dalam pembuatan soal evaluasi pendidikan.
Kamu HARUS merespon HANYA dalam format JSON yang valid sesuai skema yang diberikan.

Penting:
- Semua pertanyaan harus dalam bahasa Indonesia yang baik dan benar
- Gunakan tanda baca yang tepat
- Pastikan konten pertanyaan jelas dan tidak ambigu
- Sesuaikan tingkat kesulitan soal dengan level yang diminta
PROMPT;
    }

    protected function buildUserPrompt(string $contextPrompt): string
    {
        $prompt = "Buat {$this->count} soal {$this->type->label()} ".
                  "dengan tingkat kesulitan {$this->difficulty->getLabel()}.\n\n";

        $prompt .= $contextPrompt."\n\n";

        if ($this->customMaterial) {
            $prompt .= "MATERI TAMBAHAN DARI USER:\n{$this->customMaterial}\n\n";
        }

        $prompt .= $this->getJsonSchemaPrompt();

        return $prompt;
    }

    protected function getJsonSchemaPrompt(): string
    {
        $schemaInstructions = match ($this->type) {
            QuestionTypeEnum::MULTIPLE_CHOICE => <<<'SCHEMA'
JSON Schema untuk setiap soal (HANYA JSON, tanpa teks lain):
[
  {
    "content": "Pertanyaan yang jelas dan spesifik",
    "options": [
      {"key": "A", "content": "Opsi jawaban A", "is_correct": false},
      {"key": "B", "content": "Opsi jawaban B", "is_correct": true},
      {"key": "C", "content": "Opsi jawaban C", "is_correct": false},
      {"key": "D", "content": "Opsi jawaban D", "is_correct": false}
    ],
    "explanation": "Penjelasan jawaban yang benar",
    "hint": "Petunjuk (opsional)"
  }
]
SCHEMA
            ,
            QuestionTypeEnum::MULTIPLE_SELECTION => <<<'SCHEMA'
JSON Schema untuk setiap soal:
{
  "content": "Pertanyaan yang jelas dan spesifik",
  "options": [
    {"key": "A", "content": "Opsi jawaban A", "is_correct": true},
    {"key": "B", "content": "Opsi jawaban B", "is_correct": true},
    {"key": "C", "content": "Opsi jawaban C", "is_correct": false},
    {"key": "D", "content": "Opsi jawaban D", "is_correct": true}
  ],
  "explanation": "Penjelasan jawaban yang benar",
  "hint": "Petunjuk (opsional)"
}
SCHEMA
            ,
            QuestionTypeEnum::TRUE_FALSE => <<<'SCHEMA'
JSON Schema untuk setiap soal:
{
  "content": "Pernyataan yang akan dinilai benar atau salah",
  "correct_answer": "T",
  "explanation": "Penjelasan mengapa pernyataan benar/salah",
  "hint": "Petunjuk (opsional)"
}
SCHEMA
            ,
            QuestionTypeEnum::SHORT_ANSWER => <<<'SCHEMA'
JSON Schema untuk setiap soal:
{
  "content": "Pertanyaan yang memerlukan jawaban singkat",
  "answers": ["jawaban yang diterima 1", "jawaban yang diterima 2"],
  "explanation": "Penjelasan jawaban",
  "hint": "Petunjuk (opsional)"
}
SCHEMA
            ,
            QuestionTypeEnum::ESSAY => <<<'SCHEMA'
JSON Schema untuk setiap soal:
{
  "content": "Pertanyaan esai yang memerlukan jawaban panjang",
  "rubric": "Rubrik penilaian yang jelas",
  "keywords": ["kata kunci 1", "kata kunci 2"],
  "hint": "Petunjuk (opsional)"
}
SCHEMA
            ,
            QuestionTypeEnum::MATCHING => <<<'SCHEMA'
JSON Schema untuk setiap soal:
{
  "content": "Petunjuk untuk menjodohkan",
  "pairs": [
    {"left": "Item di sisi kiri", "right": "Item di sisi kanan yang cocok"}
  ]
}
SCHEMA
            ,
            QuestionTypeEnum::SEQUENCE => <<<'SCHEMA'
JSON Schema untuk setiap soal:
{
  "content": "Petunjuk untuk menyusun urutan",
  "items": ["item 1", "item 2", "item 3", "item 4"]
}
SCHEMA
            ,
            QuestionTypeEnum::CATEGORIZATION => <<<'SCHEMA'
JSON Schema untuk setiap soal:
{
  "content": "Petunjuk untuk mengkategorikan",
  "groups": [
    {"title": "Nama Kategori 1", "items": ["item 1", "item 2"]},
    {"title": "Nama Kategori 2", "items": ["item 3", "item 4"]}
  ]
}
SCHEMA
            ,
            QuestionTypeEnum::MATH_INPUT => <<<'SCHEMA'
JSON Schema untuk setiap soal:
{
  "content": "Pertanyaan matematika",
  "answer": "jawaban dalam format LaTeX",
  "unit": "satuan (opsional)",
  "tolerance": 0
}
SCHEMA
            ,
            QuestionTypeEnum::ARRANGE_WORDS => <<<'SCHEMA'
JSON Schema untuk setiap soal:
{
  "content": "Petunjuk untuk menyusun kata",
  "sentence": "Kalimat lengkap yang akan diacak"
}
SCHEMA
            ,
            default => 'Sesuaikan format JSON dengan tipe soal yang diminta.'
        };

        return "Format Output (array of questions):\n".$schemaInstructions."\n\n".
               "Pastikan:\n".
               "- Pertanyaan sesuai dengan kurikulum dan taksonomi yang dipilih\n".
               "- Untuk multiple choice: 1 jawaban benar, 3 jawaban salah yang masuk akal\n".
               "- Untuk multiple selection: minimal 2 jawaban benar\n".
               "- Distraktor (jawaban salah) harus masuk akal dan bukan jawaban yang jelas salah\n";
    }

    protected function callAI(string $systemPrompt, string $userPrompt)
    {
        $response = Prism::text()
            ->using(Provider::OpenRouter, $this->model)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->withClientOptions(['timeout' => 180])
            ->asText();

        $text = $response->toArray()['text'] ?? '';

        $text = trim($text);
        $text = preg_replace('/^```json\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $data = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse AI response as JSON', [
                'error' => json_last_error_msg(),
                'text' => mb_substr($text, 0, 500),
            ]);

            return null;
        }

        $result = $response->toArray();
        $result['structured'] = $data;

        return new class($result)
        {
            private array $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function toArray(): array
            {
                return $this->data;
            }
        };
    }

    protected function getSchemaFields(): array
    {
        return [
            new StringSchema('content', 'The question content'),
            new ArraySchema('options', 'Options for multiple choice/selection', new ObjectSchema('option', 'Option data', [
                new StringSchema('key', 'Option key (A, B, C, D, etc)'),
                new StringSchema('content', 'Option content'),
                new BooleanSchema('is_correct', 'Whether this is the correct answer'),
            ], []), false),
            new StringSchema('explanation', 'Explanation of the correct answer', false),
            new StringSchema('hint', 'Hint for the question', false),
            new StringSchema('correct_answer', 'Correct answer for true/false', false),
            new ArraySchema('answers', 'Accepted answers for short answer', new StringSchema('answer', 'Accepted answer'), false),
            new StringSchema('rubric', 'Rubric for essay questions', false),
            new ArraySchema('keywords', 'Keywords for essay', new StringSchema('keyword', 'Keyword'), false),
            new ArraySchema('pairs', 'Matching pairs', new ObjectSchema('pair', 'Matching pair', [
                new StringSchema('left', 'Left item'),
                new StringSchema('right', 'Right item'),
            ], []), false),
            new ArraySchema('items', 'Items for sequence', new StringSchema('item', 'Sequence item'), false),
            new ArraySchema('groups', 'Groups for categorization', new ObjectSchema('group', 'Category group', [
                new StringSchema('title', 'Group title'),
                new ArraySchema('items', 'Group items', new StringSchema('item', 'Group item')),
            ], []), false),
            new StringSchema('answer', 'Math answer in LaTeX', false),
            new StringSchema('unit', 'Unit for math answer', false),
            new NumberSchema('tolerance', 'Tolerance for math answer', false),
            new StringSchema('sentence', 'Complete sentence for arrange words', false),
        ];
    }

    protected function saveDrafts(array $questions, string $prompt): array
    {
        $draftIds = [];
        $timer = $this->getDefaultTimer();
        $score = $this->getDefaultScore();

        foreach ($questions as $questionData) {
            $draft = QuestionDraft::create([
                'user_id' => $this->userId,
                'curriculum_id' => $this->curriculum ? (string) $this->curriculum->id : null,
                'subject_code' => $this->subjectCode,
                'type' => $this->type->value,
                'difficulty' => $this->difficulty->value,
                'timer' => $timer,
                'score' => $score,
                'content' => $questionData['content'] ?? '',
                'hint' => $questionData['hint'] ?? null,
                'taxonomy_type' => $this->taxonomy?->taxonomy_type,
                'taxonomy_code' => $this->taxonomy?->code,
                'custom_material' => $this->customMaterial,
                'options' => $this->normalizeOptions($questionData),
                'status' => QuestionDraft::STATUS_PENDING,
                'generated_by' => $this->model,
                'generation_prompt' => $prompt,
            ]);

            $draftIds[] = (string) $draft->id;

            $optionsSummary = collect($draft->options ?? [])->map(fn ($opt) => [
                'key' => $opt['option_key'] ?? $opt['key'] ?? null,
                'content' => mb_substr($opt['content'] ?? '', 0, 50),
                'is_correct' => $opt['is_correct'] ?? false,
            ])->toArray();

            Log::info('QuestionDraft created', [
                'draft_id' => $draft->id,
                'type' => $this->type->value,
                'difficulty' => $this->difficulty->value,
                'question_content' => mb_substr($draft->content, 0, 100),
                'question_length' => mb_strlen($draft->content),
                'options' => $optionsSummary,
                'hint' => $draft->hint,
                'explanation' => $questionData['explanation'] ?? '',
                'curriculum_id' => $draft->curriculum_id,
                'subject_code' => $draft->subject_code,
                'taxonomy_type' => $draft->taxonomy_type,
                'taxonomy_code' => $draft->taxonomy_code,
                'generated_by' => $this->model,
                'custom_prompt_mode' => $this->customPrompt !== null,
            ]);
        }

        return $draftIds;
    }

    protected function normalizeOptions(array $questionData): array
    {
        $type = $this->type;

        return match ($type) {
            QuestionTypeEnum::MULTIPLE_CHOICE,
            QuestionTypeEnum::MULTIPLE_SELECTION => $questionData['options'] ?? [],
            QuestionTypeEnum::TRUE_FALSE => [
                [
                    'option_key' => 'T',
                    'content' => 'Benar',
                    'is_correct' => ($questionData['correct_answer'] ?? 'T') === 'T',
                ],
                [
                    'option_key' => 'F',
                    'content' => 'Salah',
                    'is_correct' => ($questionData['correct_answer'] ?? 'T') === 'F',
                ],
            ],
            QuestionTypeEnum::SHORT_ANSWER,
            QuestionTypeEnum::ARABIC_RESPONSE,
            QuestionTypeEnum::JAVANESE_RESPONSE => collect($questionData['answers'] ?? [])
                ->map(fn ($answer, $index) => [
                    'option_key' => 'SA'.($index + 1),
                    'content' => is_array($answer) ? ($answer['content'] ?? $answer) : $answer,
                    'is_correct' => true,
                ])
                ->toArray(),
            QuestionTypeEnum::ESSAY => [
                [
                    'option_key' => 'ESSAY',
                    'content' => $questionData['rubric'] ?? '',
                    'is_correct' => true,
                    'metadata' => [
                        'type' => 'rubric',
                        'keywords' => $questionData['keywords'] ?? [],
                    ],
                ],
            ],
            QuestionTypeEnum::MATCHING => collect($questionData['pairs'] ?? [])
                ->flatMap(fn ($pair, $index) => [
                    [
                        'option_key' => 'L'.($index + 1),
                        'content' => $pair['left'] ?? '',
                        'is_correct' => true,
                        'metadata' => [
                            'side' => 'left',
                            'pair_id' => $index + 1,
                            'match_with' => 'R'.($index + 1),
                        ],
                    ],
                    [
                        'option_key' => 'R'.($index + 1),
                        'content' => $pair['right'] ?? '',
                        'is_correct' => true,
                        'metadata' => [
                            'side' => 'right',
                            'pair_id' => $index + 1,
                            'match_with' => 'L'.($index + 1),
                        ],
                    ],
                ])
                ->toArray(),
            QuestionTypeEnum::SEQUENCE => collect($questionData['items'] ?? [])
                ->map(fn ($item, $index) => [
                    'option_key' => (string) ($index + 1),
                    'content' => is_array($item) ? ($item['content'] ?? $item) : $item,
                    'is_correct' => true,
                    'metadata' => [
                        'correct_position' => $index + 1,
                    ],
                ])
                ->toArray(),
            QuestionTypeEnum::CATEGORIZATION => collect($questionData['groups'] ?? [])
                ->flatMap(fn ($group, $groupIndex) => collect($group['items'] ?? [])
                    ->map(fn ($item, $itemIndex) => [
                        'option_key' => 'C'.($groupIndex + 1).'I'.($itemIndex + 1),
                        'content' => is_array($item) ? ($item['content'] ?? $item) : $item,
                        'is_correct' => true,
                        'metadata' => [
                            'group_title' => $group['title'] ?? 'Category '.($groupIndex + 1),
                            'group_uuid' => \Illuminate\Support\Str::uuid()->toString(),
                        ],
                    ])
                )
                ->toArray(),
            QuestionTypeEnum::MATH_INPUT => [
                [
                    'option_key' => 'MATH',
                    'content' => $questionData['answer'] ?? '',
                    'is_correct' => true,
                    'metadata' => [
                        'correct_answer' => $questionData['answer'] ?? '',
                        'tolerance' => $questionData['tolerance'] ?? 0,
                        'unit' => $questionData['unit'] ?? null,
                    ],
                ],
            ],
            QuestionTypeEnum::ARRANGE_WORDS => [
                [
                    'option_key' => 'SENTENCE',
                    'content' => $questionData['sentence'] ?? '',
                    'is_correct' => true,
                    'metadata' => [
                        'delimiter' => ' ',
                        'shuffle_mode' => 'phrase',
                    ],
                ],
            ],
            default => [],
        };
    }

    protected function getDefaultTimer(): int
    {
        return match ($this->type) {
            QuestionTypeEnum::MULTIPLE_CHOICE,
            QuestionTypeEnum::MULTIPLE_SELECTION,
            QuestionTypeEnum::TRUE_FALSE => QuestionTimeEnum::THIRTY_SECONDS->value,
            QuestionTypeEnum::SHORT_ANSWER => QuestionTimeEnum::ONE_MINUTE->value,
            QuestionTypeEnum::ESSAY => QuestionTimeEnum::FIVE_MINUTES->value,
            QuestionTypeEnum::MATCHING,
            QuestionTypeEnum::SEQUENCE => QuestionTimeEnum::TWO_MINUTES->value,
            QuestionTypeEnum::CATEGORIZATION => QuestionTimeEnum::TWO_MINUTES->value,
            QuestionTypeEnum::MATH_INPUT => QuestionTimeEnum::ONE_MINUTE->value,
            QuestionTypeEnum::ARRANGE_WORDS => QuestionTimeEnum::ONE_MINUTE->value,
            default => QuestionTimeEnum::THIRTY_SECONDS->value,
        };
    }

    protected function getDefaultScore(): int
    {
        return match ($this->type) {
            QuestionTypeEnum::ESSAY => QuestionScoreEnum::FIVE->value,
            QuestionTypeEnum::MATCHING,
            QuestionTypeEnum::SEQUENCE,
            QuestionTypeEnum::CATEGORIZATION => QuestionScoreEnum::FIVE->value,
            default => QuestionScoreEnum::ONE->value,
        };
    }
}
