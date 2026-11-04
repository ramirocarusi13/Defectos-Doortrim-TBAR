<?php

namespace App\Http\Controllers;

use App\Http\Estados;
use App\Http\Kanban;
use App\Models\Kanbans;
use App\Models\LectraEstado;
use App\Models\ModeloDado;
use App\Models\ModeloKanbanPadre;
use App\Models\Modelos;
use App\Models\PcPendienteImpresion;
use App\Models\PcPlanProduccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class PcPendienteImpresionController extends Controller {

    public function setPlanificacion(Request $request) {

        // Log::alert($request);

        //Primero quito todos los kanbans planificados
        $kanbansPlanificados = Kanbans::whereHas('estado', function ($query) {
            $query->where('estado_id', Estados::EN_PLANIFICACION);
        })->get();

        foreach ($kanbansPlanificados as $k) {
            Kanban::changeStatus($k, Estados::GENERADO);
            PcPlanProduccion::where('kanban_id', $k->id)->delete();
        }

        //Creo el nuevo plan de produccion
        // Log::alert($request->items);
        foreach ($request->items as $modelo) {
            $kanbans = Kanbans::whereHas('estado', function ($query) {
                $query->where('estado_id', Estados::GENERADO);
            })
                ->whereHas('modelo', function ($q) use ($modelo) {
                    $q->where('nombre', $modelo['modelo']);
                })
                ->get();

            // $mod = Modelos::where('nombre', $modelo['modelo'])->first();

            foreach ($kanbans as $kanban) {
                PcPlanProduccion::create([
                    'kanban_id' => $kanban->id,
                    'orden'     => intval($modelo['orden']) //ORDEN,
                ]);

                Kanban::changeStatus($kanban, Estados::EN_PLANIFICACION);
            }
        }

        return $this->setResponse([]);
    }


    public function getPendientesPlanificacion() {

        // Log::alert("PASO ACA");

        $kanbans = Kanbans::with(['estadoPc'])->whereHas('estado', function ($query) {
            $query->where('estado_id', Estados::GENERADO);
        })
            ->whereHas('modelo', function ($q) {
                $q->where('activo', true);
            })
            ->get();

        if ($kanbans) {
            return $this->setResponse($kanbans->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getPendientes() {

        // $data = PcPendienteImpresion::with(['kanban.modelo', 'kanban.reemplazo.pieza', 'kanban.estado.linea'])
        //     ->where('pendiente', true)
        //     ->orderBy('created_at')
        //     ->get();

        $data = Kanbans::with(['modelo', 'reemplazo.pieza', 'estado.linea', 'estadoPc'])
            ->whereHas('estado', function ($query) {
                $query->where('estado_id', Estados::GENERADO);
            })
            // ->whereHas('modelo', function ($q) {
            //     $q->where('activo', true);
            // })
            ->get();

        // Log::alert($data->toArray());

        // foreach ($data as $k) {

        // $mod = Modelos::where('id', intval($k->modelo_id))->first();
        // $dadosA = ModeloDado::with(['dado.material'])->where('modelo_id', $mod->id)->where('tipo', 'A')->get();
        // $dadosB = ModeloDado::with(['dado.material'])->where('modelo_id', $mod->id)->where('tipo', 'B')->get();
        // $dadosC = ModeloKanbanPadre::with(['material'])->where('modelo_id', $mod->id)->get();

        // $k->setsPorCarro = $mod->cantidad;
        // $k->dadosA = $dadosA;
        // $k->dadosB = $dadosB;
        // $k->dadosC = $dadosC;
        // }

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getSituacionModelos(Request $request) {
        $modelos = [];

        $kanbans = Kanbans::with(['modelo'])
            ->whereHas('estado', function ($q) {
                $q->where('estado_id', Estados::PLANIFICADO);
            })
            ->get();

        foreach ($kanbans as $kanban) {
            $modelo = $kanban->modelo->nombre;

            if (!in_array($modelo, $modelos)) {
                array_push($modelos, $modelo);
            }
        }

        $content = [];

        foreach ($modelos as $mod) {

            $modelo = Modelos::where('nombre', $mod)->first();
            $kanbansCortados = [];

            //Cortados
            $kanbansCortados = Kanbans::with(['modelo'])
                ->whereHas('estado', function ($q) {
                    $q->where('estado_id', Estados::PLANIFICADO);
                })
                ->whereHas('modelo', function ($q) use ($mod) {
                    $q->where('nombre', $mod);
                })
                ->get();

            $pendientes = 0;
            $cortados = 0;

            foreach ($kanbansCortados as $k) {
                $dadosPendientes = LectraEstado::where('modelo', $mod)
                    ->where('operacion', $k->operacion)
                    ->where('fin', null)->get()->count();

                if ($dadosPendientes > 0) {
                    $pendientes = $pendientes + 1;
                } else {
                    $cortados++;
                }
            }

            //En Buffer
            $enBuffer = Kanbans::with(['modelo'])
                ->whereHas('estado', function ($q) {
                    $q->where('estado_id', Estados::EN_BUFFER);
                })
                ->whereHas('modelo', function ($q) use ($mod) {
                    $q->where('nombre', $mod);
                })->get();

            array_push($content, [
                'modelo'                => $mod,
                'sets_cortados'         => $cortados * $modelo->cantidad, //$estanPendientes == 0 ? count($kanbansCortados) : 0,
                'sets_en_corte'         => $pendientes * $modelo->cantidad, //$estanPendientes > 0 ? count($kanbansCortados) : 0,
                'sets_en_buffer'        => count($enBuffer) * $modelo->cantidad,
            ]);
        }

        return $this->setResponse($content);
    }

    public function quitarPendientes(Request $request) {

        $multiple = $request->multiple;
        $codigos = $request->kanbans;
        $ids = [];

        // Log::alert($request);

        $kanbans = Kanbans::when($multiple, function ($q) use ($codigos) {
            $q->whereIn('codigo', $codigos);
        })
            ->when(!$multiple, function ($q) use ($codigos) {
                $q->where('codigo', $codigos);
            })
            ->get();

        // Log::alert($kanbans);

        foreach ($kanbans as $k) {
            array_push($ids, $k->id);
        }

        if (count($ids) == 0) {
            return $this->setResponse([]);
        }

        try {
            PcPendienteImpresion::whereIn('kanban_id', $ids)
                ->update([
                    'pendiente'         => false,
                    'fecha_impresion'   => date('Y-m-d')
                ]);

            return $this->setResponse([]);
        } catch (\Throwable $th) {
            Log::error("PcPendienteImpresionController::quitarPendientes : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas.", true);
        }
    }

    public function calcularCarrosAReponerBuffer() {

        //MODELO
        $modelo = Modelos::where('nombre', 'SFLE')->first();

        //Obtengo el stock actual del modelo
        $stock = 10;

        //Obtengo el plan semanal 
        $plan = new \stdClass();

        $plan->diasLaborablesSemana = 5;
        $plan->consumoSemanal = 315;
        $consumoDiario = $plan->consumoSemanal / $plan->diasLaborablesSemana;
    }
}
