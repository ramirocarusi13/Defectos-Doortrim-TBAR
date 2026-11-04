<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HoraHoraSoporte extends Model {
    use HasFactory;

    protected $fillable = ['turno', 'linea', 'fecha', 'soporte'];
}
