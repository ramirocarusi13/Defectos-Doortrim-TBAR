<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TmpUbicaciones extends Model {
    use HasFactory;

    protected $fillable = ['deposito_id', 'ubicacion_id', 'nombre', 'ref'];
}
