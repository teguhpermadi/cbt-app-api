<?php

namespace App\Services;

use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionScoreEnum;
use App\Enums\QuestionTimeEnum;
use App\Enums\QuestionTypeEnum;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuestionBank;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Spatie\MediaLibrary\HasMedia;

class WordToDatabaseParserService
{
    protected $createdQuestions = [];
    protected $errors = [];

    /**
     * Parse Word document (2-column Key-Value format) and create questions
     */
    public function parse(string $filePath, string $authorId, ?string $questionBankId = null): array
    {
        try {
            $phpWord = IOFactory::load($filePath);
            $sections = $phpWord->getSections();

            DB::beginTransaction();

            foreach ($sections as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof Table) {
                        $this->processTable($element, $authorId, $questionBankId);
                    }
                }
            }

            if (empty($this->createdQuestions) && !empty($this->errors)) {
                throw new Exception("Gagal mengimpor soal. Periksa format tabel dalam dokumen.");
            }

            DB::commit();

            return [
                'success' => true,
                'total' => count($this->createdQuestions),
                'errors' => $this->errors,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Word import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'total' => 0,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Process a single question table
     */
    protected function processTable(Table $table, string $authorId, ?string $questionBankId): void
    {
        $data = [
            'type' => null,
            'score' => 1,
            'question' => ['text' => '', 'images' => []],
            'option' => ['text' => '', 'images' => []],
            'key' => '',
            'hint' => '',
            'tag' => '',
        ];

        foreach ($table->getRows() as $row) {
            $cells = $row->getCells();
            if (count($cells) < 2) continue;

            $key = strtolower(trim($this->extractRawText($cells[0])));
            $content = $this->extractCellContent($cells[1]);

            if (array_key_exists($key, $data)) {
                $data[$key] = $content;
            }
        }

        // Validate mandatory fields
        if (empty($data['type']['text']) || empty($data['question']['text'])) {
            return;
        }

        try {
            $typeCode = (int) $data['type']['text'];
            $type = $this->mapType($typeCode);

            if (!$type) {
                throw new Exception("Tipe soal '{$typeCode}' tidak didukung.");
            }

            $scoreValue = (int) $data['score']['text'];
            $scoreEnum = QuestionScoreEnum::tryFrom($scoreValue) ?? QuestionScoreEnum::ONE;

            $question = Question::create([
                'user_id' => $authorId,
                'type' => $type,
                'content' => $this->formatRichText($data['question']['text']),
                'score' => $scoreEnum,
                'hint' => $data['hint']['text'] ?? null,
                'difficulty' => QuestionDifficultyLevelEnum::Medium,
                'timer' => QuestionTimeEnum::THIRTY_SECONDS,
                'is_approved' => true,
                'order' => count($this->createdQuestions) + 1,
            ]);

            if ($questionBankId) {
                $question->questionBanks()->attach($questionBankId);
            }

            // Attach Question Images
            $this->attachImages($question, $data['question']['images'], 'question_content');

            // Handle Tags
            if (!empty($data['tag']['text'])) {
                $tags = array_map('trim', explode(',', $data['tag']['text']));
                $question->attachTags(array_filter($tags));
            }

            // Handle Options logic
            $this->createOptions($question, $type, $data['option'], $data['key']['text']);

            $this->createdQuestions[] = $question->id;
        } catch (Exception $e) {
            $this->errors[] = "Tabel " . (count($this->createdQuestions) + count($this->errors) + 1) . ": " . $e->getMessage();
        }
    }

    /**
     * Map numeric code to QuestionTypeEnum
     */
    protected function mapType(int $code): ?QuestionTypeEnum
    {
        return match ($code) {
            1 => QuestionTypeEnum::MULTIPLE_CHOICE,
            2 => QuestionTypeEnum::MULTIPLE_SELECTION,
            3 => QuestionTypeEnum::TRUE_FALSE,
            4 => QuestionTypeEnum::SHORT_ANSWER,
            5 => QuestionTypeEnum::ESSAY,
            6 => QuestionTypeEnum::MATH_INPUT,
            7 => QuestionTypeEnum::SEQUENCE,
            8 => QuestionTypeEnum::ARABIC_RESPONSE,
            9 => QuestionTypeEnum::JAVANESE_RESPONSE,
            default => null,
        };
    }

    /**
     * Create options based on type
     */
    protected function createOptions(Question $question, QuestionTypeEnum $type, array $optionData, string $keyAnswer): void
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $optionData['text']))));

        switch ($type) {
            case QuestionTypeEnum::MULTIPLE_CHOICE:
            case QuestionTypeEnum::MULTIPLE_SELECTION:
                foreach ($lines as $index => $line) {
                    $key = chr(65 + $index); // A, B, C...
                    $isCorrect = false;

                    if ($type === QuestionTypeEnum::MULTIPLE_CHOICE) {
                        $isCorrect = (strtoupper(trim($keyAnswer)) === $key);
                    } else {
                        $correctKeys = array_map('trim', array_map('strtoupper', explode(',', $keyAnswer)));
                        $isCorrect = in_array($key, $correctKeys);
                    }

                    $option = Option::create([
                        'question_id' => $question->id,
                        'option_key' => $key,
                        'content' => $this->formatRichText($line),
                        'order' => $index,
                        'is_correct' => $isCorrect,
                    ]);

                    // Attach images found in the 'option' cell to ALL options as a fallback 
                    // or better: just the first one? Usually options in Word are text.
                    // Request says: "attach image tersebut menjadi milik question / option tersebut"
                    // If multiple options, mapping image to specific option is hard in plain text split.
                    // We'll attach images to the question if we can't map them.
                    if ($index === 0) {
                        $this->attachImages($option, $optionData['images'], 'option_media');
                    }
                }
                break;

            case QuestionTypeEnum::TRUE_FALSE:
                $correct = (strtoupper(trim($keyAnswer)) === 'T' || strtoupper(trim($keyAnswer)) === 'TRUE' || strtoupper(trim($keyAnswer)) === 'BENAR');
                Option::createTrueFalseOptions($question->id, $correct);
                break;

            case QuestionTypeEnum::SHORT_ANSWER:
                // Multiple correct answers can be separated by newline in the 'key' cell
                $answers = array_values(array_filter(array_map('trim', explode("\n", $keyAnswer))));
                Option::createShortAnswerOptions($question->id, $answers);
                break;

            case QuestionTypeEnum::ESSAY:
                Option::createEssayOption($question->id, $keyAnswer);
                break;

            case QuestionTypeEnum::MATH_INPUT:
                Option::createMathInputOption($question->id, $keyAnswer);
                break;

            case QuestionTypeEnum::SEQUENCE:
                Option::createOrderingOptions($question->id, $lines);
                break;

            case QuestionTypeEnum::ARABIC_RESPONSE:
                Option::createArabicOption($question->id, $keyAnswer);
                break;

            case QuestionTypeEnum::JAVANESE_RESPONSE:
                Option::createJavaneseOption($question->id, $keyAnswer);
                break;
        }
    }

    /**
     * Generate Word Template
     */
    public function generateTemplate(): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addTitle('TEMPLATE IMPORT SOAL (WORD-TO-DATABASE)', 1);
        $section->addText('Gunakan format tabel 2 kolom berikut untuk setiap soal. Jangan mengubah teks di kolom pertama (Key).');
        $section->addTextBreak(1);

        $styleTable = ['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80];
        $phpWord->addTableStyle('QuestionTable', $styleTable);

        $table = $section->addTable('QuestionTable');
        $rows = [
            ['type', '1'],
            ['score', '1'],
            ['question', 'Apa ibu kota Indonesia?'],
            ['option', "Jakarta\nBandung\nSurabaya\nMedan"],
            ['key', 'A'],
            ['hint', 'Terletak di pulau Jawa'],
            ['tag', 'geografi, umum'],
        ];

        foreach ($rows as $rowData) {
            $row = $table->addRow();
            $row->addCell(2000)->addText($rowData[0], ['bold' => true]);
            $row->addCell(8000)->addText($rowData[1]);
        }

        $section->addTextBreak(2);
        $section->addTitle('LEGENDA TIPE SOAL (KODE):', 2);
        $types = [
            '1' => 'Multiple Choice',
            '2' => 'Multiple Selection',
            '3' => 'True/False',
            '4' => 'Short Answer (Isian Singkat)',
            '5' => 'Essay',
            '6' => 'Math Input',
            '7' => 'Sequence (Urutan)',
            '8' => 'Arabic Response',
            '9' => 'Javanese Response',
        ];

        foreach ($types as $code => $label) {
            $section->addText("{$code} : {$label}");
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'template_') . '.docx';
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempFile);

        return $tempFile;
    }

    /**
     * Helpers for extraction
     */
    protected function extractRawText($element): string
    {
        $text = '';
        if ($element instanceof Text) {
            $text .= $element->getText();
        } elseif ($element instanceof TextRun) {
            foreach ($element->getElements() as $child) {
                $text .= $this->extractRawText($child);
            }
        } elseif ($element instanceof TextBreak) {
            $text .= "\n";
        } elseif (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $text .= $this->extractRawText($child);
            }
            if (get_class($element) === 'PhpOffice\PhpWord\Element\ListItem') {
                $text .= "\n";
            }
        }
        return $text;
    }

    protected function extractCellContent($cell): array
    {
        $images = [];
        $text = $this->recursiveExtract($cell, $images);
        return [
            'text' => trim($text),
            'images' => $images
        ];
    }

    protected function recursiveExtract($element, &$images): string
    {
        $text = '';
        if ($element instanceof TextBreak) {
            return "\n";
        }
        if ($element instanceof Text) {
            return $element->getText();
        }
        if ($element instanceof Image) {
            $images[] = $element;
            return "";
        }
        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $text .= $this->recursiveExtract($child, $images);
            }
            if ($element instanceof TextRun) {
                $text .= "\n"; // Simple split for rows
            }
        }
        return $text;
    }

    protected function formatRichText(string $text): string
    {
        if (empty($text)) return '';

        // Handle LaTeX pattern $...$
        $text = preg_replace_callback('/\$([^$]+)\$/', function ($m) {
            return '<span data-type="math-component" latex="' . htmlspecialchars($m[1]) . '"></span>';
        }, $text);

        // Basic multi-line to P tags if many
        $lines = explode("\n", $text);
        if (count($lines) > 1) {
            return collect($lines)->map(fn($l) => trim($l) ? "<p>{$l}</p>" : "")->implode('');
        }
        return $text;
    }

    protected function attachImages(HasMedia $model, array $images, string $collection): void
    {
        foreach ($images as $image) {
            try {
                $source = $image->getSource();
                $binaryData = null;
                $extension = $image->getImageExtension() ?: 'png';

                if (str_starts_with($source, 'data:image')) {
                    $model->addMediaFromBase64($source)
                        ->usingFileName('img_' . uniqid() . '.' . $extension)
                        ->toMediaCollection($collection);
                    continue;
                }

                if (method_exists($image, 'getImageStringData')) {
                    $binaryData = $image->getImageStringData();
                } elseif (file_exists($source)) {
                    $binaryData = file_get_contents($source);
                }

                if ($binaryData) {
                    // Check if hex
                    if (ctype_xdigit($binaryData) && strlen($binaryData) > 128) {
                        $binaryData = hex2bin($binaryData);
                    }

                    $model->addMediaFromString($binaryData)
                        ->usingFileName('img_' . uniqid() . '.' . $extension)
                        ->toMediaCollection($collection);
                }
            } catch (Exception $e) {
                Log::warning("Failed to attach image from Word: " . $e->getMessage());
            }
        }
    }
}
