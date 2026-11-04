<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AndonLineas extends Model {
    use HasFactory;

    // protected $connection = 'sqlsrvsar';
    protected $table = 'vt_andon_lineas';

    // protected $hidden = ['created_at', 'updated_at'];
    // protected $fillable = [''];
}
