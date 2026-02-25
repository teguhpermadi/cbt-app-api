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
        'extra_time',
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
        'extra_time' => 'integer',
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

    /**
     * Get data for real-time broadcasting.
     */
    public function getBroadcastData()
    {
        $user = $this->user;
        $exam = $this->exam;

        $classroom = $exam->classrooms()
            ->whereHas('students', fn($q) => $q->where('users.id', $this->user_id))
            ->first();

        $currentScore = $this->is_finished
            ? (float) $this->total_score
            : (float) $this->examResultDetails()->sum('score_earned');

        return [
            'id' => $user->id,
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'classroom' => $classroom?->name ?? 'N/A',
            ],
            'status' => $this->is_finished ? 'finished' : 'in_progress',
            'score' => $currentScore,
            'progress' => [
                'answered' => (int) $this->examResultDetails()->whereNotNull('student_answer')->count(),
                'total' => (int) $this->examResultDetails()->count(),
            ],
            'remaining_time' => (int) $this->getRemainingSeconds(),
        ];
    }

    /**
     * Calculate remaining seconds for this session.
     */
    public function getRemainingSeconds(): int
    {
        $now = now();
        $startTime = \Carbon\Carbon::parse($this->start_time);
        $exam = $this->exam;
        $duration = $exam->duration + ($this->extra_time ?? 0);
        $endTimeByDuration = $startTime->copy()->addMinutes($duration);
        $hardEndTime = $exam->end_time ? \Carbon\Carbon::parse($exam->end_time) : null;
        $realEndTime = $hardEndTime ? $endTimeByDuration->min($hardEndTime) : $endTimeByDuration;

        return $now->greaterThan($realEndTime) ? 0 : $now->diffInSeconds($realEndTime);
    }
}
