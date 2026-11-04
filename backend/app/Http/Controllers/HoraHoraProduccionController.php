<?php

namespace App\Http\Controllers;

use App\Models\EpEtiqueta;
use App\Models\FallasInformadas;
use App\Models\HoraHoraProduccion;
use App\Models\HoraHoraSoporte;
use App\Models\ModeloSublineTiempo;
use App\Models\Partes;
use App\Models\PlanLinea;
use App\Models\ProduccionParada;
use App\Models\Scrap;
use App\Models\TempModelosProduccionHoraHora;
use DateInterval;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HoraHoraProduccionController extends Controller {

    /**
     * Redondeo distribuido por "resto mayor".
     * - $valores: array de números (por franja).
     * - $modo: modo de redondeo del TOTAL (UP/DOWN/EVEN/ODD). Por defecto HALF_UP (.5 sube).
     * Retorna: array de enteros por franja cuya suma == round(sum($valores)).
     */
    private function roundDistributed(array $valores, string $modo = 'UP'): array {
        $map = [
            'UP'   => PHP_ROUND_HALF_UP,
            'DOWN' => PHP_ROUND_HALF_DOWN,
            'EVEN' => PHP_ROUND_HALF_EVEN,
            'ODD'  => PHP_ROUND_HALF_ODD,
        ];
        $mode = $map[strtoupper($modo)] ?? PHP_ROUND_HALF_UP;

        $n = count($valores);
        if ($n === 0) return [];

        // 1) Floors y remainders
        $floors = [];
        $rema   = [];
        $sumFloors = 0;
        $sum     = 0.0;

        foreach ($valores as $i => $v) {
            $f = (int) floor($v);
            $r = $v - $f;
            $floors[$i] = $f;
            $rema[$i]   = $r;
            $sumFloors += $f;
            $sum       += $v;
        }

        // 2) Total objetivo
        $target = (int) round($sum, 0, $mode);
        $faltan = $target - $sumFloors; // cuántas unidades tengo que repartir

        if ($faltan <= 0) {
            // Si sobran floors, recorta por remainders más chicos (poco común con SUM round-half-up)
            if ($faltan < 0) {
                // Ordenar por remainder asc (los más chicos pierden 1)
                $idx = array_keys($valores);
                usort($idx, function ($a, $b) use ($rema, $valores) {
                    if ($rema[$a] == $rema[$b]) {
                        // desempate: valor mayor conserva; menor pierde
                        if ($valores[$a] == $valores[$b]) return $a <=> $b;
                        return $valores[$a] <=> $valores[$b];
                    }
                    return $rema[$a] <=> $rema[$b];
                });
                $quita = min(-$faltan, count($idx));
                for ($k = 0; $k < $quita; $k++) {
                    $i = $idx[$k];
                    $floors[$i] = max(0, $floors[$i] - 1);
                }
            }
            return array_values($floors);
        }

        // 3) Reparto por mayores remainders (y de ser necesario, por mayor valor)
        $idx = array_keys($valores);
        usort($idx, function ($a, $b) use ($rema, $valores) {
            if ($rema[$a] == $rema[$b]) {
                // desempate: el mayor valor primero
                if ($valores[$a] == $valores[$b]) return $a <=> $b; // estable
                return $valores[$b] <=> $valores[$a];
            }
            return $rema[$b] <=> $rema[$a]; // remainder desc
        });

        $asigna = min($faltan, count($idx));
        for ($k = 0; $k < $asigna; $k++) {
            $i = $idx[$k];
            $floors[$i] += 1;
        }
        // Log::alert($floors);
        return array_values($floors);
    }

    private function actualizaPlanHoraHora($linea, $turno, $fecha, $nombreTurno) {
        $turnoActual = getTurnoActual();

        $fechaActual = new DateTime();
        $dayOfWeek = date('w', strtotime($fecha));
        //Si es viernes, debo invertir los turnos

        $turno = getFranjaHoraria();
        if ($turnoActual != $nombreTurno) {
            if ($dayOfWeek == 5 && $fechaActual->format('Y-m-d') != $fecha) {
                $turno = $turno;
            } else {
                $turno = $turno == "M" ? "T" : "M";
            }
        }

        HoraHoraProduccion::where('fecha', $fecha)->where('turno_nombre', $nombreTurno)->where('linea', $linea)->where('tipo', 'QC')->delete();

        $planLinea = PlanLinea::select('hora_desde', 'hora_hasta', 'plan', 'plan_acumulado')
            ->where('linea_id', $linea)
            ->where('turno', $turno)
            ->orderBy('orden', 'ASC')->get();

        if (!$planLinea) {
            return $this->setResponse([], 'No hay un plan para la linea ' . $linea, true);
        }

        $orden = 0;
        $acumulado = 0;
        $diferenciaAcum = 0;

        foreach ($planLinea as $plan) {
            $modelos = [];
            $modelo = null;
            $diferencia = 0;
            $intervalo = substr($plan->hora_desde, 0, 5) . ' - ' . substr($plan->hora_hasta, 0, 5);

            $tiemposDesde = explode(":", $plan->hora_desde);
            $tiemposHasta = explode(":", $plan->hora_hasta);

            $fechaDesde = DateTime::createFromFormat('Y-m-d', $fecha);
            $fechaHasta = DateTime::createFromFormat('Y-m-d', $fecha);

            if ($turno == "T" && $plan->hora_desde == '00:00:00.0000000') {
                $fechaDesde->add(new DateInterval('P1D'));
                $fechaHasta->add(new DateInterval('P1D'));
            }

            $fechaDesde->setTime(intval($tiemposDesde[0]), intval($tiemposDesde[1]), explode(".", $tiemposDesde[2])[0]);
            $fechaHasta->setTime(intval($tiemposHasta[0]), intval($tiemposHasta[1]), explode(".", $tiemposHasta[2])[0]);

            // Log::alert($fechaDesde->format('Y-m-d H:i:s'));
            if ($linea == 1) {
                $andon = EpEtiqueta::selectRaw('modelo,modelo_id, count(*) as cantidad')->where('linea_real', intval($linea))
                    ->whereBetween('hora_validacion_carab', [$fechaDesde->format('Y-m-d H:i:s'), $fechaHasta->format('Y-m-d H:i:s')])
                    ->groupBy('modelo')->groupBy('modelo_id')
                    ->get();
            } else {
                //SIN CARA B
                if ($linea == 3) {
                    $andon = EpEtiqueta::selectRaw('modelo,modelo_id, count(*) as cantidad')->where('linea_real', intval($linea))
                        // ->where('modelo', '<>', 'STL1')
                        ->whereBetween('hora_validacion', [$fechaDesde->format('Y-m-d H:i:s'), $fechaHasta->format('Y-m-d H:i:s')])
                        ->where('hora_validacion_carab', null)
                        ->groupBy('modelo')->groupBy('modelo_id')
                        ->get();

                    // $modelosControlados = EpEtiqueta::selectRaw('modelo')->where('linea_real', intval($linea))
                    //     ->whereBetween('hora_validacion', [$fechaDesde->format('Y-m-d H:i:s'), $fechaHasta->format('Y-m-d H:i:s')])
                    //     ->where('hora_validacion_carab', null)
                    //     ->orderBy('hora_validacion', 'ASC')
                    //     ->get();
                } else {
                    $andon = EpEtiqueta::selectRaw('modelo,modelo_id, count(*) as cantidad')->where('linea_real', intval($linea))
                        ->whereBetween('hora_validacion', [$fechaDesde->format('Y-m-d H:i:s'), $fechaHasta->format('Y-m-d H:i:s')])
                        ->groupBy('modelo')->groupBy('modelo_id')
                        ->get();
                }
            }

            $andonReal = 0;

            foreach ($andon as $a) {

                $partes = Partes::where('modelo_id', $a->modelo_id)
                    ->where(function ($q) {
                        $q->where('tipo_id', 1);
                        $q->orWhere('tipo_id', 2);
                        $q->orWhere('tipo_id', 9);
                    })
                    ->get();

                if ($partes) {
                    $andonReal = $andonReal + ($a->cantidad / $partes->count());
                } else {
                    $andonReal = $andonReal + ($a->cantidad / 4);
                }
            }

            if ($andonReal > 0) {
                // $andonReal = $this->redondearMasProximo($andonReal);
                $andonReal = $this->roundDistributed([$andonReal])[0];
            }

            $acumulado = $acumulado + ($andonReal ? $andonReal : 0);

            $piezasReparadas = FallasInformadas::with('etiqueta')->where('linea', intval($linea))->where('tipo_linea', 'MAIN')
                ->where('estado', '=', 'RETRABAJADO')
                ->whereBetween('created_at', [$fechaDesde->format('Y-m-d H:i:s'), $fechaHasta->format('Y-m-d H:i:s')])
                ->get();

            if (is_null($modelo)) {
                foreach ($andon as $p) {
                    if (!in_array(trim($p->modelo), $modelos, true)) {  // importante el tercer parámetro true
                        array_push($modelos, trim($p->modelo));
                    }
                }
            }

            $piezasScrap = FallasInformadas::with('etiqueta')->where('linea', intval($linea))->where('tipo_linea', 'MAIN')
                ->where('estado', '=', 'SCRAP')
                ->whereBetween('created_at', [$fechaDesde->format('Y-m-d H:i:s'), $fechaHasta->format('Y-m-d H:i:s')])
                ->get();

            $diferencia = ($andonReal ? $andonReal : 0) - $plan->plan;
            $diferenciaAcum = $diferenciaAcum + $diferencia;

            HoraHoraProduccion::updateOrCreate(
                [
                    'fecha'                 => $fecha,
                    'turno'                 => $turno,
                    'turno_nombre'          => $nombreTurno,
                    'linea'                 => $linea,
                    'intervalo'             => $intervalo,
                    'tipo'                  => 'QC'
                ],
                [
                    'fecha'                 => $fecha,
                    'turno'                 => $turno,
                    'turno_nombre'          => $nombreTurno,
                    'linea'                 => $linea,
                    'intervalo'             => $intervalo,
                    'plan'                  => $plan->plan,
                    'plan_acumulado'        => $plan->plan_acumulado,
                    'modelo'                => count($modelos) > 0 ? implode(" - ", $modelos) : $modelo,
                    'real'                  => $andonReal ? $andonReal  : null,
                    'acumulado'             => $acumulado,
                    'diferencia'            => $diferencia, //$andon ? $andon->diferencia : null,
                    'diferencia_acumulado'  => $diferenciaAcum, //F $andon ? $andon->diferencia_acumulada : null,
                    'piezas_reparadas'      => $piezasReparadas?->count(),
                    'piezas_scrap'          => $piezasScrap?->count(),
                    'tipo'                  => 'QC'
                ]
            );

            $orden = $orden + 1;
        }
    }

    // private function redondearMasProximo(float $n): int {
    //     return (int) round($n, 0, PHP_ROUND_HALF_UP);
    // }

    public function actualizarPlanHoraHoraSegunAndon(Request $request) {
        $turno = $request->turno;
        $linea = $request->linea;
        $fecha = $request->fecha;
        $nombreTurno = $request->nombreTurno;

        $this->actualizaPlanHoraHora($linea, $turno, $fecha, $nombreTurno);
        $data = HoraHoraProduccion::where('fecha', $fecha)->where('turno', $turno)->where('linea', $linea)->where('tipo', 'QC')->get();

        return $this->setResponse($data?->toArray());
    }

    public function desdeExcel(Request $request) {

        foreach ($request->items as $item) {
            $fecha = $item['fecha'];
            $turno = "M";
            $linea = intval(str_replace("M", "", $item['linea']));
            $intervalo = $item['intervalo'];
            $plan = intval($item['plan']);
            $acumulado = intval($item['acumulado']);
            $planAcumulado = $item['plan_acumulado'];

            try {
                if (strlen($intervalo) < 13 && !is_null($intervalo) && $intervalo != '') {
                    $intervalos = explode("-", $intervalo);

                    $horas = explode(":", $intervalos[0]);
                    $horas2 = explode(":", $intervalos[1]);

                    $intervalo = str_pad(trim($horas[0]), 2, "0", STR_PAD_LEFT) . ":" . str_pad(trim($horas[1]), 2, "0", STR_PAD_LEFT) . " - " . str_pad(trim($horas2[0]), 2, "0", STR_PAD_LEFT) . ":" . str_pad(trim($horas2[1]), 2, "0", STR_PAD_LEFT);
                }
            } catch (\Throwable $th) {
                // Log::alert("HoraHoraProduccionController::desdeExcel: " . $th->getMessage());
                // Log::alert("HoraHoraProduccionController::desdeExcel: " . $intervalo);
            }

            try {
                HoraHoraProduccion::updateOrCreate(
                    [
                        'fecha'                 => $fecha,
                        'turno'                 => $turno,
                        'turno_nombre'          => $item['turno_nombre'],
                        'linea'                 => $linea,
                        'intervalo'             => $intervalo,
                        'tipo'                  => 'MF'
                    ],
                    [
                        'fecha'                 => $fecha,
                        'turno'                 => $turno,
                        'turno_nombre'          => $item['turno_nombre'],
                        'linea'                 => $linea,
                        'intervalo'             => $intervalo,
                        'plan'                  => $plan,
                        'plan_acumulado'        => $planAcumulado,
                        'modelo'                => $item['modelo'],
                        'real'                  => $item['real'],
                        'acumulado'             => $acumulado,
                        'diferencia'            => $item['dif'],
                        'diferencia_acumulado'  => $item['dif_acumulada'],
                        'piezas_reparadas'      => intval($item['piezas_reparadas']),
                        'piezas_scrap'          => intval($item['piezas_scrap']),
                        'tipo'                  => 'MF'
                    ]
                );
            } catch (\Throwable $th) {
                //throw $th;
            }

            $this->verificaParadasDesdeExcel($item, $turno);
        }

        foreach ($request->modelos as $modelo) {
            $fondo = "";
            $fondoActual = "";
            $actual = floatval($modelo['actual']);
            $plan = floatval(str_replace("/", "", $modelo['plan']));

            if ($modelo['tipo'] == "volumen") {
                if ($plan > 0) {
                    if (($actual / $plan) < 0.93) {
                        $fondo = "#ff0000"; //ROJO
                    } else {
                        $fondo = "#92d050"; // VERDE
                    }
                } else {
                    $fondo = "#92d050"; // VERDE
                }

                if ($actual < (95 * $plan / 100)) {
                    $fondoActual = "#ff0000"; //ROJO
                } else if ($actual == $plan) {
                    $fondoActual = "#00b050"; //VERDE
                } else {
                    $fondoActual = "#ffff00"; //AMARILLO
                }
            } else if ($modelo['tipo'] == "mix") {
                if ($actual < 1) {
                    $fondoActual = "#ff0000"; //ROJO
                } else {
                    $fondoActual = "#00b050"; //VERDE
                }
            } else if ($modelo['tipo'] == "tiempo_muerto") {
                if ($actual == 0) {
                    $fondoActual = "#00b050"; //VERDE
                } else if ($actual < $plan) {
                    $fondoActual = "#ffff00"; //AMARILLO
                } else {
                    $fondoActual = "#ff0000"; //ROJO
                }
            } else if ($modelo['tipo'] == "piezas_ok") {
                if ($actual == 0) {
                    $fondoActual = "#00b050"; //VERDE
                } else {
                    $fondoActual = "#ff0000"; //ROJO
                }
            }

            try {
                TempModelosProduccionHoraHora::updateOrCreate(
                    [
                        'shop'      => $modelo['linea'],
                        'turno'     => $modelo['turno_nombre'],
                        'fecha'     => $modelo['fecha'],
                        'tipo'      => $modelo['tipo']
                    ],
                    [
                        'shop'          => $modelo['linea'],
                        'turno'         => $modelo['turno_nombre'],
                        'fecha'         => $modelo['fecha'],
                        'plan'          => $plan,
                        'actual'        => $actual, //$actual < 0.1 ? 0 : $actual,
                        'tipo'          => $modelo['tipo'],
                        'fondo'         => $fondo,
                        'fondo_actual'  => $fondoActual,
                    ]
                );
            } catch (\Throwable $th) {
                //throw $th;
            }
        }

        return $this->setResponse([]);
    }

    private function creaParadaDesdeExcel($item, $turno, $minutos, $categoria, $grupo) {
        $intervalo = $item['intervalo'];

        if (strlen($intervalo) < 13) {
            $intervalos = explode("-", $intervalo);

            $horas = explode(":", $intervalos[0]);
            $horas2 = explode(":", $intervalos[1]);

            $intervalo = str_pad(trim($horas[0]), 2, "0", STR_PAD_LEFT) . ":" . str_pad(trim($horas[1]), 2, "0", STR_PAD_LEFT) . " - " . str_pad(trim($horas2[0]), 2, "0", STR_PAD_LEFT) . ":" . str_pad(trim($horas2[1]), 2, "0", STR_PAD_LEFT);
        }

        ProduccionParada::updateOrCreate(
            [
                'fecha'         => $item['fecha'],
                'turno_nombre'  => $item['turno_nombre'],
                'turno'         => $turno,
                'intervalo'     => $intervalo,
                'shop'          => $item['linea'],
                'minutos'       => $minutos,
                'categoria'     => $categoria,
                'grupo'         => $grupo,
            ],
            [
                'fecha'         => $item['fecha'],
                'turno_nombre'  => $item['turno_nombre'],
                'turno'         => $turno,
                'intervalo'     => $intervalo,
                'area'          => "COSTURA",
                'shop'          => $item['linea'],
                'minutos'       => $minutos,
                'categoria'     => $categoria,
                'grupo'         => $grupo,
                'contramedida'  => $item['contramedida'],
                'causa'         => null
            ]
        );
    }

    private function verificaParadasDesdeExcel($item, $turno) {

        // $minutos = intval($item['parada_rrhh_0']) + intval($item['parada_rrhh_0']) + intval($item['parada_rrhh_1']) + intval($item['parada_kzn_0']) + intval($item['parada_kzn_1'])
        //     + intval($item['parada_qc_0']) + intval($item['parada_qc_1']) + intval($item['parada_mh_0']) + intval($item['parada_mh_1'])
        //     + intval($item['parada_mtto_0']) + intval($item['parada_mtto_1']);

        // if ($minutos == 0 && $item['contramedida'] != '') {
        //     $this->creaParadaDesdeExcel($item, $turno, 0, "", "");
        // } else {

        try {
            if (intval($item['parada_rrhh_0']) > 0 && ($item['contramedida'] != '' && $item['contramedida'] != '-')) {
                $this->creaParadaDesdeExcel($item, $turno, intval($item['parada_rrhh_0']), "Ausentismo", "RRHH");
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        try {
            if (intval($item['parada_rrhh_1']) > 0 && ($item['contramedida'] != '' && $item['contramedida'] != '-')) {
                $this->creaParadaDesdeExcel($item, $turno, intval($item['parada_rrhh_1']), "Rotación", "RRHH");
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        try {
            if (intval($item['parada_kzn_0']) > 0 && ($item['contramedida'] != '' && $item['contramedida'] != '-')) {
                $this->creaParadaDesdeExcel($item, $turno, intval($item['parada_kzn_0']), "Cuellos de Botella", "KZN");
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        try {
            if (intval($item['parada_kzn_1']) > 0 && ($item['contramedida'] != '' && $item['contramedida'] != '-')) {
                $this->creaParadaDesdeExcel($item, $turno, intval($item['parada_kzn_1']), "Habilidad", "KZN");
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        try {
            if (intval($item['parada_qc_0']) > 0 && ($item['contramedida'] != '' && $item['contramedida'] != '-')) {
                $this->creaParadaDesdeExcel($item, $turno, intval($item['parada_qc_0']), "Defectos de Proveedor", "QC");
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        try {
            if (intval($item['parada_qc_1']) > 0 && ($item['contramedida'] != '' && $item['contramedida'] != '-')) {
                $this->creaParadaDesdeExcel($item, $turno, intval($item['parada_qc_1']), "Problemas de Calidad", "QC");
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        try {
            if (intval($item['parada_mh_0']) > 0 && ($item['contramedida'] != '' && $item['contramedida'] != '-')) {
                $this->creaParadaDesdeExcel($item, $turno, intval($item['parada_mh_0']), "Falta de Material en carga", "MH");
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        try {
            if (intval($item['parada_mh_1']) > 0 && ($item['contramedida'] != '' && $item['contramedida'] != '-')) {
                $this->creaParadaDesdeExcel($item, $turno, intval($item['parada_mh_1']), "Retraso en abastecimiento", "MH");
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        try {
            if (intval($item['parada_mtto_0']) > 0 && ($item['contramedida'] != '' && $item['contramedida'] != '-')) {
                $this->creaParadaDesdeExcel($item, $turno, intval($item['parada_mtto_0']), "Cambio de aguja", "MTTO");
            }
        } catch (\Throwable $th) {
            //throw $th;
        }

        try {
            if (intval($item['parada_mtto_1']) > 0 && ($item['contramedida'] != '' && $item['contramedida'] != '-')) {
                $this->creaParadaDesdeExcel($item, $turno, intval($item['parada_mtto_1']), "Falla en Máquina", "MTTO");
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
        // }
    }

    public function setSoporteTurno(Request $request) {

        // Log::alert("ASD");
        $turno = $request->turno;
        $linea = $request->linea;
        $fecha = $request->fecha;
        $soporte = intval($request->soporte);

        HoraHoraSoporte::updateOrCreate(
            [
                'turno' => $turno,
                'linea' => $linea,
                'fecha' => $fecha
            ],
            [
                'turno'     => $turno,
                'linea'     => $linea,
                'fecha'     => $fecha,
                'soporte'   => $soporte
            ]
        );

        return $this->setResponse([]);
    }

    public function getPlanHoraHora(Request $request) {

        $turno = $request->turno;
        $linea = $request->linea;
        $fecha = $request->fecha;
        $nombreTurno = $request->nombreTurno;
        $actualizaAndon =  $request?->actualiza || false;

        $fechaActual = new DateTime();
        $horaActual = $fechaActual->format('H');
        $minActual = $fechaActual->format('i');

        if (($horaActual >= 0 && $horaActual < 6) || ($horaActual == 6 && $minActual < 10)) {
            $fechaActual = $fechaActual->sub(new DateInterval("P1D"));
            $fechaActual->setTime(23, 0, 0);
        }

        if (!$fecha) {
            $fecha = $fechaActual->format("Y-m-d");
        } else if ($fecha == 'CHECK') {

            $dataTurnoActual  = getDataTurnoActual();

            $newFecha = (clone $fechaActual);
            $dayOfWeek = $fechaActual->format('N');

            if ($dayOfWeek == 1) {
                $newFecha->sub(new DateInterval('P3D'));
            } else {
                $newFecha->sub(new DateInterval('P1D'));
            }

            if ($dataTurnoActual['turno'] == 'M') {
                //SI EL ACTUAL ES MAÑANA, BUSCO LOS DATOS DEL OTRO TURNO EN LA FECHA ANTERIOR
                $fecha = $newFecha->format('Y-m-d');
            } else {
                $fecha = date('Y-m-d');
            }

            //VERIFICO SI TIENE ALGO DEL DIA ACTUAL
            // $existeHoy = HoraHoraProduccion::where('fecha', $fechaActual->format("Y-m-d"))
            //     ->where('turno_nombre', $nombreTurno)
            //     ->where('linea', $linea)
            //     ->where('tipo', 'QC')->first();

            // // $fecha = "2025-12-29";
            // if ($existeHoy) {
            //     $fecha = date('Y-m-d');
            // } else {
            //     //TOMO EL DIA DE AYER
            //     //SI ES LUNES, TOMO EL VIERNES

            //     //TOMO LA ULTIMA FECHA ACTIVA



            //     $newFecha = (clone $fechaActual);
            //     $dayOfWeek = $fechaActual->format('N');

            //     if ($dayOfWeek == 1) {
            //         $newFecha->sub(new DateInterval('P3D'));
            //     } else {
            //         $newFecha->sub(new DateInterval('P1D'));
            //     }

            //     $fecha = $newFecha->format('Y-m-d');

            //     $existeHoy = HoraHoraProduccion::where('fecha', $fechaActual->format("Y-m-d"))
            //         ->where('turno_nombre', $nombreTurno)
            //         ->where('linea', $linea)
            //         ->where('tipo', 'QC')->first();
            //     ///

            //     if (!$existeHoy) {
            //         $ultimaFecha = HoraHoraProduccion::selectRaw('max(fecha) as fecha')
            //             ->where('turno_nombre', $nombreTurno)
            //             ->where('linea', $linea)
            //             ->where('tipo', 'QC')->first();

            //         $ultimaFechaFechaObj = DateTime::createFromFormat('Y-m-d', $ultimaFecha->fecha);
            //         $fecha = $ultimaFechaFechaObj->format('Y-m-d');
            //     }
            // }
        }

        if ($actualizaAndon) {
            $this->actualizaPlanHoraHora($linea, $turno, $fecha, $nombreTurno);
        }

        $data = HoraHoraProduccion::where('fecha', $fecha)->where('turno_nombre', $nombreTurno)->where('linea', $linea)->where('tipo', 'QC')->get();

        // Fallback: si la consulta viene vacia (ej. M5) se recalcula automaticamente.
        if (!$actualizaAndon && !is_null($linea) && !is_null($nombreTurno) && $data->isEmpty()) {
            $this->actualizaPlanHoraHora($linea, $turno, $fecha, $nombreTurno);
            $data = HoraHoraProduccion::where('fecha', $fecha)->where('turno_nombre', $nombreTurno)->where('linea', $linea)->where('tipo', 'QC')->get();
        }

        foreach ($data as $d) {
            [$inicio, $fin] = explode(' - ', $d->intervalo);
            $fechaDesde = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' ' . $inicio);
            $fechaHasta = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' ' . $fin);
            $fechaDesdeManana = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' ' . $fin);
            $fechaHastaManana = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' ' . $fin);

            $fd = null;
            $fh = null;

            if ($inicio == '00:00') {
                $fd = $fechaDesdeManana;
            } else {
                $fd = $fechaDesde;
            }

            if ($fin == '00:45') {
                $fh = $fechaHastaManana;
            } else {
                $fh = $fechaHasta;
            }

            $piezasReparadas = FallasInformadas::selectRaw('count(*) as cantidad')
                ->where('turno', $nombreTurno)
                ->where('linea', $linea)
                ->where('tipo_falla', 'E')
                ->whereBetween('created_at', [$fd->format('Y-m-d H:i:s'), $fh->format('Y-m-d H:i:s')])
                ->first();

            HoraHoraProduccion::where('id', $d->id)->update(['piezas_reparadas' => $piezasReparadas ? $piezasReparadas->cantidad : 0]);
        }

        $data = HoraHoraProduccion::where('fecha', $fecha)->where('linea', $linea)->where('turno_nombre', $nombreTurno)->where('tipo', 'QC')->get();
        // $data = HoraHoraProduccion::where('fecha', $fecha)->where('linea', $linea)->where('turno_nombre', $nombreTurno)->where('tipo', 'LI')->get();

        foreach ($data as $d) {
            $paradas = ProduccionParada::selectRaw('sum(minutos) as cantidad, ltrim(rtrim(grupo)) as grupo')
                ->where('fecha', $fecha)->where('turno_nombre', $nombreTurno)->where('shop', 'M' . $linea)->where('intervalo', $d->intervalo)->groupBy('grupo')->get();

            foreach ($paradas as $parada) {
                $d[$parada->grupo] = intval($parada->cantidad);
            }
        }

        $paradas = ProduccionParada::selectRaw('sum(minutos) as cantidad,ltrim(rtrim(grupo)) as grupo,categoria ')->where('fecha', $fecha)->where('turno_nombre', $nombreTurno)->where('shop', 'M' . $linea)->groupBy('grupo', 'categoria')->get();
        $paradasDetallada = ProduccionParada::where('fecha', $fecha)->where('turno_nombre', $nombreTurno)->where('shop', 'M' . $linea)->get();

        $soporte = HoraHoraSoporte::where('turno', $turno)->where('linea', $linea)->where('fecha', $fecha)->first();

        return $this->setResponse([
            'data'              => $data?->toArray(),
            'soporte'           => $soporte,
            'paradas'           => $paradas ? $paradas->toArray() : [],
            'paradasDetalle'    => $paradasDetallada ? $paradasDetallada->toArray() : []
        ]);
    }

    private function recalcularHoraHora($turno, $linea, $fecha, $tipo = 'QC') {

        $data = HoraHoraProduccion::where('turno', $turno)->where('linea', $linea)->where('fecha', $fecha)->where('tipo', $tipo)->get();

        // Log::alert(json_encode($data, JSON_PRETTY_PRINT));
        $esLineaPorTaktTime = ($linea == "7" || $linea == "8");

        $acumulado = 0;
        $realAcumulado = 0;
        $pos = 0;
        $planAcumulado = 0;

        $intervaloAnterior = null;
        $tiempoRealAnterior = "";

        foreach ($data as $d) {
            $minutosAtraso = 0;

            if ($esLineaPorTaktTime) {
                $intervalo = $this->obtenerIntervalo($turno, $pos == 0 ? null : $intervaloAnterior, $d->modelo, $d->plan, $linea);
                $planAcumulado = $planAcumulado + intval($d->plan);

                //Actulizo el intervalo de las paradas
                ProduccionParada::where('intervalo', $d->intervalo)->where('fecha', $fecha)->where('shop', 'M' . $linea)->where('turno', $turno)->update(['intervalo' => $intervalo['intervalo']]);
                if ($d->real_tiempo) {
                    $diferencia = $intervalo['inicio']->diff($intervalo['fin']);
                    $diffPlan = ($diferencia->days * 24 * 60) + ($diferencia->h * 60) + $diferencia->i + ($diferencia->s / 60);

                    $tiempos = explode(":", $d->real_tiempo);

                    $finReal = $intervalo['fin'];
                    $finReal->setTime(intval($tiempos[0]), intval($tiempos[1]));
                    $inicioReal = $intervalo['inicio'];
                    if ($pos > 0) {
                        $tiempos = explode(":", $tiempoRealAnterior);
                        $inicioReal->setTime(intval($tiempos[0]), intval($tiempos[1]));

                        // $diferencia = $intervalo['inicio']->diff($finReal);
                    }

                    $diferencia = $inicioReal->diff($finReal);
                    $diffReal = ($diferencia->days * 24 * 60) + ($diferencia->h * 60) + $diferencia->i + ($diferencia->s / 60);

                    $minutosAtraso = ceil($diffReal - $diffPlan);
                }
            } else {
                $intervalo = $this->obtenerIntervalo($turno, $pos == 0 ? null : $intervaloAnterior, $d->modelo, $d->plan, $linea);
                $planAcumulado = $d->plan_acumulado;
            }

            if ($pos == 0) {
                HoraHoraProduccion::where('id', $d->id)
                    ->update([
                        'acumulado'             => intval($d->real),
                        'diferencia'            => intval($d->real) - intval($d->plan),
                        'diferencia_acumulado'  => intval($d->real) - $planAcumulado,
                        // 'intervalo'             => $intervalo['intervalo'],
                        'plan_acumulado'        => $planAcumulado,
                        'minutos_atraso'        => $minutosAtraso
                    ]);
            } else {
                HoraHoraProduccion::where('id', $d->id)
                    ->update([
                        'acumulado'             => (intval($realAcumulado) + intval($d->real)),
                        'diferencia'            => intval($d->real) - intval($d->plan),
                        'diferencia_acumulado'  => $acumulado + (intval($d->real) - intval($d->plan)),
                        // 'intervalo'             => $intervalo['intervalo'],
                        'plan_acumulado'        => $planAcumulado,
                        'minutos_atraso'        => $minutosAtraso
                    ]);
            }

            $realAcumulado = $realAcumulado + intval($d->real);
            $acumulado = (intval($d->real) - intval($d->plan)) + intval($acumulado);
            $pos++;
            $intervaloAnterior = $intervalo['intervalo'];
            $tiempoRealAnterior = $d?->real_tiempo;
        }
    }

    public function setPlanHoraHora(Request $request) {

        // Log::alert($request);

        $esLineaPorTaktTime = ($request->linea == "7" || $request->linea == "8");

        $modelos = $request->modelo;
        if (is_array($modelos)) {
            if ($esLineaPorTaktTime) {
                $modelo = $modelos[0];
            } else {
                $modelo = str_replace(",", " - ", implode(",", $modelos));
            }
        } else {
            $modelo = $modelos;
        }

        $tiempoReal = $request?->real_tiempo;

        if ($request->id) {
            $data = HoraHoraProduccion::where('id', intval($request->id) - 1)->where('turno', $request->turno)->where('fecha', $request->fecha)->where('linea', $request->linea)->where('tipo', 'LI')->first();
            HoraHoraProduccion::where('id', $request->id)
                ->update([
                    'plan'                  => $request->plan,
                    'modelo'                => $modelo,
                    'real'                  => $request->real,
                    'acumulado'             => !$data ? $request->real : (intval($data->acumulado) + intval($request->real)),
                    'diferencia'            => intval($request->real) - intval($request->plan),
                    'diferencia_acumulado'  => !$data ? intval($request->real) - intval($request->plan) : (intval($request->real) - intval($request->plan)) + intval($data->diferencia_acumulado),
                    'piezas_reparadas'      => $request->piezas_reparadas,
                    'piezas_scrap'          => $request->piezas_scrap,
                    'real_tiempo'           => $tiempoReal
                ]);
        } else {
            $data = HoraHoraProduccion::where('turno', $request->turno)
                ->where('fecha', $request->fecha)
                ->where('linea', $request->linea)
                ->where('tipo', 'LI')
                ->orderBy('id', 'DESC')
                ->first();

            //Obtengo el tiempo de acuerdo al taktime del modelo
            $intervalo = $this->obtenerIntervalo($request->turno, $data?->intervalo, $modelo, $request->plan, $request->linea)['intervalo'];

            HoraHoraProduccion::create([
                'fecha'                 => $request->fecha,
                'turno'                 => $request->turno,
                'linea'                 => $request->linea,
                'modelo'                => $modelo,
                'plan'                  => $request->plan,
                'plan_acumulado'        => !$data ? $request->plan : (intval($data->plan_acumulado) + intval($request->plan)),
                'real'                  => $request->real,
                'acumulado'             => !$data ? $request->real : (intval($data->acumulado) + intval($request->real)),
                'diferencia'            => intval($request->real) - intval($request->plan),
                'diferencia_acumulado'  => !$data ? intval($request->real) - intval($request->plan) : (intval($request->real) - intval($request->plan)) + intval($data->diferencia_acumulado),
                'piezas_reparadas'      => $request->piezas_reparadas,
                'piezas_scrap'          => $request->piezas_scrap,
                'intervalo'             => $intervalo,
                'real_tiempo'           => $tiempoReal,
                'tipo'                  => 'LI'
            ]);
        }

        $this->recalcularHoraHora($request->turno, $request->linea, $request->fecha, 'LI');

        return $this->setResponse(HoraHoraProduccion::where('turno', $request->turno)->where('linea', $request->linea)->where('fecha', $request->fecha)->where('tipo', 'LI')->get()->toArray());
    }

    private function obtenerIntervalo($turno, $intervalo, $nombreModelo, $plan, $linea) {

        $modelo = ModeloSublineTiempo::where('id_registro', 'M' . $linea . $nombreModelo)->first();

        $fechaInicio = new DateTime();
        $fechaFin = new DateTime();
        $minutos = 0;
        $segundos = 0;

        if (!$intervalo) {
            if ($turno = 'M') {
                $fechaInicio->setTime(6, 12, 0);
                $fechaFin->setTime(6, 12, 0);
            } else {
                $fechaInicio->setTime(15, 40, 0);
            }
        } else {
            $fechas = explode("-", $intervalo);
            $horas = explode(":", $fechas[1]);

            $fechaInicio->setTime(intval($horas[0]), intval($horas[1]));
            $fechaFin->setTime(intval($horas[0]), intval($horas[1]));
        }

        if ($modelo) {
            $tiempoSets = explode(":", $modelo->tiempo);
            $minutos = $plan * intval($tiempoSets[1]);
            $segundos = $plan * intval($tiempoSets[2]);
        }

        $newIntervalo = new DateInterval('PT' . ceil($minutos) . 'M' . ceil($segundos) . 'S');

        $fechaFin->add($newIntervalo);

        return [
            'intervalo' => $fechaInicio->format('H:i') . ' - ' . $fechaFin->format('H:i'),
            'inicio'    => $fechaInicio,
            'fin'       => $fechaFin
        ];
        // return $fechaInicio->format('H:i') . ' - ' . $fechaFin->format('H:i');
    }
}
