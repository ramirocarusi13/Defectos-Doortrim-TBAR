<?php

namespace App\Models;

use App\Http\Depositos;
use App\Http\Estados;
use App\Http\Kanban;
use App\Http\StockPiezasLib;
use App\Models\Estados as ModelsEstados;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class EstadoKanban extends Model {
    use HasFactory;
    protected $fillable = ['kanban_id', 'estado_previo_id', 'estado_id', 'user_id', 'secuencia', 'posicion', 'carro', 'linea_id', 'lectra'];


    public static function boot() {
        parent::boot();

        static::updated(function (EstadoKanban $estado) {
            try {
                LogEstadosKanbans::create([
                    'kanban_id'         => $estado->kanban_id,
                    'estado_id'         => $estado->estado_id,
                    'user_id'           => $estado->user_id,
                    'estado_previo_id'  => $estado->estado_previo_id
                ]);
            } catch (\Throwable $th) {
                //throw $th;
            }
        });

        static::created(function (EstadoKanban $estado) {
            //Registro el cambio de estado
            try {
                LogEstadosKanbans::create([
                    'kanban_id'         => $estado->kanban_id,
                    'estado_id'         => $estado->estado_id,
                    'user_id'           => $estado->user_id,
                    'estado_previo_id'  => $estado->estado_previo_id
                ]);
            } catch (\Throwable $th) {
                //throw $th;
            }

            if ($estado->estado_id == Estados::EN_CORTE) {
                StockPiezasLib::crearStockEnCorte($estado->kanban_id);
            } else if ($estado->estado_id == Estados::EN_BUFFER) {
                StockPiezasLib::transferenciaEntreDepositos($estado->kanban_id, Depositos::BUFFER, Depositos::CORTE);
            } else if ($estado->estado_id == Estados::SUB_ASSY || $estado->estado_id == Estados::COSTURA) {
                //Debo verificar desde donde viene, si es COSTURA, puede venir de subassy o de buffer
                if ($estado->estado_previo_id == Estados::SUB_ASSY && $estado->estado_id == Estados::COSTURA) {
                    //VIENE DE SUB Y ENTRA A MAIN
                    $depositoIn = Depositos::COSTURA;
                    $depositoOut = Depositos::SUB_ASSY;
                } else if ($estado->estado_previo_id == Estados::EN_BUFFER && $estado->estado_id == Estados::COSTURA) {
                    //VIENE DE BUFFER Y ENTRA A MAIN
                    $depositoIn = Depositos::COSTURA;
                    $depositoOut = Depositos::BUFFER;
                } else if ($estado->estado_previo_id == Estados::EN_BUFFER && $estado->estado_id == Estados::SUB_ASSY) {
                    //VIENE DE BUFFER Y ENTRA A SUB
                    $depositoIn = Depositos::SUB_ASSY;
                    $depositoOut = Depositos::BUFFER;
                }

                // $deposito = $estado->estado_id == Estados::SUB_ASSY ? Depositos::SUB_ASSY : Depositos::COSTURA;

                // StockPiezasLib::transferenciaEntreDepositos($estado->kanban_id, $depositoIn, $depositoOut);

                // Log::alert($estado);
                //TODO Reordeno las posiciones
                //Al quitar de buffer e ingresar a sub assy, debo reacomodar los carros
                $posicion = $estado->posicion;

                $linea = Lineas::where('id', $estado->linea_id)->first();

                if (!$linea) {
                    return;
                }
                $posAExtraer = intval($posicion);

                while ($posAExtraer < $linea->capacidad) {
                    $posAExtraer = $posAExtraer + intval($linea->columnas);
                    // Log::alert($posAExtraer);

                    $estadoMover = EstadoKanban::where('linea_id', $linea->id)->where('posicion', $posAExtraer)->first();

                    if ($estadoMover) {
                        EstadoKanban::where('id', $estadoMover->id)->update(['posicion' => $posAExtraer - $linea->columnas]);
                    }
                }
            }

            //Cuando sale de Buffer, verifico máximos y mínimos del modelo
            //Para ver si genero a PC una impresion pendiente de kanban
            // if ($estado->estado_previo_id == Estados::EN_BUFFER) {
            //     $kanban = Kanbans::where('id', $estado->kanban_id)->first();
            //     Kanban::verificaReposicion($kanban, $estado->linea_id);
            // }
        });
    }

    public function estado(): HasOne {
        return $this->hasOne(ModelsEstados::class, 'id', 'estado_id');
    }

    public function linea(): HasOne {
        return $this->hasOne(Lineas::class, 'id', 'linea_id');
    }

    public function kanban(): HasOne {
        return $this->hasOne(Kanbans::class, 'id', 'kanban_id');
    }
}
