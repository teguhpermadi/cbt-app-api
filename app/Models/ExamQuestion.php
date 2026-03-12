<?php

namespace App\Models;

use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestion extends Model
{
    /** @use HasFactory<\Database\Factories\ExamQuestionFactory> */
    use HasFactory, HasUlids;

    // model ini berfungsi untuk menyimpan snapshot soal yang akan dikerjakan oleh siswa

    protected $fillable = [
        'exam_id',
        'question_id',          // ID soal asli (untuk referensi/analisis)
        'exam_reading_material_id',
        'question_number',      // Nomor urut soal dalam ujian ini
        'content',              // Salinan konten soal
        'options',              // Salinan opsi jawaban (termasuk ULID media)
        'key_answer',           // Salinan kunci jawaban (untuk scoring)
        'score_value',          // Nilai soal dalam ujian ini (bisa berbeda dari soal asli)
        'question_type',        // Tipe soal
        'difficulty_level',     // Level kesulitan soal
        'media_path',           // Added media_path
        'hint',
    ];

    protected $casts = [
        'question_type' => QuestionTypeEnum::class,
        'difficulty_level' => QuestionDifficultyLevelEnum::class,
        'options' => 'array',
        'key_answer' => 'array',
        'score_value' => 'integer',
        'question_number' => 'integer',
    ];

    // --- RELATIONS ---

    /**
     * Relasi ke konfigurasi Ujian.
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Relasi ke snapshot Reading Material.
     */
    public function examReadingMaterial(): BelongsTo
    {
        return $this->belongsTo(ExamReadingMaterial::class);
    }

    /**
     * Relasi ke soal asli di Bank Soal.
     */
    public function originalQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    /**
     * Apply mojibake fix and language tag wrapping to this exam question's content and options.
     * Returns true if anything changed (and saved when $dry is false).
     *
     * @param bool $dry
     * @param bool $verbose
     * @param \Illuminate\Console\Command|null $output
     * @return bool
     */
    public function applyMojibakeConversion(bool $dry = false, bool $verbose = false, $output = null): bool
    {
        $changed = false;

        // Helpers (local copies to avoid coupling)
        $fixMojibake = function (string $text): string {
            if ($text === '') return $text;
            $decoded = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
            if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
                if (substr_count($decoded, '?') === substr_count($text, '?')) {
                    if ($decoded !== $text) return $decoded;
                }
            }
            return $text;
        };

        $wrapLanguageTags = function (string $text): string {
            if ($text === '') return $text;
            /*
            if (strpos($text, '[ara]') === false) {
                $arabicPattern = '/([\p{Arabic}\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]+)/u';
                if (preg_match_all($arabicPattern, $text, $m) && count($m[0]) > 0) {
                    $text = preg_replace($arabicPattern, '[ara]$1[/ara]', $text);
                }
            }
            if (strpos($text, '[jav]') === false) {
                $javanesePattern = '/([\x{A980}-\x{A9DF}]+)/u';
                if (preg_match_all($javanesePattern, $text, $m2) && count($m2[0]) > 0) {
                    $text = preg_replace($javanesePattern, '[jav]$1[/jav]', $text);
                }
            }
            */
            return $text;
        };

        // Process content
        $origContent = $this->content ?? '';
        $fixedContent = $fixMojibake($origContent);
        $wrappedContent = $wrapLanguageTags($fixedContent);
        if ($wrappedContent !== $origContent) {
            $changed = true;
            if (!$dry) $this->content = $wrappedContent;
        }

        // Process hint
        $origHint = $this->hint ?? '';
        $fixedHint = $fixMojibake($origHint);
        if ($fixedHint !== $origHint) {
            $changed = true;
            if (!$dry) $this->hint = $fixedHint;
        }

        // Process options array (recursive)
        $origOptions = $this->options ?? [];
        $convertedOptions = $this->convertArrayStrings($origOptions, $fixMojibake, $wrapLanguageTags);
        if ($convertedOptions !== $origOptions) {
            $changed = true;
            if (!$dry) $this->options = $convertedOptions;
        }

        // Process key_answer array (recursive)
        $origKey = $this->key_answer ?? [];
        $convertedKey = $this->convertArrayStrings($origKey, $fixMojibake, $wrapLanguageTags);
        if ($convertedKey !== $origKey) {
            $changed = true;
            if (!$dry) $this->key_answer = $convertedKey;
        }

        if ($changed && $verbose && $output) {
            $output->line("ExamQuestion {$this->id}: content/hint/options/key_answer updated");
        }

        if ($changed && !$dry) {
            $this->save();
        }

        return $changed;
    }

    /**
     * Recursively convert all string values in array using provided callbacks.
     *
     * @param array $arr
     * @param callable $fixFn
     * @param callable $wrapFn
     * @return array
     */
    protected function convertArrayStrings(array $arr, callable $fixFn, callable $wrapFn): array
    {
        $result = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $result[$k] = $this->convertArrayStrings($v, $fixFn, $wrapFn);
            } elseif (is_string($v)) {
                $fixed = $fixFn($v);
                $wrapped = $wrapFn($fixed);
                $result[$k] = $wrapped;
            } else {
                $result[$k] = $v;
            }
        }
        return $result;
    }
}
