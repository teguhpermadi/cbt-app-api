<?php

namespace App\Models;

use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionScoreEnum;
use App\Enums\QuestionTimeEnum;
use App\Enums\QuestionTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class Question extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\QuestionFactory> */
    use HasFactory, HasUlids, SoftDeletes, HasTags, InteractsWithMedia;

    /**
     * Flag to skip automatic option creation in factory.
     * @var bool
     */
    public bool $_skip_options = false;

    protected $fillable = [
        'user_id',
        'reading_material_id',
        'type',
        'difficulty',
        'timer',
        'content',
        'score',
        'hint',
        'order',
        'is_approved',
    ];

    protected $casts = [
        'type' => QuestionTypeEnum::class,
        'difficulty' => QuestionDifficultyLevelEnum::class,
        'timer' => QuestionTimeEnum::class,
        'score' => QuestionScoreEnum::class,
        'order' => 'integer',
        'is_approved' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('order');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(QuestionSuggestion::class);
    }

    public function readingMaterial()
    {
        return $this->belongsTo(ReadingMaterial::class);
    }

    public function questionBanks(): BelongsToMany
    {
        return $this->belongsToMany(QuestionBank::class, 'list_question_of_question_bank', 'question_id', 'question_bank_id');
    }

    /**
     * Konfigurasi untuk Media Library (Media di Soal)
     */
    public function registerMediaCollections(): void
    {
        // Koleksi untuk media yang muncul di Konten Soal (gambar, audio, video)
        $this->addMediaCollection('question_content')
            ->useDisk('public');
    }

    // --- HELPER METHODS FOR EXAM SNAPSHOT ---

    /**
     * Mengambil opsi jawaban dalam format array untuk snapshot ExamQuestion.
     * Mengkonversi dari relasi options ke format array yang kompatibel dengan JSON.
     */
    public function getOptionsForExam(): array
    {
        $options = [];

        // Eager load options jika belum di-load
        if (!$this->relationLoaded('options')) {
            $this->load('options');
        }

        foreach ($this->options as $option) {
            $mediaUrl = $option->getFirstMediaUrl('option_media');

            $optionData = [
                'id' => $option->id, // Sertakan ID asli untuk referensi
                'key' => $option->option_key,
                'content' => $option->content,
                'type' => $option->getMetadata('type'), // Untuk matching (left/right)
            ];

            // Tambahkan metadata khusus jika ada
            if ($option->metadata) {
                $optionData = array_merge($optionData, $option->metadata);
            }

            // Format khusus berdasarkan tipe soal
            if ($this->question_type === QuestionTypeEnum::MATCHING) {
                // Untuk matching, kita butuh struktur yang jelas antara left dan right
                // Tapi untuk snapshot, kita simpan flat array dengan key L1, R1, dst.
                // Nanti di frontend yang akan memisahkan
            }

            $options[$option->option_key] = $optionData;
        }

        return $options;
    }

    /**
     * Mengambil kunci jawaban dalam format array untuk snapshot ExamQuestion.
     * Mengkonversi dari relasi options ke format array yang kompatibel dengan JSON.
     */
    public function getKeyAnswerForExam(): array
    {
        // Eager load options jika belum di-load
        if (!$this->relationLoaded('options')) {
            $this->load('options');
        }

        return match ($this->type) {
            QuestionTypeEnum::MULTIPLE_CHOICE => [
                'answer' => $this->options->where('is_correct', true)->first()?->option_key
            ],

            QuestionTypeEnum::MULTIPLE_SELECTION => [
                'answers' => $this->options->where('is_correct', true)->pluck('option_key')->values()->toArray()
            ],

            QuestionTypeEnum::TRUE_FALSE => [
                'answer' => $this->options->where('is_correct', true)->first()?->option_key
            ],

            QuestionTypeEnum::MATCHING => [
                'pairs' => $this->options->filter(function ($option) {
                    return str_starts_with($option->option_key, 'L');
                })
                    ->mapWithKeys(function ($option) {
                        return [$option->option_key => $option->getMetadata('match_with')];
                    })->toArray()
            ],

            QuestionTypeEnum::SEQUENCE => [
                'order' => $this->options->sortBy(function ($option) {
                    return $option->getMetadata('correct_position');
                })->pluck('option_key')->values()->toArray()
            ],

            QuestionTypeEnum::MATH_INPUT => [
                'answer' => $this->options->first()?->getMetadata('correct_answer'),
                'tolerance' => $this->options->first()?->getMetadata('tolerance', 0),
                'unit' => $this->options->first()?->getMetadata('unit'),
            ],

            QuestionTypeEnum::ESSAY => [
                // Essay mungkin punya rubrik di metadata opsi atau null
                'rubric' => $this->options->first()?->metadata
            ],

            QuestionTypeEnum::ARRANGE_WORDS => [
                'words' => ($opt = $this->options->first())
                    ? explode($opt->getMetadata('delimiter', ' '), $opt->content)
                    : []
            ],

            default => []
        };
    }
}
