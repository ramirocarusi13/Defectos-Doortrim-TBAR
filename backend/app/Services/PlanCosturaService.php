<?php

namespace App\Services;

use App\Enums\DaysOfWeek;
use App\Http\Depositos;
use App\Http\Kanban;
use App\Http\Stock;
use App\Http\WmsUnidades;
use App\Models\Lineas;
use App\Models\LogPlanCostura;
use App\Models\Modelos;
use App\Models\ModeloSublineTiempo;
use App\Models\PlanCostura;
use DateInterval;
use DateTime;
use Illuminate\Support\Facades\Log;

class PlanCosturaService {
    private $ocupacionDeLineas = [];
    private $cortesPlaneados = [];

    static function getFechaAPlanificar($fechaAPlanificar = null): DateTime {

        if (!$fechaAPlanificar) {
            $dayOfWeek = date('N');
            $fechaAPlanificar = new DateTime();
        } else {
            $dayOfWeek = $fechaAPlanificar->format('N');
        }


        if ($dayOfWeek == DaysOfWeek::DOMINGO) {
            $fechaAPlanificar->add(new DateInterval('P1D'));
        }

        return $fechaAPlanificar;
    }

    private function getPlanCosturaRango(DateTime $fecha, DateTime $fecha2, DateTime|null $fecha3) {
        $data = PlanCostura::selectRaw('modelo,linea,SUM(tm) as tm,SUM(tt) as tt')
            ->where(function ($q) use ($fecha, $fecha2, $fecha3) {
                $q->where('fecha', '=', $fecha->format('d/m/Y'));
                $q->orWhere('fecha', '=', $fecha2->format('d/m/Y'));

                if (!is_null($fecha3)) {
                    $q->orWhere('fecha', '=', $fecha3->format('d/m/Y'));
                }
            })
            ->where(function ($q) {
                $q->where('tm', '>', 0);
                $q->orWhere('tt', '>', 0);
            })
            ->orderBy('linea')
            ->groupBy('modelo')
            ->groupBy('linea')
            ->get();

        return $data;
    }

    private function sumarTiempo($tiempo, $cantidad = 1) {
        $minutos = 0;
        $horas = 0;
        $tmpMinutos = 0;

        for ($i = 0; $i < $cantidad; $i++) {
            $tiempos = explode(":", $tiempo);

            $minutos = $minutos + intval($tiempos[1]);
            $horas = $horas + intval($tiempos[0]);
        }

        if ($minutos >= 60) {
            $tmpMinutos = $minutos / 60;

            $horas = $horas + intval($tmpMinutos);
            $minutos = $minutos + intval($tmpMinutos - intval($tmpMinutos));
        }

        if ($minutos == 60) {
            $horas = $horas + 1;
            $minutos = 0;
        }

        return str_pad($horas, 2, "0", STR_PAD_LEFT) . ':' . str_pad($minutos, 2, "0", STR_PAD_LEFT);
    }

    private function sumaTiempoALinea($ocupacion, $tiempo, $linea) {

        $tiempoActual = explode(":", $ocupacion[$linea]);
        $tiempos = explode(":", $tiempo);

        $minutos = intval($tiempoActual[1]) + intval($tiempos[1]);
        $horas = intval($tiempoActual[0]) + intval($tiempos[0]);

        if ($minutos >= 60) {
            $minutos = $minutos / 60;
            $horas = $horas + intval($minutos);
            $minutos = intval(($minutos - intval($minutos)) * 60);
        }


        $ocupacion[$linea] = str_pad($horas, 2, "0", STR_PAD_LEFT) . ':' . str_pad($minutos, 2, "0", STR_PAD_LEFT);
        return $ocupacion;
    }

