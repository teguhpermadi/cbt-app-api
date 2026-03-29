<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LearningContentTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

final class LearningLesson extends Model implements HasMedia
{
    use HasFactory, HasUlids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'learning_unit_id',
        'question_bank_id',
        'title',
        'content_type',
        'content_data',
        'order',
        'xp_reward',
        'is_published',
    ];

    protected $casts = [
        'content_data' => 'json',
        'content_type' => LearningContentTypeEnum::class,
        'is_published' => 'boolean',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function unit()
    {
        return $this->belongsTo(LearningUnit::class, 'learning_unit_id');
    }

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function progress()
    {
        return $this->hasMany(UserLearningProgress::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('reading_files')
            ->acceptsMimeTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
            ->useDisk('public');

        $this->addMediaCollection('videos')
            ->acceptsMimeTypes(['video/mp4', 'video/quicktime'])
            ->useDisk('public');

        $this->addMediaCollection('audios')
            ->acceptsMimeTypes(['audio/mpeg', 'audio/wav', 'audio/mp3'])
            ->useDisk('public');
    }

    public function getContentDataAttribute(?array $value): array
    {
        return $value ?? [];
    }

    public function getHtmlContentAttribute(): ?string
    {
        return $this->content_data['html_content'] ?? null;
    }

    public function setHtmlContentAttribute(?string $value): void
    {
        $data = $this->content_data;
        if ($value !== null) {
            $data['html_content'] = $value;
        } else {
            unset($data['html_content']);
        }
        $this->attributes['content_data'] = $data;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->content_data['description'] ?? null;
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $data = $this->content_data;
        if ($value !== null) {
            $data['description'] = $value;
        } else {
            unset($data['description']);
        }
        $this->attributes['content_data'] = $data;
    }

    public function getYoutubeUrlAttribute(): ?string
    {
        return $this->content_data['youtube_url'] ?? null;
    }

    public function setYoutubeUrlAttribute(?string $value): void
    {
        $data = $this->content_data;
        if ($value !== null) {
            $data['youtube_url'] = $value;
        } else {
            unset($data['youtube_url']);
        }
        $this->attributes['content_data'] = $data;
    }

    public function getWebLinkAttribute(): ?string
    {
        return $this->content_data['web_link'] ?? null;
    }

    public function setWebLinkAttribute(?string $value): void
    {
        $data = $this->content_data;
        if ($value !== null) {
            $data['web_link'] = $value;
        } else {
            unset($data['web_link']);
        }
        $this->attributes['content_data'] = $data;
    }

    public function getPassingScoreAttribute(): ?int
    {
        return $this->content_data['passing_score'] ?? null;
    }

    public function setPassingScoreAttribute(?int $value): void
    {
        $data = $this->content_data;
        if ($value !== null) {
            $data['passing_score'] = $value;
        } else {
            unset($data['passing_score']);
        }
        $this->attributes['content_data'] = $data;
    }

    public function getAllowAnonymousAttribute(): bool
    {
        return $this->content_data['allow_anonymous'] ?? false;
    }

    public function setAllowAnonymousAttribute(bool $value): void
    {
        $data = $this->content_data;
        $data['allow_anonymous'] = $value;
        $this->attributes['content_data'] = $data;
    }

    public function getShowResultsAfterSubmitAttribute(): bool
    {
        return $this->content_data['show_results_after_submit'] ?? false;
    }

    public function setShowResultsAfterSubmitAttribute(bool $value): void
    {
        $data = $this->content_data;
        $data['show_results_after_submit'] = $value;
        $this->attributes['content_data'] = $data;
    }
}
