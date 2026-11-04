<?php

namespace App\Http\Controllers;

use App\Http\Depositos;
use App\Http\Estados;
use App\Http\LineasModelo;
use App\Models\EstadoKanban;
use App\Models\Kanbans;
use App\Models\LectraEstado;
use App\Models\Modelos;
use App\Models\Partes;
use App\Models\PcPendienteImpresion;
use App\Models\StockProductoFinal;
use App\Models\UbicacionContenido;
use Illuminate\Http\Request;

class TestController extends Controller {

    public function test3(Request $request) {
        $modelo = 'SFHG';
        $modeloId = 24;

        $data = Partes::with(['tipo', 'lado'])->whereHas('tipo', function ($q) {
            $q->where('tipo', 'CUSHION');
            $q->orWhere('tipo', 'T-UP');
        })
            ->whereHas('vehiculo', function ($q) use ($modelo) {
                $vehiculo = LineasModelo::VEHICULOS[$modelo];
                if (strpos($vehiculo, '/') > 0) {
                    $vehiculo = substr($vehiculo, 0, strpos($vehiculo, '/'));
                }
                $q->where('codigo', 'like', '%' . $vehiculo . '%');
            })
            ->where('modelo_id', $modeloId)
            ->where('activo', true)
            ->orderBy('vehiculo_id', 'ASC')
            ->orderBy('lado_id', 'ASC')
            ->get()
            ->toArray();

        return $this->setResponse($data);
    }

    public function test2(Request $request) {
        // $modelo = Modelos::withCount('enBuffer')->where('nombre', 'SFLE')->first();

        // $data = Kanbans::with(['modelo', 'estado'])->select('estado_kanbans.updated_at')->whereHas('estado', function ($q) {
        //     $q->where('estado_previo_id', Estados::EN_BUFFER);
        // })->get()->toArray();

        $data = EstadoKanban::with('kanban.modelo')->where(function ($q) {
            $date = date('Y-m-d H:i:s');
            $dateFrom = strtotime('-4 hour', strtotime($date));
            $dateFrom = date('Y-m-d H:i:s', $dateFrom);


            $q->where('created_at', '<=', $date);
            $q->where('created_at', '>=', $dateFrom);
        })->where('estado_previo_id', Estados::EN_BUFFER)
            ->orderBy('updated_at')->get()->toArray();

        return $this->setResponse($data);
    }

    public function test(Request $request) {
        $cantidadCortes = 0;
        $consumoDiario = 227;
        $minimo = $consumoDiario * 3;
        $maximo = $consumoDiario * 6;
        // $volumenCorte = 60;
        $stockActual = 0; //Inicial + producido - enviado
        $stockIdeal = ceil(($minimo + $maximo) / 2);

        $requeridoCorte = 0;

        $modelo = Modelos::withCount('enBuffer')->where('nombre', 'SFLE')->first();

        if (!$modelo) {
            return;
        }

        $cantidadSetsModelo = intval($modelo->cantidad);
        $volumenCorte = intval($modelo->volumen); //Volumen de sets por corte

        //Genero los kanban a imprimir por PC para reposición

        //Cantidad a imprimir
        $cantidad = 0;

        $enviados = StockProductoFinal::where('fecha_egreso', date('Y-m-d'))->where('egresado', 1)->where('modelo', $modelo->nombre)->get()->toArray();

        $enBuffer = $modelo->enBuffer;

        //Cantidad pendiente de impresion
        //Kanbans generados pero que quedaron pendientes de planificacion de PC
        $pendiente = PcPendienteImpresion::whereHas('kanban.modelo', function ($q) use ($modelo) {
            $q->where('id', $modelo->id);
        })->where('pendiente', 1)->get()->toArray();

        //Cantidad en proceso (Corte)
        $enProceso = PcPendienteImpresion::whereHas('kanban.modelo', function ($q) use ($modelo) {
            $q->where('id', $modelo->id);
        })
            ->whereHas('kanban.estado', function ($q) {
                $q->where('estado_id', Estados::GENERADO);
                $q->orWhere('estado_id', Estados::EN_CORTE);
                $q->orWhere('estado_id', Estados::PLANIFICADO);
                $q->orWhere('estado_id', Estados::EN_CORTE);
                $q->orWhere('estado_id', Estados::COSTURA);
                $q->orWhere('estado_id', Estados::SUB_ASSY);
            })->where('pendiente', 0)->get()->toArray();

        //Kanbans generados no pendientes de impresion (generación manual)
        $kanbansManuales = Kanbans::whereHas('modelo', function ($q) use ($modelo) {
            $q->where('id', $modelo->id);
        })
            ->whereHas('estado', function ($q) {
                $q->where('estado_id', Estados::GENERADO);
                $q->orWhere('estado_id', Estados::EN_CORTE);
                $q->orWhere('estado_id', Estados::PLANIFICADO);
                $q->orWhere('estado_id', Estados::COSTURA);
                $q->orWhere('estado_id', Estados::EN_CORTE);
                $q->orWhere('estado_id', Estados::SUB_ASSY);
            })->get()->toArray();

        $stockActual = (count($enBuffer) + count($pendiente) + count($kanbansManuales) + count($enProceso) - count($enviados)) * $cantidadSetsModelo; // Cantidad en sets

        if ($stockIdeal < 0) {
            $requeridoCorte = 0;
        } else {
            $requeridoCorte = $stockIdeal - $stockActual;
        }

        if ($requeridoCorte > 0 && $volumenCorte > 0) {
            $cantidadCortes = ceil($requeridoCorte / $volumenCorte);
        }

        $stockAlCortar = $stockActual + ($cantidadCortes * $volumenCorte);
        $stockDias = round($stockActual / $consumoDiario, 1);
        $stockDiasCortar = round($stockAlCortar / $consumoDiario, 1);

        return $this->setResponse([
            'minimo'            => $minimo,
            'maximo'            => $maximo,
            'stockIdeal'        => $stockIdeal,
            'volumenCorte'      => $volumenCorte,
            'stock'             => $stockActual,
            'stockDias'         => $stockDias,
            'stockAlCortar'     => $stockAlCortar,
            'stockDiasCortar'   => $stockDiasCortar,
            'requeridoCorte'    => $requeridoCorte,
            'cantidadCortes'    => $cantidadCortes,
            'enBuffer'          => count($enBuffer) * $cantidadSetsModelo,
            'enviados'          => count($enviados) * $cantidadSetsModelo,
            'manuales'          => count($kanbansManuales) * $cantidadSetsModelo,
            'pendiente'         => count($pendiente) * $cantidadSetsModelo,
            'enProceso'         => count($enProceso) * $cantidadSetsModelo,
        ]);
    }
}
