<?php

namespace App\Http\Controllers;

use App\Http\Kanban;
use App\Models\Modelos;
use App\Models\PcPlanProduccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PcPlanProduccionController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        //
    }

    public function getCurrentPlan() {

        $today = date('Y-m-d');

        $data = PcPlanProduccion::with('modelo')->where('vigencia_desde', '<=', $today)
            ->where('vigencia_hasta', '>=', $today)->get();


        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function store(Request $request) {

        $fechaDesde = $request->fecha;
        $fechaHasta = $request->fechaHasta;

        $fechaDesde = empty($fechaDesde) ? "2020-01-01" : date("Y-m-d", strtotime($fechaDesde));
        $fechaHasta = empty($fechaHasta) ? "2200-01-01" : date("Y-m-d", strtotime($fechaHasta));
        $modelos = Modelos::where('activo', 1)->get();

        if ($request->edit) {
            PcPlanProduccion::where('vigencia_desde', $fechaDesde)->where('vigencia_hasta', $fechaHasta)->delete();
        }

        foreach ($request->plan as $modelo) {
            $consumo = 0;
            // Log::alert($modelo);
            $mod = Modelos::where('nombre', $modelo['modelo'])->first();

            if (intval($modelo['consumo']) > 0) {
                $consumo = intval($modelo['consumo']);
            }

            $data = [
                'modelo_id'         => $mod->id,
                'consumo'           => $consumo,
                'vigencia_desde'    => $fechaDesde,
                'vigencia_hasta'    => $fechaHasta,
            ];

            // Log::alert($data);
            PcPlanProduccion::create($data);
        }

        // foreach ($modelos as $modelo) {
        //     $consumo = 0;
        //     if (!empty($request->{"consumo_" . $modelo->id})) {
        //         $consumo = intval($request->{"consumo_" . $modelo->id});
        //     }

        //     $data = [
        //         'modelo_id'         => $modelo->id,
        //         'consumo'           => $consumo,
        //         'vigencia_desde'    => $fechaDesde,
        //         'vigencia_hasta'    => $fechaHasta,
        //     ];
        //     PcPlanProduccion::create($data);
        // }

        return $this->setResponse([]);
    }

    public function getEstadoModelosStock() {
        $today = date('Y-m-d');
        $response = [];

        // $modelo = Modelos::withCount('enBuffer')->where('nombre', 'SFLE')->first();
        $modelos = Modelos::withCount('enBuffer')->where('activo', true)->get();

        if (!$modelos) {
            return;
        }

        foreach ($modelos as $modelo) {

            $plan = PcPlanProduccion::where(function ($q) use ($today) {
                $q->where('vigencia_desde', '<=', $today)
                    ->where('vigencia_hasta', '>=', $today);
            })->where('modelo_id', $modelo->id)->first();

            if ($plan) {
                $consumoSemanal = intval($plan->consumo);
                $consumoDiario = intval($plan->consumo) / 5; //Suponiendo que el plan es semanal
            } else {
                $consumoDiario = 0;
                $consumoSemanal = 0;
            }

            $cantidadCortes = 0;
            $minimo = $consumoDiario * 3;
            $maximo = $consumoDiario * 6;
            $stockActual = 0;
            $stockIdeal = ceil(($minimo + $maximo) / 2);
            $requeridoCorte = 0;

            $cantidadSetsModelo = intval($modelo->cantidad);
            $volumenCorte = intval($modelo->volumen); //Volumen de sets por corte

            //Genero los kanban a imprimir por PC para reposición
            $enviados = Kanban::getKanbanEnviados($modelo);
            $enBuffer = $modelo->enBuffer;

            //Kanbans generados pero que quedaron pendientes de planificacion de PC
            $pendiente = Kanban::getKanbanPendienteImpresion($modelo);

            //Cantidad en proceso (Corte)
            $enProceso = Kanban::getKanbanEnProceso($modelo);

            //Kanbans generados no pendientes de impresion (generación manual)
            $kanbansManuales = Kanban::getKanbanManuales($modelo);

            $stockActual = Kanban::getStockRealFinalizado($modelo); //(count($enBuffer) + count($pendiente) + count($kanbansManuales) + count($enProceso) - count($enviados)) * $cantidadSetsModelo; // Cantidad en sets
            $stockActual = count($stockActual) * $cantidadSetsModelo;

            // Log::alert($stockActual);
            if ($stockIdeal < 0) {
                $requeridoCorte = 0;
            } else {
                $requeridoCorte = $stockIdeal - $stockActual;
            }

            if ($requeridoCorte > 0 && $volumenCorte > 0) {
                $cantidadCortes = ceil($requeridoCorte / $volumenCorte);
            }

            $stockAlCortar = $stockActual + ($cantidadCortes * $volumenCorte);
            if ($consumoDiario > 0) {
                $stockDias = round($stockActual / $consumoDiario, 1);
            } else {
                $stockDias = 0;
            }
            // $stockDiasCortar = round($stockAlCortar / $consumoDiario, 1);


            array_push($response, [
                'minimo'        => $minimo,
                'maximo'        => $maximo,
                'plan'          => $consumoDiario,
                'stock'         => $stockActual,
                'stockIdeal'    => $stockIdeal,
                'stockDias'     => $stockDias,
                'modelo'        => $modelo->nombre,
                'planSemanal'   => $consumoSemanal
            ]);
        }

        return $this->setResponse($response);
    }



    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PcPlanProduccion  $pcPlanProduccion
     * @return \Illuminate\Http\Response
     */
    public function show(PcPlanProduccion $pcPlanProduccion) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PcPlanProduccion  $pcPlanProduccion
     * @return \Illuminate\Http\Response
     */
    public function edit(PcPlanProduccion $pcPlanProduccion) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PcPlanProduccion  $pcPlanProduccion
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PcPlanProduccion $pcPlanProduccion) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PcPlanProduccion  $pcPlanProduccion
     * @return \Illuminate\Http\Response
     */
    public function destroy(PcPlanProduccion $pcPlanProduccion) {
        //
    }
}
