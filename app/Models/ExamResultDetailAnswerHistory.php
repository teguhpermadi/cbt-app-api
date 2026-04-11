<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExamResultDetailAnswerHistory extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'exam_result_detail_answer_history';

    protected $fillable = [
        'exam_result_detail_id',
        'previous_answer',
        'new_answer',
        'edited_by',
        'edit_reason',
    ];

    protected $casts = [
        'previous_answer' => 'array',
        'new_answer' => 'array',
    ];

    public function examResultDetail(): BelongsTo
    {
        return $this->belongsTo(ExamResultDetail::class, 'exam_result_detail_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
