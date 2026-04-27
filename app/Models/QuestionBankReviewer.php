<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionBankReviewStateEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class QuestionBankReviewer extends Model
{
    /** @use HasFactory<\Database\Factories\QuestionBankReviewerFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'question_bank_id',
        'user_id',
        'state',
        'rating',
        'notes',
        'suggested_questions_count',
    ];

    protected $casts = [
        'state' => QuestionBankReviewStateEnum::class,
        'rating' => 'integer',
        'suggested_questions_count' => 'integer',
    ];

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query)
    {
        return $query->whereHas('questionBank', function ($q) {
            $q->forUser();
        });
    }

    public function scopeMine($query)
    {
        return $query->whereHas('questionBank', function ($q) {
            $q->mine();
        });
    }

    public function scopePublic($query)
    {
        return $query->whereHas('questionBank', function ($q) {
            $q->public();
        });
    }
}
