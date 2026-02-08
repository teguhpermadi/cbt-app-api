<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassroomUser extends Pivot
{
    /** @use HasFactory<\Database\Factories\ClassroomUserFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'classroom_id',
        'user_id',
        'academic_year_id',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
