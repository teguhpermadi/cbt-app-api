<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    /** @use HasFactory<\Database\Factories\SubjectFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'code',
        'description',
        'image_url',
        'logo_url',
        'user_id',
        'color',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
