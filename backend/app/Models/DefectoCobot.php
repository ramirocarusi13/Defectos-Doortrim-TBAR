<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DefectoCobot extends Model {
    use HasFactory;

    protected $table = 'defectos_cobot';

    protected $fillable = [
        'nro_cobot',
        'nombre',
        'numero_bit',
        'dispositivo_plc',
        'descripcion',
        'nro_error',
        'cara_b',
        'falla_id',
    ];

    protected $casts = [
        'cara_b' => 'boolean',
    ];

    public function falla(): BelongsTo {
        return $this->belongsTo(CodigoFalla::class, 'falla_id', 'id');
    }

    public function modelos(): BelongsToMany {
        return $this->belongsToMany(Modelos::class, 'defecto_cobot_modelo', 'defecto_cobot_id', 'modelo_id');
    }

    public function partes(): BelongsToMany {
        return $this->belongsToMany(Partes::class, 'defecto_cobot_parte', 'defecto_cobot_id', 'parte_id')
            ->withPivot(['x', 'y'])
            ->withTimestamps();
    }
}
