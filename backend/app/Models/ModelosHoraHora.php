<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelosHoraHora extends Model {
    use HasFactory;

    protected $fillable = ['linea', 'turno', 'fecha', 'modelo', 'nombre_turno', 'plan', 'real'];
}
