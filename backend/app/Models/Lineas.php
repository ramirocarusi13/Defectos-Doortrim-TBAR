<?php

namespace App\Models;

use App\Http\Estados;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lineas extends Model {
    use HasFactory;

    protected $fillable = ['codigo', 'capacidad', 'posicion', 'columnas', 'hc', 'takt_time', 'nombre', 'turno_blanco', 'turno_amarillo'];
    protected $hidden = ['created_at', 'updated_at'];

    public function kanbansEnBuffer(): HasMany {
        return $this->hasMany(EstadoKanban::class, 'linea_id', 'id')->where('estado_id', Estados::EN_BUFFER)->orderBy('posicion');
    }
}
