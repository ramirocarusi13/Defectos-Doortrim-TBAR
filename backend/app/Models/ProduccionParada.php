<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProduccionParada extends Model {
    use HasFactory;

    protected $fillable = ['fecha', 'causa', 'turno', 'turno_nombre', 'intervalo', 'area', 'shop', 'minutos', 'categoria', 'grupo', 'contramedida'];
}
