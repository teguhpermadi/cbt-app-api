<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

final class Taxonomy extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'taxonomies';

    protected $fillable = [
        'taxonomy_type',
        'category',
        'code',
        'name',
        'description',
        'order',
        'subcategories',
        'verbs',
        'is_active',
    ];

    protected $casts = [
        'subcategories' => 'array',
        'verbs' => 'array',
        'order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('taxonomy_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeCognitiveProcess($query)
    {
        return $query->where('category', 'Cognitive Process');
    }

    public function scopeKnowledge($query)
    {
        return $query->where('category', 'Knowledge');
    }

    public function scopeLevel($query)
    {
        return $query->where('category', 'Level');
    }
}
