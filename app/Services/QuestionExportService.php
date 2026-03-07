<?php

namespace App\Services;

use App\Enums\QuestionTypeEnum;
use App\Models\QuestionBank;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\JcTable;

class QuestionExportService
{
    // Column widths (twips) for label | value
    private const LABEL_WIDTH = 2200;
    private const VALUE_WIDTH = 9000;

    // Label cell style
    private const LABEL_STYLE = ['bgColor' => 'F2F2F2'];
    private const LABEL_FONT  = ['bold' => true, 'size' => 10];
    private const VALUE_FONT  = ['size' => 10];

    /**
     * Generate Word document containing questions from a QuestionBank
     */
    public function exportToWord(QuestionBank $questionBank): string
    {
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        Settings::setTempDir($tempDir);
        ini_set('memory_limit', '512M');

        $phpWord = new PhpWord();

        $properties = $phpWord->getDocInfo();
        $properties->setCreator('CBT App');
        $properties->setTitle('Export Soal - ' . $questionBank->name);

        $section = $phpWord->addSection([
            'orientation' => 'portrait',
            'marginLeft'  => 1000,
            'marginRight' => 1000,
            'marginTop'   => 1000,
            'marginBottom' => 1000,
        ]);

        // Title
        $section->addText(
            $questionBank->name,
            ['size' => 16, 'bold' => true],
            ['alignment' => 'center']
        );
        $section->addTextBreak(1);

        // Load relations
        $questionBank->load([
            'questions.tags',
            'questions.options',
            'questions.media',
            'questions.options.media',
        ]);

        $tableStyle = [
            'borderSize'  => 4,
            'borderColor' => 'AAAAAA',
            'cellMargin'  => 80,
            'width'       => 100 * 50,
        ];
        $phpWord->addTableStyle('QuestionTable', $tableStyle);

        $no = 1;
        foreach ($questionBank->questions as $question) {
            // Question number header
            $section->addText(
                'Soal #' . $no,
                ['bold' => true, 'size' => 11, 'color' => '333333'],
                ['spaceAfter' => 60]
            );

            $table = $section->addTable('QuestionTable');

            // Row: type
            $this->addLabelRow($table, 'type', $this->mapQuestionType($question->type));

            // Row: score
            $this->addLabelRow($table, 'score', (string)($question->score?->value ?? 10));

            // Row: question
            $questionRow = $table->addRow();
            $questionRow->addCell(self::LABEL_WIDTH, self::LABEL_STYLE)
                ->addText('question', self::LABEL_FONT);
            $questionCell = $questionRow->addCell(self::VALUE_WIDTH);
            $this->addHtmlToCell($questionCell, $question->content);
            $this->addMediaToCell($questionCell, $question->getMedia('question_content'));

            // Row: option
            $optionRow = $table->addRow();
            $optionRow->addCell(self::LABEL_WIDTH, self::LABEL_STYLE)
                ->addText('option', self::LABEL_FONT);
            $optionCell = $optionRow->addCell(self::VALUE_WIDTH);
            $this->addOptionsToCell($question, $optionCell);

            // Row: key
            $keyRow = $table->addRow();
            $keyRow->addCell(self::LABEL_WIDTH, self::LABEL_STYLE)
                ->addText('key', self::LABEL_FONT);
            $keyCell = $keyRow->addCell(self::VALUE_WIDTH);
            $this->addKeyToCell($question, $keyCell);

            // Row: hint (if exists)
            if (!empty($question->hint)) {
                $this->addLabelRow($table, 'hint', $this->sanitizeText($question->hint));
            }

            // Row: tags (if exists)
            $tags = $question->tags->pluck('name')->implode(', ');
            if (!empty($tags)) {
                $this->addLabelRow($table, 'tags', $this->sanitizeText($tags));
            }

            $section->addTextBreak(1);
            $no++;
        }

        // Save file
        $fileName  = 'export_soal_' . $questionBank->id . '_' . time() . '.docx';
        $exportDir = storage_path('app/exports');
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $filePath  = $exportDir . '/' . $fileName;
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($filePath);

        if (file_exists($filePath)) {
            Log::info("Word exported: $filePath (" . filesize($filePath) . " bytes)");
        } else {
            Log::error("Failed to save Word: $filePath");
        }

        return $filePath;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Add a simple label | text-value row
     */
    private function addLabelRow($table, string $label, string $value): void
    {
        $row = $table->addRow();
        $row->addCell(self::LABEL_WIDTH, self::LABEL_STYLE)
            ->addText($label, self::LABEL_FONT);
        $row->addCell(self::VALUE_WIDTH)
            ->addText($value, self::VALUE_FONT);
    }

    /**
     * Add options into a single cell using separate paragraphs (addText)
     * so each option appears on a new line.
     */
    private function addOptionsToCell($question, $cell): void
    {
        $options = $question->options;
        $type    = $question->type;

        if ($type === QuestionTypeEnum::MULTIPLE_CHOICE || $type === QuestionTypeEnum::MULTIPLE_SELECTION) {
            foreach ($options as $opt) {
                // Each option as a new paragraph
                $cell->addText($this->sanitizeText($opt->content), self::VALUE_FONT);
                $this->addMediaToCell($cell, $opt->getMedia('option_media'));
            }
        } elseif ($type === QuestionTypeEnum::TRUE_FALSE) {
            $cell->addText('TRUE', self::VALUE_FONT);
            $cell->addText('FALSE', self::VALUE_FONT);
        } elseif ($type === QuestionTypeEnum::MATCHING) {
            $leftSide  = $options->filter(fn($o) => str_starts_with($o->option_key, 'L'));
            $rightSide = $options->filter(fn($o) => str_starts_with($o->option_key, 'R'));
            foreach ($leftSide as $leftOpt) {
                $rightOpt  = $rightSide->where('option_key', $leftOpt->getMetadata('match_with'))->first();
                $leftText  = $this->sanitizeText($leftOpt->content);
                $rightText = $rightOpt ? $this->sanitizeText($rightOpt->content) : '';
                // Format as left::right (no quotes)
                $cell->addText($leftText . '::' . $rightText, self::VALUE_FONT);

                // Add media for both sides if they exist
                $this->addMediaToCell($cell, $leftOpt->getMedia('option_media'));
                if ($rightOpt) {
                    $this->addMediaToCell($cell, $rightOpt->getMedia('option_media'));
                }
            }
        } elseif ($type === QuestionTypeEnum::SEQUENCE) {
            $sorted = $options->sortBy(fn($o) => $o->getMetadata('correct_position'));
            foreach ($sorted as $opt) {
                // Each sorted option as a new paragraph
                $cell->addText($this->sanitizeText($opt->content), self::VALUE_FONT);
                $this->addMediaToCell($cell, $opt->getMedia('option_media'));
            }
        } else {
            // ESSAY, SHORT_ANSWER, MATH_INPUT — no options to list
            $cell->addText('-', self::VALUE_FONT);
        }
    }

    /**
     * Add the correct key/answer into a cell.
     */
    private function addKeyToCell($question, $cell): void
    {
        $options = $question->options;
        $type    = $question->type;

        if ($type === QuestionTypeEnum::MULTIPLE_CHOICE) {
            $key = $options->where('is_correct', true)->pluck('option_key')->implode(', ');
            $cell->addText($key ?: '-', self::VALUE_FONT);
        } elseif ($type === QuestionTypeEnum::MULTIPLE_SELECTION) {
            $keys = $options->where('is_correct', true)->pluck('option_key')->sort()->implode(', ');
            $cell->addText($keys ?: '-', self::VALUE_FONT);
        } elseif ($type === QuestionTypeEnum::TRUE_FALSE) {
            $correct = $options->where('is_correct', true)->first();
            $cell->addText($correct ? ($correct->option_key === 'T' ? 'TRUE' : 'FALSE') : '-', self::VALUE_FONT);
        } elseif ($type === QuestionTypeEnum::MATCHING || $type === QuestionTypeEnum::SEQUENCE) {
            // EMPTY as requested
            $cell->addText('', self::VALUE_FONT);
        } elseif ($type === QuestionTypeEnum::SHORT_ANSWER) {
            $answers = $options->pluck('content')->map(fn($c) => $this->sanitizeText($c))->filter();
            if ($answers->isEmpty()) {
                $cell->addText('-', self::VALUE_FONT);
            } else {
                foreach ($answers as $ans) {
                    $cell->addText($ans, self::VALUE_FONT);
                }
            }
        } elseif ($type === QuestionTypeEnum::ESSAY) {
            $rubric = $options->first();
            $cell->addText($rubric ? $this->sanitizeText($rubric->content) : '-', self::VALUE_FONT);
        } elseif ($type === QuestionTypeEnum::MATH_INPUT) {
            $ans = $options->first();
            if ($ans) {
                $text = $ans->getMetadata('correct_answer') ?? $ans->content;
                $cell->addText($this->sanitizeText($text), self::VALUE_FONT);
            } else {
                $cell->addText('-', self::VALUE_FONT);
            }
        } elseif ($type === QuestionTypeEnum::ARABIC_RESPONSE || $type === QuestionTypeEnum::JAVANESE_RESPONSE) {
            $ans = $options->first();
            $cell->addText($ans ? $this->sanitizeText($ans->content) : '-', self::VALUE_FONT);
        } else {
            $cell->addText('-', self::VALUE_FONT);
        }
    }

    private function mapQuestionType(QuestionTypeEnum $type): string
    {
        return match ($type) {
            QuestionTypeEnum::MULTIPLE_CHOICE   => '1',
            QuestionTypeEnum::MULTIPLE_SELECTION => '2',
            QuestionTypeEnum::TRUE_FALSE        => '3',
            QuestionTypeEnum::SHORT_ANSWER      => '4',
            QuestionTypeEnum::ESSAY             => '5',
            QuestionTypeEnum::MATH_INPUT        => '6',
            QuestionTypeEnum::SEQUENCE          => '7',
            QuestionTypeEnum::ARABIC_RESPONSE   => '8',
            QuestionTypeEnum::JAVANESE_RESPONSE => '9',
            QuestionTypeEnum::MATCHING          => '10',
            default                             => $type->name,
        };
    }

    private function sanitizeText(?string $text): string
    {
        if (empty($text)) return '';
        $text = str_replace(['[ara]', '[/ara]', '[jav]', '[/jav]'], '', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        return trim($text);
    }

    private function addHtmlToCell($cell, ?string $html): void
    {
        if (empty($html)) return;

        $text = str_replace(['[ara]', '[/ara]', '[jav]', '[/jav]'], '', $html);
        $text = str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>', '</div>'], "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $cell->addText($line, self::VALUE_FONT);
            }
        }
    }

    private function addMediaToCell($cell, $mediaItems): void
    {
        foreach ($mediaItems as $media) {
            $path = $media->getPath();
            if (file_exists($path) && !is_dir($path)) {
                try {
                    $imageInfo = @getimagesize($path);
                    if ($imageInfo !== false) {
                        $cell->addImage($path, ['width' => 200, 'height' => 200, 'ratio' => true]);
                    }
                } catch (\Exception $e) {
                    Log::error('Cannot add image to Word: ' . $e->getMessage());
                }
            }
        }
    }
}
