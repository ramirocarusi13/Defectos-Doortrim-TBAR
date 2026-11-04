<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Partes extends Model {
    use HasFactory;

    protected $fillable = ['modelo_id', 'activo', 'vehiculo_id', 'codigo', 'tipo_id', 'lado_id', 'imagen'];

    public function vehiculo(): HasOne {
        return $this->hasOne(Vehiculos::class, 'id', 'vehiculo_id');
    }

    public function tipo(): HasOne {
        return $this->hasOne(TipoPartes::class, 'id', 'tipo_id');
    }

    public function lado(): HasOne {
        return $this->hasOne(LadoPartes::class, 'id', 'lado_id');
    }

    public function piezas() {
        return $this->hasMany(Piezas::class, 'parte_id', 'id');
    }

    public function modelo() {
        return $this->hasMany(Modelos::class, 'id', 'modelo_id')->where('activo', 1);
    }

    public function modeloFalla(): HasOne {
        return $this->hasOne(ModeloFalla::class, 'modelo_id', 'modelo_id')
            ->where('tipo_id', $this->tipo_id)
            ->where('lado_id', $this->lado_id);
    }

    public function modeloFallas(): HasMany {
        return $this->hasMany(ModeloFalla::class, 'modelo_id', 'modelo_id')
            ->where('tipo_id', $this->tipo_id)
            ->where('lado_id', $this->lado_id);
    }

    public static function attachModeloFallas($partes): void {
        $partes = $partes instanceof Collection ? $partes : collect($partes);

        if ($partes->isEmpty()) {
            return;
        }

        $modeloIds = $partes->pluck('modelo_id')->filter()->unique()->values();
        $tipoIds = $partes->pluck('tipo_id')->filter()->unique()->values();
        $ladoIds = $partes->pluck('lado_id')->filter()->unique()->values();

        if ($modeloIds->isEmpty() || $tipoIds->isEmpty() || $ladoIds->isEmpty()) {
            $partes->each(fn($parte) => $parte->setRelation('modeloFalla', null));
            $partes->each(fn($parte) => $parte->setRelation('modeloFallas', collect()));
            return;
        }

        $fallas = ModeloFalla::query()
            ->whereIn('modelo_id', $modeloIds)
            ->whereIn('tipo_id', $tipoIds)
            ->whereIn('lado_id', $ladoIds)
            ->orderBy('id')
            ->get()
            ->groupBy(fn($falla) => self::modeloFallaKey($falla->modelo_id, $falla->tipo_id, $falla->lado_id));

        $partes->each(function ($parte) use ($fallas) {
            $modeloFallas = $fallas->get(self::modeloFallaKey($parte->modelo_id, $parte->tipo_id, $parte->lado_id), collect())->values();

            $parte->setRelation(
                'modeloFalla',
                $modeloFallas->first()
            );
            $parte->setRelation('modeloFallas', $modeloFallas);
        });
    }

    private static function modeloFallaKey($modeloId, $tipoId, $ladoId): string {
        return "{$modeloId}|{$tipoId}|{$ladoId}";
    }

    public function defectosCobot(): BelongsToMany {
        return $this->belongsToMany(DefectoCobot::class, 'defecto_cobot_parte', 'parte_id', 'defecto_cobot_id');
    }
}