    public function getCortesARealizar(DateTime $fechaAPlanificar): array {
        //TOMO 2 DíAS PARA PLANIFICAR LOS CORTES
        $fechaSiguiente = new DateTime();
        $fechaSiguiente->add(new DateInterval(('P1D')));
        $fechaSiguiente = $this->getFechaAPlanificar($fechaSiguiente);
        $fechaSiguiente2 = null;

        $dayOfWeek = $fechaSiguiente->format('N');

        //Si es domingo
        if ($dayOfWeek == DaysOfWeek::DOMINGO) {
            $fechaSiguiente->add(new DateInterval(('P1D')));
        } else if ($dayOfWeek == DaysOfWeek::SABADO) {
            //SI ESTOY PLANIFICANDO UN VIERNES, TOMO EL SABADO Y EL LUNES A PEDIDO DE SEBA
            $fechaSiguiente2 = new DateTime();
            $fechaSiguiente2->add(new DateInterval('P3D'));
        }

        $plans = $this->getPlanCosturaRango($fechaAPlanificar, $fechaSiguiente, $fechaSiguiente2);

        if (!$plans) {
            return 'No hay plan para la fecha indicada : ' . $fechaAPlanificar->format('d/m/Y');
        }

        $cortesPlanificados = [];

        $ocupacionLinea = [
            '1' => '00:00',
            '2' => '00:00',
            '3' => '00:00',
            '4' => '00:00',
            '5' => '00:00',
            '6' => '00:00',
            // '7' => '00:00',
            // '8' => '00:00',
            // '9' => '00:00',
            '10' => '00:00',
            '11' => '00:00',
        ];

        //Si hay plan, recorro cada modelo, verifico buffer y cuanto requiero, para ver cuantos cortes tengo que hacer
        foreach ($plans as $plan) {
            $linea = Lineas::where('codigo', $plan->linea)->first();
            $modelo = Modelos::withCount(['enBuffer', 'enBufferCorte', 'enProduccion', 'enPlan'])->where('nombre',  $plan->modelo)->first();

            if (!$modelo) {
                $mod = '';
                //PUEDE SER UN MODELO DE LA M11
                if ($plan->modelo == 'HRDH') {
                    $mod = 'HRDH-C';
                } else if ($plan->modelo = 'HFLD') {
                    $mod = 'HFLD-R';
                } else if ($plan->modelo = 'HRDL') {
                    $mod = 'HRDL-C';
                } else if ($plan->modelo = 'HRTL') {
                    $mod = 'HRTL-C';
                }

                $modelo = Modelos::withCount(['enBuffer', 'enBufferCorte', 'enProduccion', 'enPlan'])->where('nombre',  $mod)->first();
            }

            if (!$modelo) {
                break;
            }

            $enLogPlanCostura = LogPlanCostura::where('modelo', $modelo->nombre)
                ->where('fecha', date('Y-m-d'))
                ->get();

            $tiempoSubline = ModeloSublineTiempo::where('sublinea', 'S' . $linea->id)->where('modelo_id', $modelo->id)->first();

            $cantidadSetsModelo = intval($modelo->cantidad);
            $stockSetsBuffer = $modelo->en_buffer_count * $cantidadSetsModelo;

            if ($tiempoSubline) {
                $tiempoLineaBuffer = $this->sumarTiempo($tiempoSubline->tiempo, $cantidadSetsModelo); // $modelo->en_buffer_count);
            } else {
                $tiempoLineaBuffer = "00:00";
            }

            $ocupacionLinea = $this->sumaTiempoALinea($ocupacionLinea, $tiempoLineaBuffer, $linea->id);

            $requerido = intval($plan->tt) + intval($plan->tm);

            $setsPorCortar = $requerido - $stockSetsBuffer;
            $volumenSetsCorte = intval($modelo->volumen);

            //Verifico si hay planeados pero no cortados
            $pendienteStockArr = Kanban::getKanbanPendienteCorte($modelo);
            $enPlanCostura = 0;

            if ($enLogPlanCostura) {
                $enPlanCostura = is_null($enLogPlanCostura) ? 0 : (count($enLogPlanCostura) * $volumenSetsCorte);
            }

            $pendienteStock = count($pendienteStockArr);

            $stockSetsEnProduccion = $modelo->en_produccion_count * $cantidadSetsModelo;
            $setsPorCortar = $setsPorCortar - ($pendienteStock * $cantidadSetsModelo)  - $enPlanCostura - $stockSetsEnProduccion;
            $vecesACortar = 0;

            //Si lo solicitado es mayor a lo que tengo en buffer, me fijo cuantos cortes hay que realizar
            if ($setsPorCortar > 0) {
                if ($volumenSetsCorte > $setsPorCortar) {
                    //Corto 1 vez
                    $vecesACortar = 1;
                } else {
                    $vecesACortar = ceil($setsPorCortar / $volumenSetsCorte);
                }
            }

            if ($vecesACortar > 0) {

                $ciclo = range($cantidadSetsModelo, round($setsPorCortar < $cantidadSetsModelo ? $cantidadSetsModelo : $setsPorCortar), 1);
                foreach ($ciclo as $c) {
                    $ocupacionLinea = $this->sumaTiempoALinea($ocupacionLinea, $tiempoLineaBuffer, $linea->id);
                }

                $stockSetsBufferCorte = $modelo->en_buffer_corte_count * $cantidadSetsModelo;
                $stockSetsEnPlan = $modelo->en_plan_count * $cantidadSetsModelo;

                $enDollys = Stock::getStockConsolidadoModeloPorDeposito($modelo->nombre, WmsUnidades::KANBAN, null, null, [Depositos::DOLLYS, Depositos::TEMPORAL_A]);
                $enRacks = Stock::getStockConsolidadoModeloPorDeposito($modelo->nombre, WmsUnidades::KANBAN, Depositos::RACKS);

                $total = $stockSetsBufferCorte + $stockSetsBuffer + $stockSetsEnPlan + $stockSetsEnProduccion + ($enDollys * $cantidadSetsModelo) + ($enRacks * $cantidadSetsModelo);

                $cobertura = 0;
                $consumo = 0;

                if (intval($modelo->consumo) > 0) {
                    $cobertura = round($total / intval($modelo->consumo));
                    $consumo = intval($modelo->consumo);
                } else {
                    $cobertura = 0;
                    $consumo = 0;
                }

                //TOMO COMO REFERENCIA EL TIEMPO DE CORTE DE LECTRA 1
                $tLectra1 = $modelo->tiempoCorte("1");

                array_push($cortesPlanificados, [
                    'cobertura'         => $cobertura,
                    'modelo'            => $modelo->nombre,
                    'consumo'           => $consumo,
                    'demora'            => $tLectra1['time'],
                    'ms'                => $tLectra1['microseconds'],
                    'vecesACortar'      => $vecesACortar,
                    'cantidad'          => ($volumenSetsCorte / $cantidadSetsModelo),
                    'linea'             => $linea->id,
                    'stockBuffer'       => $stockSetsBuffer,
                    'requerido'         => $requerido,
                    'setsPorCortar'     => $setsPorCortar < 0 ? 0 : $setsPorCortar,
                    'volumenCorte'      => $volumenSetsCorte,
                    'orden'             => 0, //$orden,
                    'ordenSeteado'      => false,
                    'tiempoSubLine'     => $tiempoLineaBuffer,
                    'stockBufferCorte'  => $stockSetsBufferCorte,
                    'stockEnPlan'       => $stockSetsEnPlan,
                    'stockPendientes'   => $pendienteStockArr,
                    'stockAssy'         => $stockSetsEnProduccion
                ]);
            }
        }

        $this->ocupacionDeLineas = $ocupacionLinea;
        $this->cortesPlaneados = $cortesPlanificados;

        $cortesARealizar = [];
        foreach ($cortesPlanificados as $corte) {
            for ($i = 0; $i < intval($corte['vecesACortar']); $i++) {
                array_push($cortesARealizar, $corte);
            }
        }

        $this->armaOrdenOptimoCorte($cortesARealizar, $ocupacionLinea);

        //ORDENO LOS CORTES POR EL ORDEN SETEADO
        usort($this->cortesPlaneados, function ($item1, $item2) {
            return $item1['orden'] > $item2['orden'];
        });

        return $this->cortesPlaneados;
    }

