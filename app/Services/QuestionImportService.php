<?php

namespace App\Services;

use App\Enums\QuestionTypeEnum;
use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionTimeEnum;
use App\Enums\QuestionScoreEnum;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuestionBank;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\Text;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Image;
use Spatie\MediaLibrary\HasMedia;

class QuestionImportService
{
    protected $questionBank;
    protected $createdQuestions = [];
    protected $errors = [];

    /**
     * Parse Word document and create questions
     *
     * @param string $filePath Full path to uploaded .docx file
     * @param string $questionBankId ULID of question bank
     * @param string $authorId ULID of key user
     * @return array ['success' => bool, 'questions' => array, 'errors' => array]
     */
    public function parseWordDocument(string $filePath, string $questionBankId, string $authorId): array
    {
        try {
            $this->questionBank = QuestionBank::findOrFail($questionBankId);

            // Load Word document
            $phpWord = IOFactory::load($filePath);

            // Extract table rows
            $rows = $this->extractTableRows($phpWord);

            if (empty($rows)) {
                throw new Exception('Tidak ada tabel ditemukan dalam dokumen Word atau tabel kosong.');
            }

            DB::beginTransaction();

            // Process each row (skip header row)
            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue; // Skip header
                }

                try {
                    $question = $this->parseRow($row, $authorId);
                    if ($question) {
                        $this->createdQuestions[] = $question;
                    }
                } catch (Exception $e) {
                    $this->errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                    Log::error("Question import error at row " . ($index + 1), [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            if (count($this->errors) > 0 && count($this->createdQuestions) === 0) {
                // If all failed, rollback
                throw new Exception("Semua baris gagal diimpor. Periksa format file Anda.");
            }

            DB::commit();

            return [
                'success' => true,
                'questions' => $this->createdQuestions,
                'errors' => $this->errors,
                'total' => count($this->createdQuestions),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Question import failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'questions' => [],
                'errors' => [$e->getMessage()],
                'total' => 0,
            ];
        }
    }

    /**
     * Extract all table rows from Word document
     *
     * @param \PhpOffice\PhpWord\PhpWord $phpWord
     * @return array Array of rows, each row is array of cell texts
     */
    protected function extractTableRows(\PhpOffice\PhpWord\PhpWord $phpWord): array
    {
        $rows = [];
        $sections = $phpWord->getSections();

        foreach ($sections as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof Table) {
                    foreach ($element->getRows() as $row) {
                        $cells = $row->getCells();
                        $rowValues = [];
                        foreach ($cells as $cell) {
                            $rowValues[] = $this->extractCellContent($cell);
                        }

                        // Skip strictly empty rows
                        if (empty(array_filter($rowValues, fn($v) => !empty(trim($v['text']))))) {
                            continue;
                        }

                        $rows[] = $rowValues;
                    }
                }
            }
        }

        return $rows;
    }


    /**
     * Extract text and images from a cell
     */
    protected function extractCellContent($cell): array
    {
        $images = [];
        $text = $this->recursiveExtractText($cell, $images);
        return [
            'text' => trim($text),
            'images' => $images
        ];
    }

