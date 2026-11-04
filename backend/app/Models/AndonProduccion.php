<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AndonProduccion extends Model {
    use HasFactory;

    protected $fillable = ['turno', 'fecha', 'linea_id', 'cantidad'];
}
