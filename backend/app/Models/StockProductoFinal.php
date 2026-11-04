<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockProductoFinal extends Model {
    use HasFactory;

    protected $fillable = ['codigo_kanban', 'run', 'fecha_calidad', 'rechazado', 'fecha_egreso', 'cuarentena', 'egresado', 'modelo', 'mes', 'fecha', 'modelo_id', 'kanbnan_id'];
}
