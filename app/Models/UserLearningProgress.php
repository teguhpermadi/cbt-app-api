<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\LearningLesson;

class UserLearningProgress extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'user_learning_progress';

    protected $fillable = [
        'user_id',
        'learning_lesson_id',
        'status',
        'xp_earned',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(LearningLesson::class, 'learning_lesson_id');
    }
}
