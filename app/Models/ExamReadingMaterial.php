<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamReadingMaterial extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'exam_id',
        'reading_material_id',
        'title',
        'content',
        'media_path',
    ];

    /**
     * Relasi ke Ujian.
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Relasi ke ReadingMaterial asli (opsional, karena ini snapshot).
     */
    public function originalReadingMaterial(): BelongsTo
    {
        return $this->belongsTo(ReadingMaterial::class, 'reading_material_id');
    }

    /**
     * Relasi ke Pertanyaan Ujian yang menggunakan materi ini.
     */
    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }
}
