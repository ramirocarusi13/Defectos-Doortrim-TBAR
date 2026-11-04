<?php

namespace App\Models;

use App\Http\Estados;
use App\Http\EstadosQr;
use App\Models\Sar\SarEpEtiqueta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kanbans extends Model {
    use HasFactory;
    protected $fillable = ['codigo', 'modelo_id', 'fecha', 'mes', 'operacion', 'linea', 'secuencia', 'cantidad', 'lote'];

    public static function boot() {
        parent::boot();

        static::created(function (Kanbans $kb) {
            //Registro el estado inicial del Kanban, GENERADO

            //para cuando no está logueado
            try {
                $userId = auth()->guard('api')->user()->id;
            } catch (\Throwable $th) {
                $userId = null;
            }

            $modelo = Modelos::where('id', $kb->modelo_id)->first();

            $data = [
                'kanban_id' => $kb->id,
                'estado_id' => Estados::GENERADO,
                'user_id'   => $userId,
                'linea_id'  => $modelo ? $modelo?->linea_buffer : null
            ];

            EstadoKanban::create($data);
        });
    }

    public function modelo(): HasOne {
        return $this->hasOne(Modelos::class, 'id', 'modelo_id'); //->where('activo', 1);
    }

    public function estado(): HasOne {
        return $this->hasOne(EstadoKanban::class, 'kanban_id', 'id');
    }

    public function reemplazo(): HasOne {
        return $this->hasOne(KanbansReemplazo::class, 'kanban_id', 'id');
    }

    public function planCorte(): HasOne {
        return $this->hasOne(PlanCorte::class, 'operacion', 'operacion');
    }

    public function etiquetas(): HasMany {
        return $this->hasMany(SarEpEtiqueta::class, 'ID_KB', 'codigo');
    }

    public function history(): HasMany {
        return $this->hasMany(LogEstadosKanbans::class, 'kanban_id', 'id');
    }

    public function estadoPc(): HasOne {
        return $this->hasOne(PcPendienteImpresion::class, 'kanban_id', 'id');
    }

    public function pendienteValidarQr(): HasMany {
        return $this->hasMany(EpEtiqueta::class, 'kanban_impresion', 'codigo')
            ->where('airbag', false)
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->where('estado', EstadosQr::CREADA);
                    $q->orWhere('estado', EstadosQr::IMPRESION);
                });
            });
    }

    public function pendienteValidarQrModelo(): HasMany {
        return $this->hasMany(EpEtiqueta::class, 'modelo_id', 'modelo_id')
            ->where('airbag', false)
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->where('estado', EstadosQr::CREADA);
                    $q->orWhere('estado', EstadosQr::IMPRESION);
                });
            });
    }

    public function etiquetasQrValidadas(): HasMany {
        return $this->hasMany(EpEtiqueta::class, 'kanban', 'codigo')->where('estado', EstadosQr::QCP_OK)->where('airbag', false);
    }

    public function pendienteValidarQrQcModelo(): HasMany {
        //ESTAS AHORA SON POR MODELO
        // return $this->hasMany(EpEtiqueta::class, 'kanban', 'codigo')->where('estado', '<>', EstadosQr::QCP_OK);
        return $this->hasMany(EpEtiqueta::class, 'modelo_id', 'modelo_id')->where('estado', '<>', EstadosQr::QCP_OK)->where('airbag', false);
    }

    public function etiquetasAsignadasKanbanAirbag(): HasMany {
        //SON LAS ETIQUETAS QUE YA SE VALIDARON PARA EL KANBAN
        // return $this->hasMany(EpEtiqueta::class, 'kanban', 'codigo')->where('estado', '<>', EstadosQr::QCP_OK);
        return $this->hasMany(EpEtiqueta::class, 'kanban', 'codigo')->where('airbag', true)->where('estado', EstadosQr::QCP_OK);
    }

    public function pendienteValidarQrQc(): HasMany {
        return $this->hasMany(EpEtiqueta::class, 'kanban_impresion', 'codigo')->where('estado', '<>', EstadosQr::QCP_OK)->where('airbag', false);
        // return $this->hasMany(EpEtiqueta::class, 'modelo_id', 'modelo_id')->where('estado', '<>', EstadosQr::QCP_OK);
    }

    public function etiquetasQrGeneradas(): HasMany {
        return $this->hasMany(EpEtiqueta::class, 'kanban_impresion', 'codigo')->where('airbag', false);
        // return $this->hasMany(EpEtiqueta::class, 'kanban_impresion', 'codigo');
    }

    public function etiquetasQrGeneradasModelo(): HasMany {
        return $this->hasMany(EpEtiqueta::class, 'modelo_id', 'modelo_id')->where('estado', '<>', EstadosQr::QCP_OK)->orderBy('secuencia')->where('airbag', false);
        // return $this->hasMany(EpEtiqueta::class, 'kanban_impresion', 'codigo');
    }
}
