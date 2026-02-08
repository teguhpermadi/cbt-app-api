<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classroom extends Model
{
    /** @use HasFactory<\Database\Factories\ClassroomFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'level',
        'user_id',
        'academic_year_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'classroom_users', 'classroom_id', 'user_id')
            ->using(ClassroomUser::class)
            ->withPivot('academic_year_id')
            ->withTimestamps();
    }

    /**
     * Synchronize students for a specific academic year.
     */
    public function syncStudents(array $studentIds, string $academicYearId): void
    {
        $syncData = [];
        foreach ($studentIds as $id) {
            $syncData[$id] = ['academic_year_id' => $academicYearId];
        }

        $this->students()->wherePivot('academic_year_id', $academicYearId)->detach();
        $this->students()->attach($syncData);
    }
}
