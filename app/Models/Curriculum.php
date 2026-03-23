<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

final class Curriculum extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'curricula';

    protected $fillable = [
        'name',
        'code',
        'description',
        'phase',
        'level',
        'grade_range',
        'academic_year',
        'subjects',
        'is_active',
    ];

    protected $casts = [
        'grade_range' => 'array',
        'subjects' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPhase($query, string $phase)
    {
        return $query->where('phase', $phase);
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeByGrade($query, int $grade)
    {
        return $query->where(function ($q) use ($grade) {
            $q->where('grade_range.min', '<=', $grade)
                ->where('grade_range.max', '>=', $grade);
        });
    }

    public function getSubjects(): array
    {
        return $this->subjects ?? [];
    }

    public function getSubjectByCode(string $code): ?array
    {
        $subjects = $this->getSubjects();
        foreach ($subjects as $subject) {
            if ($subject['code'] === $code) {
                return $subject;
            }
        }

        return null;
    }

    public function getLearningOutcomesBySubject(string $subjectCode): array
    {
        $subject = $this->getSubjectByCode($subjectCode);

        return $subject['learning_outcomes'] ?? [];
    }

    public function getLearningObjectivesBySubject(string $subjectCode): array
    {
        $subject = $this->getSubjectByCode($subjectCode);

        return $subject['learning_objectives'] ?? [];
    }
}
