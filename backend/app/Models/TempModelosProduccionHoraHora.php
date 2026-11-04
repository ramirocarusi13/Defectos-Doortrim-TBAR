<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempModelosProduccionHoraHora extends Model {
    use HasFactory;

    protected $fillable = ['shop', 'real', 'turno', 'fecha','tipo','plan','actual','fondo','fondo_actual'];
}
