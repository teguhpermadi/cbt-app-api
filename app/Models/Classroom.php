<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Classroom extends Model
{
    /** @use HasFactory<\Database\Factories\ClassroomFactory> */
    use HasFactory, HasUlids, SoftDeletes, LogsActivity;

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
     * Scope a query to only include classrooms of the authenticated user.
     */
    public function scopeMine($query)
    {
        $userId = auth()->id();

        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhereHas('students', function ($sq) use ($userId) {
                    $sq->where('users.id', $userId);
                });
        });
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

        $this->students()->sync($syncData);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'level', 'academic_year_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Classroom has been {$eventName}");
    }
}
