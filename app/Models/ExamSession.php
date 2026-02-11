<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamSession extends Model
{
    /** @use HasFactory<\Database\Factories\ExamSessionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'exam_id',
        'user_id',             // ID Siswa
        'attempt_number',      // Percobaan ke berapa (1, 2, 3, ...)
        'total_score',         // Skor yang didapat di sesi ini
        'total_max_score',     // Skor maksimal yang bisa didapat di sesi ini
        'is_finished',         // Sudah selesai atau masih berlangsung
        'is_corrected',        // Status koreksi (untuk soal Essay)
        'start_time',          // Waktu mulai pengerjaan
        'finish_time',         // Waktu selesai pengerjaan
        'duration_taken',      // Durasi pengerjaan dalam menit
        'ip_address',
    ];

    protected $casts = [
        'total_score' => 'float',
        'total_max_score' => 'float',
        'is_finished' => 'boolean',
        'is_corrected' => 'boolean',
        'start_time' => 'datetime',
        'finish_time' => 'datetime',
        'duration_taken' => 'integer',
        'attempt_number' => 'integer',
    ];

    protected $appends = ['final_score'];

    public function getFinalScoreAttribute()
    {
        if ($this->total_max_score && $this->total_max_score > 0) {
            return round(($this->total_score / $this->total_max_score) * 100);
        }
        return 0;
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function examResultDetails()
    {
        return $this->hasMany(ExamResultDetail::class, 'exam_session_id');
    }

    public function examResult()
    {
        return $this->hasOne(ExamResult::class, 'exam_session_id');
    }
}
