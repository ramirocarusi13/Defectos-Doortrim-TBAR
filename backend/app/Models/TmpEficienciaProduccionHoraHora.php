<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TmpEficienciaProduccionHoraHora extends Model {
    use HasFactory;

    protected $fillable = ['shop', 'oa', 'eficiencia', 'volumen', 'fecha', 'real', 'tipo', 'turno'];
}