    private function getUltimoOrdenCortesPlanificados($cortes): int {
        $orden = -1;
        $encontro = false;

        //RECORRO LOS CORTES Y OBTENGO EL ULTIMO ORDEN ASIGADO CUANDO ORDENSETEADO = TRUE
        foreach ($cortes as $corte) {
            if ($corte['ordenSeteado']) {
                if ($corte['orden'] > $orden) {
                    $orden = $corte['orden'];
                    $encontro = true;
                }
            }
        }

        if (!$encontro) {
            return 0;
        }
        return $orden + 1;
    }

    private function armaOrdenOptimoCorte(&$cortesPlanificados, $ocupacionDeLineas, $indexAnterior = -1) {
        asort($ocupacionDeLineas);
        $ocupacionLineas = $ocupacionDeLineas;
        $encontroCambio = false;
        $index = -1;

        foreach ($ocupacionLineas as $linea => $ocupacion) {
            if ($ocupacion != '00:00') {
                //OBTENGO EL CORTE MAS RAPIDO DE LA LÍNEA
                $index = $this->getCorteMasRapido($cortesPlanificados, $linea, $indexAnterior);

                if ($index >= 0) {
                    try {
                        if ($cortesPlanificados[$index]) {
                            $encontroCambio = true;
                            $cortesPlanificados[$index]['orden'] = $this->getUltimoOrdenCortesPlanificados($cortesPlanificados);
                            $cortesPlanificados[$index]['ordenSeteado'] = true;

                            $ocupacionLineas = $this->sumaTiempoALinea($ocupacionLineas, $cortesPlanificados[$index]['tiempoSubLine'], $linea);
                            break 1;
                        }
                    } catch (\Throwable $th) {
                        // Log::alert($th->getMessage());
                    }
                }
            }
        }

        if ($encontroCambio) {
            $this->armaOrdenOptimoCorte($cortesPlanificados, $ocupacionLineas, $index);
            return;
        }

        $this->ocupacionDeLineas = $ocupacionLineas;
        $this->cortesPlaneados = $cortesPlanificados;
    }

