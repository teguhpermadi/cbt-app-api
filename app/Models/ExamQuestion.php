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

    protected $fillable = [
        'exam_id',
        'question_id',          // ID soal asli (untuk referensi/analisis)
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
     * Relasi ke soal asli di Bank Soal.
     */
    public function originalQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
