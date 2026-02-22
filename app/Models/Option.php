<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Option extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\OptionFactory> */
    use HasFactory, HasUlids, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'question_id',
        'option_key',       // A, B, C, D atau 1, 2, 3
        'content',          // Teks konten opsi
        'order',            // Urutan tampilan
        'is_correct',       // Penanda jawaban benar
        'metadata',         // Data tambahan per tipe soal
    ];

    protected $casts = [
        'order' => 'integer',
        'is_correct' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Relasi ke Question
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    // --- SCOPES ---

    /**
     * Scope untuk mendapatkan opsi yang benar
     */
    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }

    /**
     * Scope untuk mengurutkan berdasarkan order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Scope untuk mendapatkan opsi berdasarkan key
     */
    public function scopeByKey($query, string $key)
    {
        return $query->where('option_key', $key);
    }

    // --- HELPER METHODS ---

    /**
     * Cek apakah opsi ini adalah jawaban benar
     */
    public function isCorrect(): bool
    {
        return $this->is_correct;
    }

    /**
     * Set opsi sebagai jawaban benar
     */
    public function markAsCorrect(): self
    {
        $this->update(['is_correct' => true]);
        return $this;
    }

    /**
     * Set opsi sebagai jawaban salah
     */
    public function markAsIncorrect(): self
    {
        $this->update(['is_correct' => false]);
        return $this;
    }

    /**
     * Get metadata value by key
     */
    public function getMetadata(string $key, $default = null)
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set metadata value by key
     */
    public function setMetadata(string $key, $value): self
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->update(['metadata' => $metadata]);
        return $this;
    }

    /**
     * Cek apakah opsi memiliki media
     */
    public function hasOptionMedia(): bool
    {
        return $this->getMedia('option_media')->isNotEmpty();
    }

    /**
     * Get URL media opsi
     */
    public function getMediaUrl(): ?string
    {
        // Prioritas: Spatie Media Library, lalu media_path
        $media = $this->getFirstMedia('option_media');
        if ($media) {
            return $media->getUrl();
        }

        return null;
    }

    // --- SPATIE MEDIA LIBRARY ---

    /**
     * Register media collections untuk opsi
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('option_media')
            ->singleFile() // Satu media per opsi
            ->useDisk('public');
    }

    // --- STATIC HELPERS ---

    /**
     * Create multiple options untuk Multiple Choice / Multiple Selection
     * 
     * @param string $questionId
     * @param array $options Format: [['key' => 'A', 'content' => '...', 'is_correct' => true], ...]
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function createMultipleChoiceOptions(string $questionId, array $options)
    {
        $createdOptions = collect();

        foreach ($options as $index => $option) {
            $createdOptions->push(self::create([
                'question_id' => $questionId,
                'option_key' => $option['option_key'] ?? $option['key'] ?? chr(65 + $index), // A, B, C, D...
                'content' => $option['content'] ?? '',
                'order' => $option['order'] ?? $index,
                'is_correct' => $option['is_correct'] ?? false,
            ]));
        }

        return $createdOptions;
    }

    /**
     * Create options untuk True/False
     * 
     * @param string $questionId
     * @param bool $correctAnswer
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function createTrueFalseOptions(string $questionId, bool $correctAnswer)
    {
        return collect([
            self::create([
                'question_id' => $questionId,
                'option_key' => 'T',
                'content' => 'Benar',
                'order' => 0,
                'is_correct' => $correctAnswer === true,
            ]),
            self::create([
                'question_id' => $questionId,
                'option_key' => 'F',
                'content' => 'Salah',
                'order' => 1,
                'is_correct' => $correctAnswer === false,
            ]),
        ]);
    }

    /**
     * Create options untuk Matching
     * 
     * @param string $questionId
     * @param array $pairs Format: [['left' => '...', 'right' => '...'], ...]
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function createMatchingOptions(string $questionId, array $pairs)
    {
        $createdOptions = collect();

        foreach ($pairs as $index => $pair) {
            // Create left side option
            $createdOptions->push(self::create([
                'question_id' => $questionId,
                'option_key' => 'L' . ($index + 1),
                'content' => $pair['left'],
                'order' => $index * 2,
                'metadata' => [
                    'side' => 'left',
                    'pair_id' => $index + 1,
                    'match_with' => 'R' . ($index + 1),
                ],
            ]));

            // Create right side option
            $createdOptions->push(self::create([
                'question_id' => $questionId,
                'option_key' => 'R' . ($index + 1),
                'content' => $pair['right'],
                'order' => $index * 2 + 1,
                'metadata' => [
                    'side' => 'right',
                    'pair_id' => $index + 1,
                    'match_with' => 'L' . ($index + 1),
                ],
            ]));
        }

        return $createdOptions;
    }

    /**
     * Create options untuk Ordering
     * 
     * @param string $questionId
     * @param array $items Format: ['Item 1', 'Item 2', 'Item 3']
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function createOrderingOptions(string $questionId, array $items)
    {
        $createdOptions = collect();

        foreach ($items as $index => $item) {
            $createdOptions->push(self::create([
                'question_id' => $questionId,
                'option_key' => (string)($index + 1),
                'content' => $item,
                'order' => $index,
                'metadata' => [
                    'correct_position' => $index + 1,
                ],
            ]));
        }

        return $createdOptions;
    }

    /**
     * Create option untuk Numerical Input
     * 
     * @param string|float $correctAnswer
     * @return Option
     */
    public static function createNumericalInputOption(string $questionId, string|float $correctAnswer)
    {
        return self::create([
            'question_id' => $questionId,
            'option_key' => 'NUM',
            'content' => (string)$correctAnswer,
            'order' => 0,
            'is_correct' => true,
            'metadata' => [
                'correct_answer' => $correctAnswer,
            ],
        ]);
    }

    /**
     * Create option untuk Arrange Words
     *
     * @param string $questionId
     * @param string $sentence Kalimat lengkap
     * @param string $delimiter Delimiter pemisah kata
     * @return Option
     */
    public static function createArrangeWordsOption(string $questionId, string $sentence, string $delimiter = ' ')
    {
        return self::create([
            'question_id' => $questionId,
            'option_key' => 'SENTENCE',
            'content' => $sentence,
            'order' => 0,
            'is_correct' => true,
            'metadata' => [
                'delimiter' => $delimiter,
            ],
        ]);
    }
    /**
     * Create options untuk Short Answer
     * 
     * @param string $questionId
     * @param array $answers Format: [['content' => '...'], ...] atau ['jawaban1', 'jawaban2', ...]
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function createShortAnswerOptions(string $questionId, array $answers)
    {
        $createdOptions = collect();

        foreach ($answers as $index => $answer) {
            $createdOptions->push(self::create([
                'question_id' => $questionId,
                'option_key'  => 'SA' . ($index + 1),
                'content'     => is_array($answer) ? ($answer['content'] ?? '') : $answer,
                'order'       => $index,
                'is_correct'  => true,
            ]));
        }

        return $createdOptions;
    }

    /**
     * Create option untuk Essay (Rubrik)
     *
     * @param string $questionId
     * @param string $rubric Konten rubrik / kunci jawaban
     * @return Option
     */
    public static function createEssayOption(string $questionId, string $rubric)
    {
        return self::create([
            'question_id' => $questionId,
            'option_key' => 'ESSAY',
            'content' => $rubric,
            'order' => 0,
            'is_correct' => true, // Tidak digunakan untuk auto-grading biasa, tapi sebagai penanda
            'metadata' => [
                'type' => 'rubric',
            ],
        ]);
    }
    /**
     * Create option untuk Math Input
     *
     * @param string $questionId
     * @param string $latex Jawaban dalam format LaTeX
     * @return Option
     */
    public static function createMathInputOption(string $questionId, string $latex)
    {
        return self::create([
            'question_id' => $questionId,
            'option_key' => 'MATH',
            'content' => $latex,
            'order' => 0,
            'is_correct' => true,
            'metadata' => [
                'correct_answer' => $latex,
            ],
        ]);
    }
    /**
     * Create option untuk Arabic Input
     *
     * @param string $questionId
     * @param string $arabic Jawaban dalam bahasa Arab
     * @return Option
     */
    public static function createArabicOption(string $questionId, string $arabic)
    {
        return self::create([
            'question_id' => $questionId,
            'option_key' => 'ARABIC',
            'content' => $arabic,
            'order' => 0,
            'is_correct' => true,
            'metadata' => [
                'correct_answer' => $arabic,
            ],
        ]);
    }
}
