<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResultDetail extends Model
{
    /** @use HasFactory<\Database\Factories\ExamResultDetailFactory> */
    use HasFactory, HasUlids;

    // model ini berfungsi untuk menyimpan jawaban siswa per soal dalam satu sesi ujian. 
    protected $fillable = [
        'exam_session_id',      // ID Sesi Pengerjaan (BARU)
        'exam_question_id',     // ID Soal Transaksional (Salinan Soal)
        'student_answer',       // Jawaban siswa (JSON untuk MC/MS, string untuk Essay/Input)
        'is_correct',           // Hasil koreksi otomatis (True/False/Null untuk Essay)
        'score_earned',         // Skor yang didapat untuk soal ini
        'correction_notes',     // Catatan/Feedback dari guru untuk soal Essay
        'answered_at',          // Waktu soal ini dijawab/diubah terakhir kali
        'time_spent',           // Waktu yang dihabiskan untuk soal ini (dalam detik)
        'question_number',      // Nomor urut soal dalam sesi (randomized order)
        'is_flagged',           // Status ragu-ragu
    ];

    protected $casts = [
        'student_answer' => 'array',  // Cast ke array agar otomatis di-serialize ke JSON
        'is_correct' => 'boolean',
        'score_earned' => 'float',
        'answered_at' => 'datetime',
        'time_spent' => 'integer',
        'question_number' => 'integer',
        'is_flagged' => 'boolean',
    ];

    // --- RELATIONS ---

    /**
     * Relasi ke Sesi Pengerjaan (ExamSession).
     */
    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    /**
     * Relasi ke Soal Transaksional (ExamQuestion).
     */
    public function examQuestion(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'exam_question_id');
    }
}
