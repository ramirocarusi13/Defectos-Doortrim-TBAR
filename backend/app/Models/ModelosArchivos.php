<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelosArchivos extends Model {
    use HasFactory;

    protected $fillable = ['modelo_id', 'ruta', 'nombre', 'descripcion'];
}
