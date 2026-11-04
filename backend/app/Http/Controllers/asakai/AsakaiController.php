<?php

namespace App\Http\Controllers\asakai;

use App\Services\FallaService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\FallasInformadas;
use App\Models\LineaOperaciones;
use App\Services\UserService;
use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Support\Facades\Log;

$lineas = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

class AsakaiController extends Controller {


    public function getFmds(Request $request) {
        $fechaFiltroDesde = null;
        $fechaFiltroHasta = null;
        $fecha = new DateTime();

        // Log::alert($request);

        if ($request->has('fechaDesde')) {
            if ($request->fechaDesde) {
                $fechaFiltroDesde = DateTime::createFromFormat("d/m/Y", $request->fechaDesde);
            } else {
                $fechaFiltroDesde = (clone $fecha);
            }
        } else {
            $fechaFiltroDesde = (clone $fecha);
        }

        if ($request->has('fechaHasta')) {
            if ($request->fechaHasta) {
                $fechaFiltroHasta = DateTime::createFromFormat("d/m/Y", $request->fechaHasta);
                $fechaFiltroHasta->add(new DateInterval("P1D"));
            } else {
                $fechaFiltroHasta = (clone $fecha);
            }
        } else {
            $fechaFiltroHasta = (clone $fecha);
        }

        if ($fechaFiltroDesde == $fechaFiltroHasta) {
            //A Fecha hasta le sumo 1 dia
            $fechaFiltroHasta->add(new DateInterval("P1D"));
        }


        $fechaManana = new DateTime();
        $fechaManana->add(new DateInterval("P1D"));
        // $turno = 'A';
        // $linea = 1;

        $fechaInicioMes = (clone $fecha)->modify('first day of this month');
        $fechaFinMes = (clone $fecha)->modify('last day of this month');

        $fechaInicioMesPasado = (clone $fecha)->modify('first day of last month');
        $fechaFinMesPasado = (clone $fecha)->modify('last day of last month');

        // if ($request->has('turno')) {
        //     $turno = $request->turno;
        // } else {
        //     $turno = getTurnoActual();
        // }

        if ($request->has('linea')) {
            $linea = $request->linea;
        }


        $membersResponse = [];
        $membersLinea = UserService::obtenerMembersConLinea();


        foreach ($membersLinea as $member) {

            if ($member['departamento'] == 'PRODUCCION') {
                //DEFECTOS MES ACTUAL
                $member['defectos_mes_actual'] = FallasInformadas::where('user_operacion_id', $member['id'])
                    ->whereBetween('created_at', [$fechaInicioMes->format('Y-m-d H:i:s'), $fechaFinMes->format('Y-m-d H:i:s')])
                    ->get();

                //DEFECTOS MES PASADO
                $member['defectos_mes_pasado'] = FallasInformadas::where('user_operacion_id', $member['id'])
                    ->whereBetween('created_at', [$fechaInicioMesPasado->format('Y-m-d H:i:s'), $fechaFinMesPasado->format('Y-m-d H:i:s')])
                    ->get();

                //TOP DEFECTOS
                $member['defectos_top'] = FallasInformadas::filtroBaseSinLinea($fechaFiltroDesde, $fechaFiltroHasta)
                    ->selectRaw("count(*) as cantidad, fallas_informadas.falla_id, codigo_fallas.nombre")
                    ->leftJoin('codigo_fallas', 'fallas_informadas.falla_id', 'codigo_fallas.id')
                    ->groupBy('fallas_informadas.falla_id', 'codigo_fallas.nombre')
                    ->where('user_operacion_id', $member['id'])
                    ->orderByDesc('cantidad')
                    ->limit(5)
                    ->get();

                foreach ($member['defectos_top'] as $key => $defecto) {
                    $detalleFallas = FallasInformadas::filtroBaseSinLinea($fechaFiltroDesde, $fechaFiltroHasta)
                        ->with(['falla', 'etiqueta.modelod', 'imagen'])
                        ->where('user_operacion_id', $member['id'])
                        ->where('falla_id', $defecto['falla_id'])
                        // ->orderByDesc('created_at')
                        // ->limit(5)
                        ->get();

                    $member['defectos_top'][$key]['detalle'] = $detalleFallas;
                }

                array_push($membersResponse, $member);
            }
        }

        $topFallasCritico = FallasInformadas::filtroBaseSinLinea($fechaFiltroDesde, $fechaFiltroHasta)
            ->selectRaw("count(*) as cantidad, fallas_informadas.falla_id, codigo_fallas.nombre")
            ->leftJoin('codigo_fallas', 'fallas_informadas.falla_id', 'codigo_fallas.id')
            ->whereRaw('fallas_informadas.es_critico=1')
            ->groupBy('fallas_informadas.falla_id', 'codigo_fallas.nombre')
            ->orderByDesc('cantidad')
            // ->limit(7)
            ->get();

        $topFallas = FallasInformadas::filtroBaseSinLinea($fechaFiltroDesde, $fechaFiltroHasta)
            ->selectRaw("count(*) as cantidad, fallas_informadas.falla_id, codigo_fallas.nombre")
            ->leftJoin('codigo_fallas', 'fallas_informadas.falla_id', 'codigo_fallas.id')
            ->whereRaw('(fallas_informadas.es_critico=0 or fallas_informadas.es_critico is null)')
            ->groupBy('fallas_informadas.falla_id', 'codigo_fallas.nombre')
            ->orderByDesc('cantidad')
            ->limit(7)
            ->get();

        $topLados = FallasInformadas::filtroBaseSinLinea($fechaFiltroDesde, $fechaFiltroHasta)
            ->with('lado')
            ->selectRaw("count(*) as cantidad, lado_partes.lado + ' - ' + tipo_partes.tipo as nombre")
            ->leftJoin('lado_partes', 'fallas_informadas.lado_id', 'lado_partes.id')
            ->leftJoin('tipo_partes', 'fallas_informadas.tipo_id', 'tipo_partes.id')
            // ->leftJoin('ep_etiquetas', 'fallas_informadas.qr', 'ep_etiquetas.qr')
            ->groupBy('lado_partes.lado', 'tipo_partes.tipo')
            ->orderByDesc('cantidad')
            // ->limit(5)
            ->get();

        $topUsers = FallasInformadas::filtroBaseSinLinea($fechaFiltroDesde, $fechaFiltroHasta)
            ->selectRaw("count(*) as cantidad, fallas_informadas.user_operacion_id, users.email as nombre")
            ->whereNotNull('fallas_informadas.user_operacion_id')
            ->groupBy('fallas_informadas.user_operacion_id', 'users.email')
            ->orderByDesc('cantidad')
            ->limit(7)
            ->get();

        $topOperaciones = FallasInformadas::filtroBaseSinLinea($fechaFiltroDesde, $fechaFiltroHasta)
            ->selectRaw("count(*) as cantidad, fallas_informadas.user_operacion_id, linea_operaciones.nombre")
            ->leftJoin('linea_operaciones', 'fallas_informadas.operacion', 'linea_operaciones.id')
            ->groupBy('fallas_informadas.user_operacion_id', 'linea_operaciones.nombre')
            ->orderByDesc('cantidad')
            // ->limit(5)
            ->get();

        $fallas = FallasInformadas::with(['operador', 'falla', 'lado', 'tipo', 'etiqueta'])
            // ->where('turno', $turno)
            // ->where('linea', $linea)
            ->where(function ($q) use ($fechaFiltroDesde, $fechaFiltroHasta) {
                $q->where('created_at', '>=', $fechaFiltroDesde->format('Y-m-d') . ' 06:00:00');
                $q->where('created_at', '<=', $fechaFiltroHasta->format('Y-m-d') . ' 01:00:00');
            })
            ->get();

        $historialMes = FallasInformadas::selectRaw('count(*) as cantidad, month(created_at) as mes, year(created_at) as anio')
            // ->where('turno', $turno)
            // ->where('linea', $linea)
            ->where('tipo_falla', 'E')
            ->groupByRaw('month(created_at)')
            ->groupByRaw('year(created_at)')
            ->orderByRaw('month(created_at)')
            ->get();

        $fechaInicioMesesAnteriores = clone $fechaInicioMes;
        $fechaInicioMesesAnteriores->sub(new DateInterval("P3M"));

        // $historialMesDiario = [];

        // $intervalo = new DateInterval('P1D'); // Periodo de 1 día
        // $periodo   = new DatePeriod($fechaInicioMesesAnteriores, $intervalo, $fechaFinMes);

        // $tiposDefectos = ['E', 'I'];

        // foreach ($periodo as $fecha) {
        //     // echo $fecha->format("Y-m-d") . PHP_EOL;
        //     $diaBuscado = $fecha->format('d');
        //     $mesBuscado = $fecha->format('m');
        //     $fechaSiguiente = (clone $fecha)->modify('+1 day');
        //     $cantidadASumar = 1;

        //     $encontrado = false;

        //     $cantidadASumar = FallasInformadas::selectRaw('count(*) as cantidad')
        //         // ->where('turno', $turno)
        //         // ->where('linea', $linea)
        //         ->where('tipo_falla', 'E')
        //         ->whereBetween('created_at', [$fecha->format('Y-m-d') . ' 06:00:00', $fechaSiguiente->format('Y-m-d') . ' 00:50:00'])
        //         ->first();

        //     foreach ($historialMesDiario as &$item) {   // usar & para modificar directamente
        //         if ($item["dia"] === $diaBuscado && $item["mes"] === $mesBuscado) {
        //             $item["cantidad"] += $cantidadASumar->cantidad;
        //             $encontrado = true;
        //             break; // ya lo encontré, salgo del loop
        //         }
        //     }
        //     unset($item); // buena práctica al usar referencias en foreach

        //     if (!$encontrado) {
        //         // si no existe, lo agrego
        //         if ($cantidadASumar->cantidad > 0) {
        //             $historialMesDiario[] = [
        //                 "dia"       => $diaBuscado,
        //                 "mes"       => $mesBuscado,
        //                 "cantidad"  => $cantidadASumar->cantidad,
        //             ];
        //         }
        //     }
        // }

        // $historialMesComparacion = FallasInformadas::selectRaw('count(*) as cantidad')
        //     // ->where('turno', $turno)
        //     // ->where('linea', $linea)
        //     ->where(function ($q) use ($fecha) {
        //         $inicio = (clone $fecha)->modify('first day of last month');
        //         $fin = (clone $fecha)->modify('-1 month');

        //         $q->where('fallas_informadas.created_at', '>=', $inicio->format('Y-m-d') . ' 06:00:00');
        //         $q->where('fallas_informadas.created_at', '<=', $fin->format('Y-m-d') . ' 01:00:00');
        //     })
        //     ->get();

        $finalResponse = [];


        array_push(
            $finalResponse,
            [
                'fallas'            => $fallas ? $fallas->toArray() : [],
                'topUsers'          => $topUsers,
                'topFallas'         => $topFallas,
                'topFallasCritico'  => $topFallasCritico,
                'topOperaciones'    => $topOperaciones,
                'historial'         => $historialMes,
                'historialDiario'   => [], //$historialMesDiario,
                'mesAnterior'       => 0, // $historialMesComparacion ? $historialMesComparacion[0] : 0,
                // 'topModelos'        => $topModelos ? $topModelos->toArray() : [],
                'topLados'          => $topLados,
                // 'turno'             => $turno,
                'members'           => $membersResponse,
                'operaciones'       => [] //$operacionesResponse,
                // 'historialMeses'    => $historialMesesAnteriores
            ]
        );

        return $finalResponse[0];
    }
}
