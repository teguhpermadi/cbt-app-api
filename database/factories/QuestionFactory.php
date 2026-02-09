<?php

namespace Database\Factories;

use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionScoreEnum;
use App\Enums\QuestionTimeEnum;
use App\Enums\QuestionTypeEnum;
use App\Models\Option;
use App\Models\Question;
use App\Models\ReadingMaterial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(QuestionTypeEnum::cases());

        return [
            'user_id' => User::inRandomOrder()->first() ?? User::factory(),
            'type' => $type,
            'difficulty' => $this->faker->randomElement(QuestionDifficultyLevelEnum::cases()),
            'timer' => $this->faker->randomElement(QuestionTimeEnum::cases()),
            'content' => $this->generateQuestionContent($type),
            'reading_material_id' => ReadingMaterial::inRandomOrder()->first() ?? ReadingMaterial::factory(),
            'hint' => $this->faker->optional()->sentence(),
            'score' => $this->faker->randomElement(QuestionScoreEnum::cases()),
            'order' => $this->faker->numberBetween(1, 50),
            'is_approved' => $this->faker->boolean(50),
        ];
    }

    /**
     * Configure the model factory.
     */
    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Question $question) {
            // Attach media to question if GD is available
            if (extension_loaded('gd')) {
                $this->attachDummyMedia($question, 'question_content', "Question");
            }

            $this->createOptionsForQuestion($question);
        });
    }

    /**
     * Create a question with specific type
     */
    public function withType(QuestionTypeEnum $type): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => $type,
            'content' => $this->generateQuestionContent($type),
        ]);
    }

    /**
     * Generate question content based on type
     */
    private function generateQuestionContent(QuestionTypeEnum $type): string
    {
        return match ($type) {
            QuestionTypeEnum::MULTIPLE_CHOICE => 'Pilih satu jawaban yang paling tepat dari pilihan yang tersedia: ' . $this->faker->sentence(),
            QuestionTypeEnum::MULTIPLE_SELECTION => 'Pilih semua jawaban yang benar (bisa lebih dari satu): ' . $this->faker->sentence(),
            QuestionTypeEnum::TRUE_FALSE => 'Tentukan apakah pernyataan berikut benar atau salah: ' . $this->faker->sentence(),
            QuestionTypeEnum::ESSAY => $this->generateEssayQuestion(),
            QuestionTypeEnum::MATCHING => 'Jodohkan item di Kolom Kiri dengan item yang tepat di Kolom Kanan.',
            QuestionTypeEnum::SEQUENCE => 'Urutkan langkah-langkah berikut secara kronologis.',
            QuestionTypeEnum::MATH_INPUT => $this->generateNumericalQuestion(),
            QuestionTypeEnum::ARRANGE_WORDS => 'Susunlah kata-kata berikut menjadi kalimat yang benar.',
            default => $this->faker->sentence(),
        };
    }

    /**
     * Generate numerical question with LaTeX
     */
    private function generateNumericalQuestion(): string
    {
        $mathExpressions = [
            'Hitung nilai dari $$\\frac{3}{4} + \\frac{2}{5}$$',
            'Jika $$x = 2$$, hitung nilai dari $$2x^2 + 3x - 5$$',
            'Hitung luas persegi panjang dengan panjang $$\\sqrt{16}$$ cm dan lebar $$\\frac{3}{2}$$ cm',
            'Hitung nilai dari $$\\sin(30°) + \\cos(60°)$$',
            'Jika $$a = 3$$ dan $$b = 4$$, hitung $$\\sqrt{a^2 + b^2}$$',
            'Hitung volume kubus dengan rusuk $$\\sqrt[3]{27}$$ cm',
            'Hitung nilai dari $$\\frac{3}{4} + \\frac{2}{5}$$',
        ];

        return $this->faker->randomElement($mathExpressions) . ' (Masukkan jawaban numerik)';
    }

    /**
     * Generate essay question with prefixes
     */
    private function generateEssayQuestion(): string
    {
        $prefixes = [
            'Jelaskan mengapa',
            'Bagaimana proses',
            'Berikan contoh dari',
            'Uraikan dampak dari',
            'Apa perbedaan antara',
            'Analisis faktor-faktor yang mempengaruhi'
        ];

        $topics = [
            'pemanasan global',
            'demokrasi di Indonesia',
            'kecerdasan buatan',
            'siklus air',
            'revolusi industri 4.0',
            'keanekaragaman hayati',
            'sistem tata surya',
            'perang dunia kedua'
        ];

        return $this->faker->randomElement($prefixes) . ' ' . $this->faker->randomElement($topics) . '?';
    }

    /**
     * Create options for question based on type
     */
    private function createOptionsForQuestion(Question $question): void
    {
        match ($question->type) {
            QuestionTypeEnum::MULTIPLE_CHOICE => $this->createMultipleChoiceOptions($question),
            QuestionTypeEnum::MULTIPLE_SELECTION => $this->createMultipleSelectionOptions($question),
            QuestionTypeEnum::TRUE_FALSE => $this->createTrueFalseOptions($question),
            QuestionTypeEnum::MATCHING => $this->createMatchingOptions($question),
            QuestionTypeEnum::SEQUENCE => $this->createOrderingOptions($question),
            QuestionTypeEnum::MATH_INPUT => $this->createNumericalInputOption($question),
            QuestionTypeEnum::ARRANGE_WORDS => $this->createArrangeWordsOptions($question),
            QuestionTypeEnum::ESSAY => $this->createEssayOption($question),
            default => throw new \Exception('Unknown type in factory: ' . ($question->type->value ?? 'null')),
        };
    }

    /**
     * Create multiple choice options
     */
    private function createMultipleChoiceOptions(Question $question): void
    {
        $options = [];
        $correctKey = $this->faker->randomElement(['A', 'B', 'C', 'D']);

        foreach (['A', 'B', 'C', 'D'] as $key) {
            $options[] = [
                'key' => $key,
                'content' => $this->faker->sentence(3),
                'is_correct' => $key === $correctKey,
            ];
        }

        $createdOptions = Option::createMultipleChoiceOptions($question->id, $options);

        if (extension_loaded('gd')) {
            foreach ($createdOptions as $option) {
                $this->attachDummyMedia($option, 'option_media', "Option {$option->option_key}");
            }
        }
    }

    /**
     * Create multiple selection options
     */
    private function createMultipleSelectionOptions(Question $question): void
    {
        $options = [];
        $correctKeys = $this->faker->randomElements(['A', 'B', 'C', 'D'], $this->faker->numberBetween(2, 3));

        foreach (['A', 'B', 'C', 'D'] as $key) {
            $options[] = [
                'key' => $key,
                'content' => $this->faker->sentence(3),
                'is_correct' => in_array($key, $correctKeys),
            ];
        }

        $createdOptions = Option::createMultipleChoiceOptions($question->id, $options);

        if (extension_loaded('gd')) {
            foreach ($createdOptions as $option) {
                $this->attachDummyMedia($option, 'option_media', "Option {$option->option_key}");
            }
        }
    }

    /**
     * Create true/false options
     */
    private function createTrueFalseOptions(Question $question): void
    {
        $correctAnswer = $this->faker->boolean();
        $createdOptions = Option::createTrueFalseOptions($question->id, $correctAnswer);

        if (extension_loaded('gd')) {
            foreach ($createdOptions as $option) {
                $this->attachDummyMedia($option, 'option_media', "Option {$option->option_key}");
            }
        }
    }

    /**
     * Create matching options
     */
    private function createMatchingOptions(Question $question): void
    {
        $pairs = [];
        $topics = ['Indonesia', 'Malaysia', 'Thailand', 'Singapura'];
        $answers = ['Jakarta', 'Kuala Lumpur', 'Bangkok', 'Singapura'];

        for ($i = 0; $i < 4; $i++) {
            $pairs[] = [
                'left' => $topics[$i],
                'right' => $answers[$i],
            ];
        }

        $createdOptions = Option::createMatchingOptions($question->id, $pairs);

        if (extension_loaded('gd')) {
            foreach ($createdOptions as $option) {
                $this->attachDummyMedia($option, 'option_media', "Match {$option->option_key}");
            }
        }
    }

    /**
     * Create ordering options
     */
    private function createOrderingOptions(Question $question): void
    {
        $steps = [
            'Siapkan bahan-bahan yang diperlukan.',
            'Aduk rata telur dan gula.',
            'Masukkan terigu secara bertahap.',
            'Panggang selama 30 menit.',
        ];

        $createdOptions = Option::createOrderingOptions($question->id, $steps);

        if (extension_loaded('gd')) {
            foreach ($createdOptions as $option) {
                $this->attachDummyMedia($option, 'option_media', "Order {$option->option_key}");
            }
        }
    }

    /**
     * Create numerical input option
     */
    private function createNumericalInputOption(Question $question): void
    {
        $answers = [
            '10',
            '-5',
            '0.5',
            '1.5',
            '25',
            '8',
            '3.14',
            '1',
            '0.75',
            '100',
        ];

        $selected = $this->faker->randomElement($answers);
        $option = Option::createNumericalInputOption(
            $question->id,
            $selected
        );

        if (extension_loaded('gd')) {
            $this->attachDummyMedia($option, 'option_media', "Num {$option->option_key}");
        }
    }

    /**
     * Create arrange words options
     */
    private function createArrangeWordsOptions(Question $question): void
    {
        $sentences = [
            'Saya pergi ke pasar membeli sayur',
            'Ibu memasak nasi di dapur',
            'Ayah membaca koran di teras',
            'Adik bermain bola di lapangan',
            'The quick brown fox jumps over the lazy dog',
        ];

        $sentence = $this->faker->randomElement($sentences);
        $delimiter = ' ';

        $option = Option::createArrangeWordsOption($question->id, $sentence, $delimiter);

        if (extension_loaded('gd')) {
            $this->attachDummyMedia($option, 'option_media', "Arrange Words");
        }
    }

    /**
     * Create essay option (Rubric)
     */
    private function createEssayOption(Question $question): void
    {
        $content = $question->content;
        $rubric = "Rubrik Penilaian:\n";

        if (str_contains(strtolower($content), 'jelaskan')) {
            $rubric .= "- Menjelaskan definisi secara tepat (Skor 3)\n";
            $rubric .= "- Menguraikan alasan utama (Skor 4)\n";
            $rubric .= "- Memberikan kesimpulan yang logis (Skor 3)";
        } elseif (str_contains(strtolower($content), 'contoh')) {
            $rubric .= "- Memberikan minimal 3 contoh yang relevan (Skor 5)\n";
            $rubric .= "- Menjelaskan konteks setiap contoh (Skor 5)";
        } elseif (str_contains(strtolower($content), 'perbedaan')) {
            $rubric .= "- Menyebutkan minimal 3 perbedaan (Skor 6)\n";
            $rubric .= "- Menjelaskan dari segi konsep (Skor 4)";
        } else {
            $rubric .= "- Jawaban relevan dengan pertanyaan (Skor 5)\n";
            $rubric .= "- Struktur kalimat baik dan mudah dipahami (Skor 5)";
        }

        $rubric .= "\n\nKunci Jawaban Singkat: Jawaban harus mencakup aspek A, B, dan C.";

        Option::createEssayOption($question->id, $rubric);
    }

    /**
     * Generate and attach a dummy image using GD
     */
    private function attachDummyMedia($model, string $collectionName, string $text): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $width = 400;
        $height = 300;
        $image = @imagecreatetruecolor($width, $height);

        if (!$image) {
            return;
        }

        // Random background color
        $bgColor = imagecolorallocate($image, rand(50, 200), rand(50, 200), rand(50, 200));
        imagefill($image, 0, 0, $bgColor);

        // Text color (White)
        $textColor = imagecolorallocate($image, 255, 255, 255);
        $font = 5; // Largest built-in font

        // Centering text
        $charWidth = imagefontwidth($font);
        $charHeight = imagefontheight($font);
        $textLen = strlen($text);

        $x = ($width - ($textLen * $charWidth)) / 2;
        $y = ($height - $charHeight) / 2;

        imagestring($image, $font, (int)$x, (int)$y, $text, $textColor);

        // Save to temp file
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('media_', true) . '.jpg';

        if (imagejpeg($image, $tempPath)) {
            imagedestroy($image);

            // Attach to model using Spatie Media Library
            try {
                $model->addMedia($tempPath)
                    ->preservingOriginal()
                    ->toMediaCollection($collectionName);
            } catch (\Throwable $t) {
                // Ignore errors during attachment in factory
            }
        } else {
            imagedestroy($image);
        }
    }
}