    /**
     * Recursively extract text from any element, preserving intentional line breaks
     */
    protected function recursiveExtractText($element, &$images = []): string
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
                $text .= $this->recursiveExtractText($child, $images);
            }

            // Elements that act as block containers in Word (like paragraphs/TextRuns)
            // should usually end with a newline to separate them from the next block.
            if (
                $element instanceof TextRun ||
                get_class($element) === 'PhpOffice\PhpWord\Element\ListItem' ||
                get_class($element) === 'PhpOffice\PhpWord\Element\Table' ||
                get_class($element) === 'PhpOffice\PhpWord\Element\Title'
            ) {
                $text .= "\n";
            }
        } elseif (method_exists($element, 'getText')) {
            $text .= $element->getText();
        }

        return $text;
    }

    /**
     * Process placeholders and attach images to model
     */
    protected function processPlaceholdersAndAttach(HasMedia $model, string $text, array $images, string $collection)
    {
        if (empty($images)) return;

        // Just attach all images found in the cell
        foreach ($images as $image) {
            $this->attachPhpWordImage($model, $image, $collection);
        }
    }

    /**
     * Attach a PHPWord Image element to a Spatie Media model
     */
    protected function attachPhpWordImage(HasMedia $model, Image $image, string $collection)
    {
        try {
            $source = $image->getSource();
            $binaryData = null;
            $extension = $image->getImageExtension() ?: 'png';

            // Check if source is base64 data URI
            if (str_starts_with($source, 'data:image')) {
                $model->addMediaFromBase64($source)
                    ->usingFileName('image_' . uniqid() . '.' . $extension)
                    ->toMediaCollection($collection);
                return;
            }

            if (method_exists($image, 'getImageStringData')) {
                $binaryData = $image->getImageStringData();
            } elseif (file_exists($source)) {
                $binaryData = file_get_contents($source);
                $extension = pathinfo($source, PATHINFO_EXTENSION);
            }

            if (!$binaryData) {
                Log::warning("Gagal mendapatkan data biner untuk gambar dari source: " . substr($source, 0, 100));
                return;
            }

            // Fix: PHPWord sometimes returns hex-encoded string instead of raw binary
            if (ctype_xdigit($binaryData) && strlen($binaryData) > 128) {
                $binaryData = hex2bin($binaryData);
            }

            // Detect mime type and extension from binary data
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($binaryData);

            $extensionMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/bmp' => 'bmp',
                'image/x-ms-bmp' => 'bmp',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
            ];

            if (isset($extensionMap[$mimeType])) {
                $extension = $extensionMap[$mimeType];
            }

            $filename = 'image_' . uniqid() . '.' . $extension;
            Log::debug("Attaching image: " . $filename . " (Mime: {$mimeType}) | Size: " . strlen($binaryData));

            $model->addMediaFromString($binaryData)
                ->usingFileName($filename)
                ->toMediaCollection($collection);
        } catch (Exception $e) {
            Log::warning("Gagal melampirkan gambar ke koleksi {$collection}: " . $e->getMessage());
        }
    }

    /**
     * Parse a single row from table
     * Expected format: [Tipe Soal, Pertanyaan, Opsi, Kunci, Poin]
     *
     * @param array $cells Array of 5 cell texts
     * @return Question|null
     */
    protected function parseRow(array $cells, string $authorId): ?Question
    {
        if (count($cells) < 4) { // At least Type, Question, Key
            return null;
        }

        // Fill missing columns with empty string/images structure
        while (count($cells) < 6) {
            $cells[] = ['text' => '', 'images' => []];
        }

        [$typeCell, $questionCell, $optionsCell, $keyCell, $pointsCell, $tagsCell] = $cells;

        $typeStr = is_array($typeCell) ? $typeCell['text'] : $typeCell;
        $typeNormalized = strtoupper(trim($typeStr));

        // Skip header rows
        if (in_array($typeNormalized, ['TIPE SOAL', 'TYPE', 'QUESTION TYPE', 'NO', 'NUMBER', ''])) {
            return null;
        }

        // Validate question type
        $questionType = $this->parseQuestionType($typeStr);
        if (!$questionType) {
            if (empty($typeNormalized)) return null;
            throw new Exception("Tipe soal tidak valid: '{$typeStr}'");
        }

        // Validate score
        $scoreValue = is_numeric($pointsCell['text']) ? intval($pointsCell['text']) : 1;
        $scoreEnum = QuestionScoreEnum::tryFrom($scoreValue);

        // If score is invalid (e.g. 10, 15, 20), we default to 5 or throw error?
        // Let's coerce to closest available or default to 1 to prevent failure, 
        // but since we want strict checking, let's try to map typical values (10, 20..) to 1-5 if possible?
        // For now, strict check:
        if (!$scoreEnum) {
            // Attempt fallback mapping for common user inputs
            $scoreEnum = match (true) {
                $scoreValue > 5 => QuestionScoreEnum::FIVE,
                $scoreValue <= 0 => QuestionScoreEnum::ONE,
                default => QuestionScoreEnum::ONE
            };
        }

        try {
            DB::beginTransaction();

            $question = $this->createQuestion([
                'type' => $questionType,
                'content' => $questionCell['text'],
                'score' => $scoreEnum,
                'user_id' => $authorId,
            ]);

            // Attach images to question
            $this->processPlaceholdersAndAttach($question, $questionCell['text'], $questionCell['images'], 'question_content');

            // Create options based on type
            $this->createOptions($question, $questionType, $optionsCell, $keyCell['text']);

            // Attach Tags
            if (!empty(trim($tagsCell['text']))) {
                // Split by comma, trim, and filter empty
                $tags = array_values(array_filter(array_map('trim', explode(',', $tagsCell['text']))));
                if (!empty($tags)) {
                    $question->attachTags($tags);
                }
            }

            DB::commit();
            return $question;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Gagal mengimport baris: " . $e->getMessage(), ['cells' => $cells]);
            throw $e;
        }
    }

    /**
     * Parse question type string to enum
     *
     * @param string $typeStr
     * @return QuestionTypeEnum|null
     */
    protected function parseQuestionType(string $typeStr): ?QuestionTypeEnum
    {
        $typeStr = strtoupper(trim($typeStr));

        return match ($typeStr) {
            'MULTIPLE_CHOICE' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'MULTIPLE_SELECTION' => QuestionTypeEnum::MULTIPLE_SELECTION,
            'TRUE_FALSE' => QuestionTypeEnum::TRUE_FALSE,
            'MATCHING' => QuestionTypeEnum::MATCHING,
            'ORDERING' => QuestionTypeEnum::SEQUENCE, // Updated to match Enum name vs user input
            'SEQUENCE' => QuestionTypeEnum::SEQUENCE,
            'ESSAY' => QuestionTypeEnum::ESSAY,
            'NUMERICAL_INPUT' => QuestionTypeEnum::MATH_INPUT, // Updated to match Enum name vs user input
            'MATH_INPUT' => QuestionTypeEnum::MATH_INPUT,
            'SHORT_ANSWER' => QuestionTypeEnum::SHORT_ANSWER,
            'ARRANGE_WORDS' => QuestionTypeEnum::ARRANGE_WORDS,
            default => null,
        };
    }

    /**
     * Process text to convert specific patterns to Rich Text HTML components
     */
    protected function processRichText(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // 1. Convert Latex: $equation$ -> math-component
        $text = preg_replace_callback(
            '/\$([^$]+)\$/',
            function ($matches) {
                $latex = htmlspecialchars($matches[1], ENT_QUOTES);
                return '<span data-type="math-component" latex="' . $latex . '"></span>';
            },
            $text
        );

        // Enhance layout: Wrap in paragraph if it looks like a block
        $lines = explode("\n", $text);
        if (count($lines) > 1) {
            $html = '';
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    $html .= '<p>' . $line . '</p>';
                }
            }
            return $html;
        }

        return $text;
    }

    protected function createQuestion(array $data): Question
    {
        return Question::create([
            // 'question_bank_id' => $this->questionBank->id, // Removed: Not in Question model fillable
            'type' => $data['type'],
            'difficulty' => QuestionDifficultyLevelEnum::Medium,
            'timer' => QuestionTimeEnum::THIRTY_SECONDS,
            'content' => $this->processRichText($data['content']),
            'score' => $data['score'],
            'is_approved' => true,
            // 'is_active' => true, // Removed: Not in Question model fillable
            'user_id' => $data['user_id'],
        ]);
    }

    /**
     * Create options based on question type
     */
    protected function createOptions(Question $question, QuestionTypeEnum $type, array $optionsCell, string $keyAnswer): void
    {
        match ($type) {
            QuestionTypeEnum::MULTIPLE_CHOICE => $this->handleMultipleChoice($question, $optionsCell, $keyAnswer),
            QuestionTypeEnum::MULTIPLE_SELECTION => $this->handleMultipleSelection($question, $optionsCell, $keyAnswer),
            QuestionTypeEnum::TRUE_FALSE => $this->handleTrueFalse($question, $optionsCell, $keyAnswer),
            QuestionTypeEnum::MATCHING => $this->handleMatching($question, $optionsCell),
            QuestionTypeEnum::SEQUENCE => $this->handleSequence($question, $optionsCell),
            QuestionTypeEnum::ESSAY => $this->handleEssay($question, $keyAnswer),
            QuestionTypeEnum::MATH_INPUT => $this->handleMathInput($question, $optionsCell, $keyAnswer),
            QuestionTypeEnum::SHORT_ANSWER => $this->handleShortAnswer($question, $keyAnswer),
            QuestionTypeEnum::ARRANGE_WORDS => $this->handleArrangeWords($question, $optionsCell),
            default => throw new Exception("Handler untuk tipe soal {$type->value} belum diimplementasikan."),
        };
    }

    protected function handleMultipleChoice(Question $question, array $optionsCell, string $keyAnswer): void
    {
        $options = $this->splitOptions($optionsCell['text']);
        $correctKey = strtoupper(trim($keyAnswer));

        if (count($options) < 2) {
            throw new Exception("Soal Multiple Choice harus memiliki minimal 2 opsi jawaban.");
        }

        foreach ($options as $index => $optionText) {
            if (preg_match('/^([A-Z])\.\s*(.+)$/si', trim($optionText), $matches)) {
                $key = strtoupper($matches[1]);
                $content = trim($matches[2]);
            } else {
                $key = chr(65 + $index);
                $content = trim($optionText);
            }

            $option = Option::create([
                'question_id' => $question->id,
                'option_key' => $key,
                'content' => $this->processRichText($content),
                'order' => $index,
                'is_correct' => ($key === $correctKey),
            ]);

            $this->processPlaceholdersAndAttach($option, $optionText, $optionsCell['images'], 'option_media');
        }
    }

    protected function handleMultipleSelection(Question $question, array $optionsCell, string $keyAnswer): void
    {
        $options = $this->splitOptions($optionsCell['text']);
        $correctKeys = array_map('trim', explode(',', strtoupper($keyAnswer)));
        $correctKeys = array_map('strtoupper', $correctKeys);

        if (count($options) < 2) {
            throw new Exception("Soal Multiple Selection harus memiliki minimal 2 opsi jawaban.");
        }

        foreach ($options as $index => $optionText) {
            if (preg_match('/^([A-Z])\.\s*(.+)$/si', trim($optionText), $matches)) {
                $key = strtoupper($matches[1]);
                $content = trim($matches[2]);
            } else {
                $key = chr(65 + $index);
                $content = trim($optionText);
            }

            $option = Option::create([
                'question_id' => $question->id,
                'option_key' => $key,
                'content' => $this->processRichText($content),
                'order' => $index,
                'is_correct' => in_array($key, $correctKeys),
            ]);

            $this->processPlaceholdersAndAttach($option, $optionText, $optionsCell['images'], 'option_media');
        }
    }

    protected function handleTrueFalse(Question $question, array $optionsCell, string $keyAnswer): void
    {
        $options = $this->splitOptions($optionsCell['text']);
        $rawCorrectKey = strtoupper(trim($keyAnswer));

        if (empty($options)) {
            $options = ['A. Benar', 'B. Salah'];
        }

        $standardizedCorrectKey = match ($rawCorrectKey) {
            'A', 'TRUE', 'BENAR', 'YA', '1', 'T' => 'T',
            'B', 'FALSE', 'SALAH', 'TIDAK', '0', 'F' => 'F',
            default => $rawCorrectKey,
        };

        foreach ($options as $index => $optionText) {
            if (preg_match('/^([A-Z])\.\s*(.+)$/si', trim($optionText), $matches)) {
                $content = trim($matches[2]);
            } else {
                $content = trim($optionText);
            }

            $mappedKey = ($index === 0) ? 'T' : 'F';
            if ($index > 1) {
                $mappedKey = chr(70 + ($index - 1));
            }

            $option = Option::create([
                'question_id' => $question->id,
                'option_key' => $mappedKey,
                'content' => $this->processRichText($content),
                'order' => $index,
                'is_correct' => ($mappedKey === $standardizedCorrectKey),
            ]);

            $this->processPlaceholdersAndAttach($option, $optionText, $optionsCell['images'], 'option_media');
        }
    }

    protected function handleMatching(Question $question, array $optionsCell): void
    {
        $pairs = $this->splitOptions($optionsCell['text']);

        foreach ($pairs as $index => $pairText) {
            $parts = explode('::', $pairText);
            if (count($parts) !== 2) {
                continue;
            }

            $leftContent = trim($parts[0]);
            $rightContent = trim($parts[1]);

            $leftKey = 'L' . ($index + 1);
            $rightKey = 'R' . ($index + 1);

            $leftOption = Option::create([
                'question_id' => $question->id,
                'option_key' => $leftKey,
                'content' => $this->processRichText($leftContent),
                'order' => $index * 2,
                'is_correct' => false,
                'metadata' => [
                    'side' => 'left',
                    'pair_id' => $index + 1,
                    'match_with' => $rightKey,
                ],
            ]);

            $rightOption = Option::create([
                'question_id' => $question->id,
                'option_key' => $rightKey,
                'content' => $this->processRichText($rightContent),
                'order' => $index * 2 + 1,
                'is_correct' => false,
                'metadata' => [
                    'side' => 'right',
                    'pair_id' => $index + 1,
                    'match_with' => $leftKey,
                ],
            ]);

            $this->processPlaceholdersAndAttach($leftOption, $leftContent, $optionsCell['images'], 'option_media');
            $this->processPlaceholdersAndAttach($rightOption, $rightContent, $optionsCell['images'], 'option_media');
        }
    }

    protected function handleSequence(Question $question, array $optionsCell): void
    {
        $items = $this->splitOptions($optionsCell['text']);

        foreach ($items as $index => $itemText) {
            if (preg_match('/^(\d+)\.\s*(.+)$/si', trim($itemText), $matches)) {
                $correctPosition = intval($matches[1]);
                $content = trim($matches[2]);
            } else {
                $correctPosition = $index + 1;
                $content = trim($itemText);
            }

            $option = Option::create([
                'question_id' => $question->id,
                'option_key' => (string)($index + 1),
                'content' => $this->processRichText($content),
                'order' => $index,
                'is_correct' => false,
                'metadata' => [
                    'correct_position' => $correctPosition,
                ],
            ]);

            $this->processPlaceholdersAndAttach($option, $itemText, $optionsCell['images'], 'option_media');
        }
    }

    protected function handleMathInput(Question $question, array $optionsCell, string $keyAnswer): void
    {
        $sanitizedValue = str_replace(',', '.', trim($keyAnswer));
        $numericValue = is_numeric($sanitizedValue) ? (float)$sanitizedValue : 0;

        $option = Option::create([
            'question_id' => $question->id,
            'option_key' => 'NUM',
            'content' => (string)$numericValue,
            'order' => 0,
            'is_correct' => true,
            'metadata' => [
                'tolerance' => 0.01,
                'unit' => null,
                'correct_answer' => $numericValue,
            ],
        ]);

        $this->processPlaceholdersAndAttach($option, $optionsCell['text'], $optionsCell['images'], 'option_media');
    }

    protected function handleShortAnswer(Question $question, string $keyAnswer): void
    {
        Option::create([
            'question_id' => $question->id,
            'option_key' => 'SHORT',
            'content' => $keyAnswer,
            'order' => 0,
            'is_correct' => true,
        ]);
    }

    protected function handleArrangeWords(Question $question, array $optionsCell): void
    {
        $sentence = trim($optionsCell['text']);
        Option::createArrangeWordsOption($question->id, $sentence);
    }

    protected function handleEssay(Question $question, string $rubric): void
    {
        Option::create([
            'question_id' => $question->id,
            'option_key' => 'ESSAY',
            'content' => null, // Content null for essay option strict
            'order' => 0,
            'is_correct' => false,
            'metadata' => [
                'rubric' => $this->processRichText($rubric),
                'expected_answer' => $this->processRichText($rubric),
            ],
        ]);
    }

    protected function splitOptions(string $text): array
    {
        if (empty(trim($text)) || $text === '-') {
            return [];
        }

        $text = preg_replace('/(?<=\S)\s+([A-Z]\.)/i', "\n$1", trim($text));
        $options = preg_split('/\r\n|\r|\n/', $text);

        return array_values(array_filter(array_map('trim', $options)));
    }
}
