<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\InteractsWithMedia;

class ReadingMaterial extends Model
{
    /** @use HasFactory<\Database\Factories\ReadingMaterialFactory> */
    use HasFactory, HasUlids, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'title',
        'content',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('reading_materials')
            ->singleFile();
    }
}
