<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
