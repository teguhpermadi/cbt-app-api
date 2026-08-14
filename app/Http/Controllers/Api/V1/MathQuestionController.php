<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\MathGenerate\BatchPreviewMathQuestionRequest;
use App\Http\Requests\Api\V1\MathGenerate\PreviewMathQuestionRequest;
use App\Http\Requests\Api\V1\MathGenerate\SaveMathQuestionRequest;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Services\MathEngineService;
use App\Services\MathQuestionTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MathQuestionController extends ApiController
{
    public function __construct(
        private MathEngineService $mathEngine,
        private MathQuestionTransformer $transformer,
    ) {
    }

    /**
     * Generate preview soal matematika tanpa menyimpan ke database.
     */
    public function preview(PreviewMathQuestionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Build payload for Math Engine
            $payload = $this->buildPayload($validated);

            // Determine endpoint based on domain
            $endpoint = $this->getEndpoint($validated['domain']);

            // Generate questions
            $previews = [];
            $count = $validated['count'];

            for ($i = 0; $i < $count; $i++) {
                // Use unique seed per question for determinism
                $questionPayload = $payload;
                if (isset($questionPayload['seed'])) {
                    $questionPayload['seed'] = $questionPayload['seed'] + $i;
                }

                $response = $this->mathEngine->generate($endpoint, $questionPayload);
                $preview = $this->transformer->toPreview($response, [
                    'score' => $validated['score'] ?? null,
                    'timer' => $validated['timer'] ?? null,
                    'hint' => $validated['hint'] ?? null,
                    'tags' => $validated['tags'] ?? null,
                ]);

                $previews[] = $preview;
            }

            return $this->success([
                'previews' => $previews,
                'total_generated' => count($previews),
                'engine_version' => '2.0.0',
            ], 'Preview generated successfully');

        } catch (\RuntimeException $e) {
            Log::error('Math Engine error during preview', [
                'error' => $e->getMessage(),
                'validated' => $validated,
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 502);
        } catch (\Throwable $e) {
            Log::error('Unexpected error during preview', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Terjadi kesalahan saat generate soal', 500);
        }
    }

    /**
     * Generate batch preview dari multiple domain.
     */
    public function batchPreview(BatchPreviewMathQuestionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $requirements = $validated['requirements'];
        $masterSeed = $validated['master_seed'] ?? random_int(1, 999999999);

        try {
            $allPreviews = [];

            foreach ($requirements as $index => $req) {
                $domain = $req['domain'];
                $count = $req['count'] ?? 1;

                // Derive sub-seed for determinism
                $subSeed = $this->deriveSubSeed($masterSeed, $index, $domain);

                $payload = $this->buildPayload(array_merge($req, ['seed' => $subSeed]));
                $endpoint = $this->getEndpoint($domain);

                for ($i = 0; $i < $count; $i++) {
                    $questionPayload = $payload;
                    $questionPayload['seed'] = $subSeed + $i;

                    $response = $this->mathEngine->generate($endpoint, $questionPayload);
                    $allPreviews[] = $this->transformer->toPreview($response);
                }
            }

            return $this->success([
                'previews' => $allPreviews,
                'total_generated' => count($allPreviews),
                'master_seed' => $masterSeed,
            ], 'Batch preview generated successfully');

        } catch (\RuntimeException $e) {
            return $this->error('Gagal generate batch: '.$e->getMessage(), 502);
        }
    }

    /**
     * Simpan soal hasil preview ke Question Bank.
     */
    public function save(SaveMathQuestionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $questionBankId = $validated['question_bank_id'];
        $previews = $validated['previews'];

        // Verify question bank belongs to user
        $questionBank = QuestionBank::where('id', $questionBankId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $questionBank) {
            return $this->notFound('Question Bank tidak ditemukan atau bukan milik Anda.');
        }

        try {
            $savedIds = [];
            $maxOrder = Question::whereHas('questionBanks', function ($q) use ($questionBankId) {
                $q->where('question_bank_id', $questionBankId);
            })->max('order') ?? 0;

            DB::transaction(function () use ($previews, $questionBankId, &$savedIds, &$maxOrder) {
                foreach ($previews as $previewData) {
                    $transformed = $this->transformer->toSaveFormat($previewData);
                    $questionData = $transformed['question'];
                    $optionsData = $transformed['options'];

                    // Set user_id and order
                    $questionData['user_id'] = Auth::id();
                    $questionData['order'] = ++$maxOrder;

                    // Create question
                    $question = Question::create($questionData);

                    // Create options
                    foreach ($optionsData as $optionData) {
                        $optionData['question_id'] = $question->id;
                        Option::create($optionData);
                    }

                    // Attach to question bank
                    $question->questionBanks()->attach($questionBankId);

                    // Attach tags if provided
                    $tags = $previewData['tags'] ?? [];
                    if (! empty($tags)) {
                        $question->attachTags($tags);
                    }

                    $savedIds[] = $question->id;
                }
            });

            return $this->created([
                'saved_count' => count($savedIds),
                'question_ids' => $savedIds,
                'question_bank_id' => $questionBankId,
                'questions_count_total' => QuestionBank::find($questionBankId)?->questions()->count() ?? 0,
            ], count($savedIds).' questions saved successfully');

        } catch (\Throwable $e) {
            Log::error('Failed to save math questions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Gagal menyimpan soal: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get available levels and their configurations.
     */
    public function levels(): JsonResponse
    {
        $levels = [];
        for ($i = 1; $i <= 7; $i++) {
            $levels[] = [
                'level' => $i,
                'difficulty' => $this->mathEngine->mapLevelToDifficulty($i),
                'allowed_number_types' => $this->getAllowedNumberTypesForLevel($i),
            ];
        }

        return $this->success([
            'levels' => $levels,
            'domains' => $this->mathEngine->getDomains(),
        ]);
    }

    /**
     * Get available domains and their metadata.
     */
    public function domains(): JsonResponse
    {
        return $this->success([
            'domains' => $this->mathEngine->getDomains(),
        ]);
    }

    /**
     * Build payload for Math Engine based on domain.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildPayload(array $data): array
    {
        $payload = [
            'seed' => $data['seed'] ?? random_int(1, 999999999),
            'level' => $data['level'],
            'with_story' => $data['with_story'] ?? false,
            'with_distractors' => $data['with_distractors'] ?? true,
            'distractor_count' => $data['distractor_count'] ?? config('mathengine.defaults.distractor_count', 3),
        ];

        $domain = $data['domain'];

        switch ($domain) {
            case 'arithmetic':
                $payload['operation'] = $data['operation'] ?? 'addition';
                $payload['number_type'] = $data['number_type'] ?? 'natural';
                $payload['operand_count'] = $data['operand_count'] ?? 2;
                break;

            case 'geometry':
                $payload['shape'] = $data['shape'] ?? 'cube';
                $payload['dimension'] = $data['dimension'] ?? '3D';
                break;

            case 'algebra':
                // Algebra doesn't need extra params
                break;

            case 'measurement':
                // Measurement doesn't need extra params
                break;

            case 'statistics':
                // Statistics doesn't need extra params
                break;

            case 'angles':
                $payload['type'] = $data['type'] ?? 'complementary';
                break;
        }

        return $payload;
    }

    /**
     * Get API endpoint for a domain.
     */
    private function getEndpoint(string $domain): string
    {
        return match ($domain) {
            'arithmetic' => 'arithmetic/generate',
            'geometry' => 'geometry/generate',
            'algebra' => 'algebra/generate',
            'measurement' => 'measurement/generate',
            'statistics' => 'statistics/generate',
            'angles' => 'angles/generate',
            default => 'arithmetic/generate',
        };
    }

    /**
     * Derive sub-seed for deterministic batch generation.
     */
    private function deriveSubSeed(int $masterSeed, int $index, string $domain): int
    {
        $seedString = "{$masterSeed}:{$index}:{$domain}";

        return (int) hexdec(substr(hash('sha256', $seedString), 0, 8)) % 1000000000;
    }

    /**
     * Get allowed number types for a given level.
     *
     * @return list<string>
     */
    private function getAllowedNumberTypesForLevel(int $level): array
    {
        return match (true) {
            $level <= 2 => ['natural', 'whole'],
            $level <= 5 => ['natural', 'whole', 'integer', 'rational'],
            default => ['natural', 'whole', 'integer', 'rational', 'real'],
        };
    }
}
