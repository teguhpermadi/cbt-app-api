<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Badge extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'image_url',
        'requirement_type',
        'requirement_value',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->using(UserBadge::class)
            ->withPivot('awarded_at')
            ->withTimestamps();
    }
}
