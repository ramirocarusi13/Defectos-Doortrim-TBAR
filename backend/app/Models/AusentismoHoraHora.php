<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AusentismoHoraHora extends Model {
    use HasFactory;

    protected $fillable = [
        'user_id',
        'turno',
        'fecha',
        'linea_id',
        'comentario',
        'estado',
        'operacion'
    ];
}
