<?php

namespace App\Models;

use App\Enums\ExamTimerTypeEnum;
use App\Enums\ExamTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Exam extends Model
{
    /** @use HasFactory<\Database\Factories\ExamFactory> */
    use HasFactory, HasUlids, SoftDeletes, LogsActivity;

    protected $fillable = [
        'academic_year_id',
        'subject_id',
        'user_id',          // Guru yang membuat ujian
        'question_bank_id', // Bank soal yang digunakan (opsional)
        'title',
        'type',        // Tipe ujian (Harian, UTS, UAS, dll.)
        'duration',         // Durasi ujian dalam menit
        'token',            // Token ujian
        'is_token_visible', // Status visibilitas token
        'is_published',     // Status ujian: draft/terbit
        'is_randomized_question',    // Apakah urutan soal diacak
        'is_randomized_answer', // Apakah urutan jawaban diacak
        'is_show_result', // Apakah hasil ditampilkan setelah selesai
        'is_visible_hint', // Apakah hint ditampilkan
        'max_attempts',     // Jumlah maksimal upaya siswa (null = unlimited)
        'timer_type',       // Jenis timer: strict/flexible
        'passing_score',    // Nilai minimum kelulusan
        'start_time',       // Waktu mulai ujian
        'end_time',         // Waktu berakhir ujian
    ];

    protected $casts = [
        'type' => ExamTypeEnum::class,
        'duration' => 'integer',
        'is_token_visible' => 'boolean',
        'is_published' => 'boolean',
        'is_randomized_question' => 'boolean',
        'is_randomized_answer' => 'boolean',
        'is_show_result' => 'boolean',
        'is_visible_hint' => 'boolean',
        'max_attempts' => 'integer',
        'timer_type' => ExamTimerTypeEnum::class,
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function examResults()
    {
        return $this->hasMany(ExamResult::class);
    }

    public function examSessions()
    {
        return $this->hasMany(ExamSession::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'type', 'is_published', 'duration', 'passing_score', 'start_time', 'end_time'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Exam has been {$eventName}");
    }
}
