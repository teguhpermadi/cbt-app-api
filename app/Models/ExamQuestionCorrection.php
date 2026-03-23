<?php

namespace App\Models;

use App\Enums\CorrectionStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestionCorrection extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'exam_id',
        'exam_question_id',
        'status',
        'total_to_correct',
        'corrected_count',
        'last_error',
    ];

    protected $casts = [
        'status' => CorrectionStatusEnum::class,
        'total_to_correct' => 'integer',
        'corrected_count' => 'integer',
    ];

    /**
     * Relasi ke Ujian.
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Relasi ke Snapshot Soal.
     */
    public function examQuestion(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class);
    }
}