    private function getCorteMasRapido(&$cortes, $linea, $indexAnt = -1) {
        $index  = 0;
        $indexSel  = -1;
        $tiempo = 0;
        $modeloAnterior = null;

        if ($indexAnt >= 0) {
            $modeloAnterior = $cortes[$indexAnt]['modelo'];
        }

        try {
            if (!is_null($modeloAnterior)) {
                //Busco evitando el modelo anterior
                foreach ($cortes as $corte) {
                    $index = $index + 1;
                    if ($corte['modelo'] != $modeloAnterior && $corte['linea'] == $linea && $corte['ordenSeteado'] == false && $corte['vecesACortar'] > 0) {
                        if ($tiempo > 0) {
                            if ($tiempo > $corte['ms']) {
                                $indexSel = $index;
                                $tiempo = $corte['ms'];
                            }
                        } else {
                            $tiempo = $corte['ms'];
                            $indexSel = $index;
                        }
                    }
                }
            }

            if ($indexSel == -1) {
                //Busco evitando el modelo anterior
                foreach ($cortes as $corte) {
                    $index = $index + 1;
                    if ($corte['linea'] == $linea && $corte['ordenSeteado'] == false && $corte['vecesACortar'] > 0) {
                        if ($tiempo > 0) {
                            if ($tiempo > $corte['ms']) {
                                $indexSel = $index;
                                $tiempo = $corte['ms'];
                            }
                        } else {
                            $tiempo = $corte['ms'];
                            $indexSel = $index;
                        }
                    }
                }
            }

            return $indexSel - 1;
        } catch (\Throwable $th) {
            return -1;
        }
    }

    public function ejecutarPlanAutomaticamente() {

        $fechaAPlanificar = PlanCosturaService::getFechaAPlanificar();

        if ($fechaAPlanificar == null) {
            return 'El día no corresponde planificación';
        }

        $cortesPlanificados = $this->getCortesARealizar($fechaAPlanificar);
        $cortesPlanificados = $this->cortesPlaneados;

        foreach ($cortesPlanificados as $c) {
            $orden = 0;
            if (array_key_exists('modelo', $c)) {
                $orden = $orden + 1;
                LogPlanCostura::updateOrCreate([
                    'fecha'             => $fechaAPlanificar->format('Y-m-d'),
                    'modelo'            => $c['modelo']
                ], [
                    'fecha'              => $fechaAPlanificar->format('Y-m-d'),
                    'modelo'             => $c['modelo'],
                    'cortes_requeridos'  => 1,
                    'cant_buffer'        => $c['stockBuffer'],
                    'cant_assy'          => $c['stockAssy'],
                    'cant_corte'         => $c['stockBufferCorte'],
                    'cortes_ejecutados'  => 0,
                    'orden'              => $orden
                ]);
            }
        }

        return true;
    }
}
