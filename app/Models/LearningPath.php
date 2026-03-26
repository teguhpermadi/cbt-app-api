<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class LearningPath extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'subject_id',
        'classroom_id',
        'user_id',
        'title',
        'description',
        'order',
        'is_published',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function units()
    {
        return $this->hasMany(LearningUnit::class)->orderBy('order');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeBySubjectAndClassroom($query, string $subjectId, string $classroomId)
    {
        return $query->where('subject_id', $subjectId)
            ->where('classroom_id', $classroomId)
            ->ordered();
    }
}
