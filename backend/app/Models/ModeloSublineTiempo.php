<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModeloSublineTiempo extends Model {
    use HasFactory;

    protected $fillable = ['id_registro', 'sublinea', 'modelo_id', 'tiempo'];
}
