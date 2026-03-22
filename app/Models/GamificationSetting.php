<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class GamificationSetting extends Model
{
    use HasUlids;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];
}
