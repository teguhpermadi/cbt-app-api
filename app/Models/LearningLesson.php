<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningLesson extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'learning_unit_id',
        'question_bank_id',
        'title',
        'content_type',
        'content_data',
        'order',
        'xp_reward',
    ];

    protected $casts = [
        'content_data' => 'json',
    ];

    public function unit()
    {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function progress()
    {
        return $this->hasMany(UserLearningProgress::class);
    }
}
