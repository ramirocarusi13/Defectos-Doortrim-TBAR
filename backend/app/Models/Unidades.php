<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unidades extends Model {
    use HasFactory;

    protected $table = 'wms_unidades';
    protected $fillable = ['nombre', 'volumen', 'cantidad_unica', 'es_kanban'];
}
