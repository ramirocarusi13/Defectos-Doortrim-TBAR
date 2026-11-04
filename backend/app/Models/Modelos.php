<?php

namespace App\Models;

use App\Http\Estados;
use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

class Modelos extends Model {
    use HasFactory;

    protected $fillable = ['minimo_buffer', 'ptopedido_buffer', 'linea_buffer', 'revision', 'volumen', 'hr', 'activo', 'ctrhr', 'ar', 'descripcion', 'nombre', 'airbag', 'material_id', 'color_id', 'fila_id', 'codigo', 'cantidad', 'consumo'];
    // protected $hidden = ['created_at', 'updated_at'];

    public function material(): HasOne {
        return $this->hasOne(Materiales::class, 'id', 'material_id');
    }

    public function dadosEnCorte(): HasMany {
        return $this->hasMany(LectraEstado::class, 'nombre', 'modelo');
    }

    public function color(): HasOne {
        return $this->hasOne(Colores::class, 'id', 'color_id');
    }

    public function fila(): HasOne {
        return $this->hasOne(Filas::class, 'id', 'fila_id');
    }

    public function partes(): HasMany {
        return $this->hasMany(Partes::class, 'modelo_id', 'id')->where('activo', 1);
    }

    public function piezas(): HasManyThrough {
        return $this->hasManyThrough(Piezas::class, Partes::class, 'modelo_id', 'parte_id', 'id', 'id')->where('activo', 1);
    }

    public function archivos(): HasMany {
        return $this->hasMany(ModelosArchivos::class, 'modelo_id', 'id');
    }

    public function lineas(): HasManyThrough {
        return $this->hasManyThrough(Lineas::class, ModeloLinea::class, 'modelo_id', 'id', 'id', 'linea_id');
    }

    public function enBuffer(): HasManyThrough {
        return $this->hasManyThrough(EstadoKanban::class, Kanbans::class, 'modelo_id', 'kanban_id', 'id', 'id')->where('estado_id', Estados::EN_BUFFER);
    }

    public function enBufferCorte(): HasManyThrough {
        return $this->hasManyThrough(EstadoKanban::class, Kanbans::class, 'modelo_id', 'kanban_id', 'id', 'id')->where('estado_id', Estados::EN_BUFFER_CORTE);
    }

    public function enProduccion(): HasManyThrough {
        return $this->hasManyThrough(EstadoKanban::class, Kanbans::class, 'modelo_id', 'kanban_id', 'id', 'id')
            ->where(function ($q) {
                $q->where('estado_id', Estados::SUB_ASSY);
                $q->orWhere('estado_id', Estados::COSTURA);
            });
    }

    public function enPlan(): HasManyThrough {
        return $this->hasManyThrough(EstadoKanban::class, Kanbans::class, 'modelo_id', 'kanban_id', 'id', 'id')
            ->where(function ($q) {
                $q->where('estado_id', Estados::PLANIFICADO);
                $q->orWhere('estado_id', Estados::EN_PLANIFICACION);
            });
    }


    // public function fallas(): HasManyThrough {
    //     return $this->hasManyThrough(CodigoFalla::class, ModeloFalla::class, 'modelo_id', 'id', 'id', 'falla_id');
    // }

    public function fallas(): HasMany {
        return $this->hasMany(ModeloFalla::class, 'modelo_id', 'id');

        // return $this->hasManyThrough(CodigoFalla::class, ModeloFalla::class, 'modelo_id', 'id', 'id', 'falla_id');
    }

    public function defectosCobot(): BelongsToMany {
        return $this->belongsToMany(DefectoCobot::class, 'defecto_cobot_modelo', 'modelo_id', 'defecto_cobot_id');
    }

    public function stockFinalLogistica(): HasMany {
        return $this->hasMany(StockProductoFinal::class, 'modelo', 'nombre')
            ->selectRaw('month(fecha) as mes,year(fecha) as ano, count(*) as cantidad ')
            ->groupByRaw('YEAR(fecha)')
            ->groupByRaw('MONTH(fecha)')
            ->orderByRaw('YEAR(fecha)')
            ->orderByRaw('MONTH(fecha)');
    }

    public function tiempoCorte($lectra, $tipo = null) {
        $startTime = '00:00:00';
        $horaInicio = DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d') . ' ' . $startTime);
        $inicio = null;
        // $posicionamiento = null;
        $paso = false;

        $dados = ModeloKanbanPadre::with('modeloDado')
            ->when(!empty($tipo), function ($q) use ($tipo) {
                $q->whereHas('modeloDado', function ($query) use ($tipo) {
                    $query->where('tipo', $tipo);
                });
            })
            ->where('modelo_id', $this->id)->get();

        foreach ($dados as $dado) {
            if ($dado->{"t_lectra" . $lectra}) {
                $paso = true;
                if (is_null($inicio)) {
                    $inicio = $horaInicio->format('Y-m-d H:i:s');
                }
                //Obtengo los tiempos para la lectra
                $times = explode(":", $dado->{"t_lectra" . $lectra});

                for ($i = 0; $i < intval($dado->corte); $i++) {
                    //Sumo las horas,minutos y segundos por cada repeticion
                    $horaInicio->modify("+" . intval($times[0]) . " hours");
                    $horaInicio->modify("+" . intval($times[1]) . " minutes");
                    $horaInicio->modify("+" . intval($times[2]) . " seconds");
                }

                //TODO Sumo fijo el posicionamiento
                $horaInicio->modify("+2 minutes");
            }

            // if ($dado->t_posicionamiento && !$posicionamiento) {
            //     $posicionamiento = $dado->t_posicionamiento;
            // }
        }

        // if ($posicionamiento) {
        //     $repes = explode(":", $posicionamiento);
        //     $horaInicio->modify("+" . intval($repes[0]) . " hours");
        //     $horaInicio->modify("+" . intval($repes[1]) . " minutes");
        //     $horaInicio->modify("+" . intval($repes[2]) . " seconds");
        // }

        if (!$paso) {
            return ['microseconds' => 0, 'time' => '00:00'];
        }

        $horaFin = $horaInicio;
        $interval = $horaFin->diff(new DateTime($inicio));
        $hours = $interval->format('%H');
        // $seconds = $interval->format('%s');
        $minutes = $interval->format('%i');


        return  ['microseconds' => (intval($hours) * 60) + intval(($minutes)), 'time' => $hours . ':' . $minutes]; // $hours . ':' . $minutes;
    }
}
