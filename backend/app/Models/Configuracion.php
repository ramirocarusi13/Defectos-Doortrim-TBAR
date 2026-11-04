<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model {
    use HasFactory;

    protected $fillable = ['clave', 'valor'];
    protected $hidden = ['created_at', 'updated_at'];
}
