<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningUnit extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'learning_path_id',
        'title',
        'order',
        'xp_reward',
    ];

    public function learningPath()
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function lessons()
    {
        return $this->hasMany(LearningLesson::class)->orderBy('order');
    }
}
