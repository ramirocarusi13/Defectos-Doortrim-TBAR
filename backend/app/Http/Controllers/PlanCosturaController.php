<?php

namespace App\Http\Controllers;

use App\Enums\DaysOfWeek;
use App\Http\Depositos;
use App\Http\Estados;
use App\Http\Kanban;
use App\Http\MotivosReimpresion;
use App\Http\Stock;
use App\Http\WmsUnidades;
use App\Models\EstadoKanban;
use App\Models\Kanbans;
use App\Models\Lineas;
use App\Models\LogPlanCostura;
use App\Models\Modelos;
use App\Models\ModeloSublineTiempo;
use App\Models\MovimientosContenido;
use App\Models\PcPendienteImpresion;
use App\Models\PcPlanProduccion;
use App\Models\PlanCostura;
use App\Models\Sar\TKanban;
use App\Models\UbicacionContenido;
use App\Services\KanbanService;
use App\Services\PlanCosturaService;
use DateInterval;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanCosturaController extends Controller {

    private $lineas = [
        [
            'linea' => 'M1',
            'modelos' => ['SFEP', 'SFKQ', 'SFKN', 'SFBN', 'SFMR', 'SFTS', 'SFNG', 'SFNJ', 'SFJJ', 'SFHP', 'SFHG', 'SFEG', 'SFPC'],
        ],
        [
            'linea' => 'M2',
            'modelos' => ['SFPA', 'SFPB', 'SFLA', 'SFLB', 'SFPC', 'SFLC', 'SFTS', 'SFHG', 'SFLE']
        ],
        [
            'linea' => 'M3',
            'modelos' => ['STNS', 'STES', 'STSS', 'STHS', 'STP1', 'STL1']
        ],
        [
            'linea' => 'M4',
            'modelos' => ['STL1', 'STSS', 'STP1', 'STHS']
        ],
        [
            'linea' => 'M5',
            'modelos' => ['SUNS', 'SUJS', 'SUBS', 'SUMS', 'SUKS']
        ],
        [
            'linea' => 'M6',
            'modelos' => ['SSLN', 'SSBN', 'SSFN']
        ],
        [
            'linea' => 'M10',
            'modelos' => ['SFHG', 'SFPC', 'SFLC', 'SFLE']
        ],
        [
            'linea' => 'M11',
            // 'modelos' => ['HFHF', 'HRSH', 'HRDH', 'HSHS', 'HFLD', 'HRSL', 'HRDL', 'HRTL', 'HSLS']
            'modelos' => ['HFHF', 'HRSH', 'HRDH-C', 'HRDH-B', 'HSHS', 'HFLD-R', 'HFLD-L', 'HRSL', 'HRDL-C', 'HRDL-B', 'HRTL-C', 'HRTL-BLH', 'HRTL-BRH', 'HSLS']
        ]
    ];

    private $ocupacionDeLineas = [];
    private $cortesPlaneados = [];

    public function ejecutarPlanAutomaticamente() {
        $planService = new PlanCosturaService();

        try {
            $data = $planService->ejecutarPlanAutomaticamente();
        } catch (\Throwable $th) {
            //throw $th;
            return $this->setResponse([], $th->getMessage(), true);
        }

        Log::info("Se ejecuto plan de corte automático");

        return $this->setResponse([]);
    }

    public function getKanbansPlanificados() {

        $kanbans = Kanbans::with('modelo')->whereHas('estado', function ($q) {
            $q->where('estado_id', Estados::PLANIFICADO);
            $q->orWhere('estado_id', Estados::EN_PLANIFICACION);
        })->get();

        if ($kanbans) {
            return $this->setResponse($kanbans->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function fetchStockPlanCostura() {
        $stock = [];
        foreach ($this->lineas as $linea) {

            $modelos = [];
            foreach ($linea['modelos'] as $modR) {
                $cobertura = 0;

                $mod = $modR;
                $modelo = Modelos::withCount(['enBuffer', 'enBufferCorte', 'enProduccion', 'enPlan'])->where('nombre', $mod)->first();

                if ($modelo) {

                    $cantidadSetsModelo = is_null($modelo->cantidad) ? 10 : intval($modelo->cantidad);
                    $stockSetsBuffer = $modelo->en_buffer_count * $cantidadSetsModelo;
                    $stockSetsBufferCorte = $modelo->en_buffer_corte_count * $cantidadSetsModelo;
                    $stockSetsEnProduccion =  $modelo->en_produccion_count * $cantidadSetsModelo;
                    $stockSetsEnPlan = $modelo->en_plan_count * $cantidadSetsModelo;

                    $stockRack = Stock::getStockConsolidadoModeloPorDeposito($mod,  WmsUnidades::KANBAN, Depositos::RACKS);
                    $stockDollys = Stock::getStockConsolidadoModeloPorDeposito($mod, WmsUnidades::KANBAN, Depositos::DOLLYS);

                    $total = $stockSetsBufferCorte + $stockSetsBuffer + $stockSetsEnPlan + $stockSetsEnProduccion + ($stockDollys * $cantidadSetsModelo) + ($stockRack * $cantidadSetsModelo);

                    if (intval($modelo->consumo) > 0) {
                        $cobertura = round($total / floatval($modelo->consumo));
                    } else {
                        $cobertura = 0;
                    }

                    array_push($modelos, [
                        'modelo'                => $mod,
                        'consumo'               => number_format($modelo->consumo, 2),
                        'cobertura'             => $cobertura,
                        'stock_buffer'          => $stockSetsBuffer,
                        'stock_plan'            => $stockSetsEnPlan,
                        'stock_produccion'      => $stockSetsEnProduccion,
                        'stock_buffer_corte'    => $stockSetsBufferCorte,
                        'stock_dollys'          => $stockDollys * $cantidadSetsModelo,
                        'stock_racks'           => $stockRack * $cantidadSetsModelo,
                        'total'                 => $total,
                    ]);
                } else {
                    array_push($modelos, [
                        'modelo'                => $mod,
                        'consumo'               => 0,
                        'cobertura'             => 0,
                        'stock_buffer'          => 0,
                        'stock_plan'            => 0,
                        'stock_produccion'      => 0,
                        'stock_buffer_corte'    => 0,
                        'stock_dollys'          => 0,
                        'stock_racks'           => 0,
                        'total'                 => 0,
                    ]);
                }
            }

            array_push($stock, [
                'linea'     => $linea['linea'],
                'modelos'   => $modelos,
            ]);
        }

        return $this->setResponse($stock);
    }

    public function fetchPlan() {
        //Esta función retorna el plan de la semana. Identifica el día actual y obtiene la semana en base a eso

        $day = date('w');
        $inicio = date('Y-m-d', strtotime('-' . ($day - 1 + 7)  . ' days'));
        $fin = date('Y-m-d', strtotime('+' . (6 - $day + 8) . ' days'));

        $planActual = [];

        $begin = new DateTime($inicio);
        $end = new DateTime($fin);

        $interval = DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($begin, $interval, $end);

        $paso = false;

        foreach ($period as $dt) {
            $fecha = $dt->format("d/m/Y");
            foreach ($this->lineas as $linea) {
                $modelos = [];
                foreach ($linea['modelos'] as $mod) {

                    $plan = PlanCostura::where('fecha', $fecha)
                        ->where('linea', $linea['linea'])
                        ->where('modelo', $mod)
                        ->orderBy('fecha', 'ASC')
                        ->orderBy('linea', 'ASC')->orderBy('id', 'ASC')
                        ->get();

                    foreach ($plan as $p) {
                        $paso = True;
                        array_push($modelos, [
                            'modelo'    => $p->modelo,
                            'tt'        => $p->tt,
                            'tm'        => $p->tm,
                        ]);
                    }
                    if (!$paso) {
                        array_push($modelos, [
                            'modelo'    => $mod,
                            'tt'        => "0",
                            'tm'        => "0",
                        ]);
                    }

                    $paso = false;
                }

                array_push($planActual, [
                    'fecha'     => $fecha,
                    'linea'     => $linea['linea'],
                    'modelos'   => $modelos
                ]);
            }
        }
        return $this->setResponse($planActual);
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

    private function estandarizaModelo($modelo) {
        if ($modelo == 'HRDH') {
            $modeloEstandar = 'HRDH-C';
        } else if ($modelo = 'HFLD') {
            $modeloEstandar = 'HFLD-R';
        } else if ($modelo = 'HRDL') {
            $modeloEstandar = 'HRDL-C';
        } else if ($modelo = 'HRTL') {
            $modeloEstandar = 'HRTL-C';
        } else {
            $modeloEstandar = $modelo;
        }

        return $modeloEstandar;
    }

    private function getCortesARealizar($fechaAPlanificar, $actualizaCantidadesSegunPlan = false) {

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

        $plans = PlanCostura::selectRaw('modelo,linea,SUM(tm) as tm,SUM(tt) as tt')
            ->where(function ($q) use ($fechaAPlanificar, $fechaSiguiente, $fechaSiguiente2) {
                $q->where('fecha', '=', $fechaAPlanificar->format('d/m/Y'));
                $q->orWhere('fecha', '=', $fechaSiguiente->format('d/m/Y'));

                if (!is_null($fechaSiguiente2)) {
                    $q->orWhere('fecha', '=', $fechaSiguiente2->format('d/m/Y'));
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


        if (!$plans) {
            return $this->setResponse([], 'No hay plan para la fecha indicada : ' . $fechaAPlanificar->format('d/m/Y'));
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
                $mod = $this->estandarizaModelo($plan->modelo);
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
                $tiempoLineaBuffer = $this->sumarTiempo($tiempoSubline->tiempo, $cantidadSetsModelo);
            } else {
                $tiempoLineaBuffer = "00:00";
            }

            $ocupacionLinea = $this->sumaTiempoALinea($ocupacionLinea, $tiempoLineaBuffer, $linea->id);

            //Verifico la hora actual, si es el turno tarde, no tomo el turno mañana
            //Si es el turno mañana, tomo todo el día
            $ahora = new DateTime();

            if ($ahora->format('H') >= 15) {
                $requerido = intval($plan->tt);
            } else {
                $requerido = intval($plan->tt) + intval($plan->tm);
            }

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
                // $stockSetsEnProduccion = $modelo->en_produccion_count * $cantidadSetsModelo;
                $stockSetsEnPlan = $modelo->en_plan_count * $cantidadSetsModelo;

                $enDollys = Stock::getStockConsolidadoModeloPorDeposito(
                    $modelo->nombre,
                    WmsUnidades::KANBAN,
                    null,
                    null,
                    [Depositos::DOLLYS, Depositos::TEMPORAL_A]
                );

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





            // //Genero tantos kanbans como volumen por cantida de cortes
            // $cantidadTotal = ($volumenSetsCorte / $cantidadSetsModelo) * $vecesACortar;
            // for ($i = 1; $i <= $cantidadTotal; $i++) {

            //     $tkanban = new TKanban();
            //     $newKanban = $tkanban->crear($modelo->nombre);

            //     $newKanban = Kanbans::where('codigo', $newKanban)->first();

            //     if ($newKanban) {
            //         Kanbans::where('kanban_id', $newKanban->id)
            //             ->update([
            //                 'linea'     => $linea->id,
            //                 'lote'      => 'A',
            //                 'secuencia' => $i,
            //                 'cantidad'  => $cantidadTotal
            //             ]);

            //         EstadoKanban::where('kanban_id', $newKanban->id)
            //             ->update(['linea_id' => $linea->id, 'estado_id' => Estados::EN_PLANIFICACION]);

            //         PcPendienteImpresion::create([
            //             'kanban_id' => $newKanban->id,
            //             'motivo'    => MotivosReimpresion::ABASTECIMIENTO_BUFFER,
            //             'tipo'      => 'PRODUCCIÓN',
            //             'pendiente' => true
            //         ]);
            //     }
            // }
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
        // $this->armaOrdenOptimoCorte($cortesPlanificados, $ocupacionLinea);

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

        // Log::alert($cortesPlanificados);
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

    public function armarProximosCortesOrden(Request $request) {
        //Recibo el orden a cortar para actualizar y luego generar
        $fechaAPlanificar = $this->getFechaAPlanificar();

        if (is_null($request->items)) {
            return $this->setResponse([
                'fecha'     => $fechaAPlanificar->format('d/m/Y'),
                'tiempos'   => []
            ], 'Ok');
        }

        $orden = 0;
        $proximos = $this->armarProximosCortes();

        $existe = LogPlanCostura::where('fecha', $fechaAPlanificar->format('Y-m-d'))->where('orden', '<>', null)->get();

        if ($existe) {
            $orden = count($existe);
        }

        // Log::alert($request->items);
        // $kanbanService = new KanbanService();
        // $kanbans = [];

        foreach ($request->items as $item) {
            $orden = $orden + 1;

            try {

                // $kanbanService->crearLoteKanban($item['modelo'], $item['linea'], 1);

                PlanCostura::where('fecha', $fechaAPlanificar->format('d/m/Y'))
                    ->where('modelo', $item['modelo'])
                    ->update(['orden' => $orden]);

                LogPlanCostura::where('fecha', $fechaAPlanificar->format('Y-m-d'))
                    ->where('modelo', $item['modelo'])
                    ->where('orden', null)->first()
                    ->update([
                        'orden' => $orden
                    ]);
            } catch (\Throwable $th) {
                //throw $th;
                Log::alert($th->getMessage());
            }
        }

        // $kanbans = Kanbans::where('fecha', date('Y-m-d'))->whereNotNull('lote')->get();
        // $proximos->kanbans = $kanbans ? $kanbans->toArray() : [];

        return $proximos;
    }

    private function getFechaAPlanificar($fechaAPlanificar = null) {
        //Identifico que día es hoy. Si es viernes, planifico el lunes de la siguiente semana
        //1 - Lunes | 7 - Domingo

        if (!$fechaAPlanificar) {
            $dayOfWeek = date('N');
            $fechaAPlanificar = new DateTime();
        } else {
            $dayOfWeek = $fechaAPlanificar->format('N');
        }
        // $fechaAPlanificarDesde = new DateTime();

        //Si es sabado o domingo
        // if ($dayOfWeek == DaysOfWeek::SABADO) {
        //     $fechaAPlanificar->add(new DateInterval('P2D'));
        // }

        if ($dayOfWeek == DaysOfWeek::DOMINGO) {
            $fechaAPlanificar->add(new DateInterval('P1D'));
        }

        //Si es viernes agrego 3 días para planificar el lunes
        // if ($dayOfWeek == DaysOfWeek::VIERNES) {
        //     $fechaAPlanificar->add(new DateInterval('P3D'));
        // } else {
        //     $fechaAPlanificar->add(new DateInterval('P1D'));
        // }

        // Log::alert($fechaAPlanificar->format('d/m/Y'));

        return $fechaAPlanificar;
    }

    public function actualizaCortesPendientesSegunPlan() {
        $fechaAPlanificar = $this->getFechaAPlanificar();
        $cortesPlanificados = $this->getCortesARealizar($fechaAPlanificar);
        $cortesPlanificados = $this->cortesPlaneados;

        // Log::alert("PASO 3");


        $modelos = [];
        foreach ($cortesPlanificados as $c) {
            array_push($modelos, $c['modelo']);
            $existente = LogPlanCostura::where('modelo', $c['modelo'])
                ->where('fecha', $fechaAPlanificar->format('Y-m-d'))
                ->first();

            if ($existente) {
                $cortesEjecutados = intval($existente->cortes_ejecutados);
                $cortesRequeridos = intval($existente->cortes_requeridos);
            } else {
                $cortesEjecutados = 0;
                $cortesRequeridos = 0;
            }

            $cortesRequeridos = abs($cortesRequeridos - intval($c['vecesACortar']));

            LogPlanCostura::updateOrCreate(
                [
                    'modelo'    => $c['modelo'],
                    'fecha'     => $fechaAPlanificar->format('Y-m-d')
                ],
                [
                    'fecha'              => $fechaAPlanificar->format('Y-m-d'),
                    'modelo'             => $c['modelo'],
                    'cortes_requeridos'  => $cortesRequeridos,
                    'cant_buffer'        => $c['stockBuffer'],
                    'cant_assy'          => $c['stockAssy'],
                    'cant_corte'         => $c['stockBufferCorte'],
                    'cortes_ejecutados'  => ($cortesEjecutados > $cortesRequeridos) ? ($cortesEjecutados - $cortesRequeridos) : $cortesEjecutados
                ]
            );
        }

        LogPlanCostura::whereNotIn('modelo', $modelos)->where('fecha', $fechaAPlanificar->format('Y-m-d'))->delete();

        return $this->setResponse([
            'fecha'     => $fechaAPlanificar->format('d/m/Y'),
            'tiempos'   => $cortesPlanificados
        ], 'Ok');
    }

    /*
    Esta función se encarga de recorrer el plan del dia siguiente para armar los cortes necesarios del día
    */
    public function armarProximosCortes() {
        $fechaAPlanificar = $this->getFechaAPlanificar();

        if ($fechaAPlanificar == null) {
            return $this->setResponse([], 'El día no corresponde planificación');
        }

        $cortesPlanificados = $this->getCortesARealizar($fechaAPlanificar);
        $cortesPlanificados = $this->cortesPlaneados;

        foreach ($cortesPlanificados as $c) {
            //Averiguo cuantos tengo en total y los comparo contra los que tengo que cortar ahrora
            //SI NECESITO MÁS DE LOS QUE TENGO, LOS AGREGO
            if (array_key_exists('modelo', $c)) {
                LogPlanCostura::create([
                    'fecha'              => $fechaAPlanificar->format('Y-m-d'),
                    'modelo'             => $c['modelo'],
                    'cortes_requeridos'  => 1,
                    'cant_buffer'        => $c['stockBuffer'],
                    'cant_assy'          => $c['stockAssy'],
                    'cant_corte'         => $c['stockBufferCorte'],
                    'cortes_ejecutados'  => 0,
                ]);
            }
        }

        return $this->setResponse([
            'fecha'     => $fechaAPlanificar->format('d/m/Y'),
            'tiempos'   => $cortesPlanificados
        ], 'Ok');
    }

    public function store(Request $request) {
        //Grabo el plan semanal
        $items = $request->items;

        foreach ($items as $item) {
            foreach ($item['modelos'] as $modelo) {
                PlanCostura::updateOrCreate(
                    [
                        'fecha'     => $item['fecha'],
                        'linea'     => $item['linea'],
                        'modelo'    => $modelo['modelo']
                    ],
                    [
                        'fecha'     => $item['fecha'],
                        'linea'     => $item['linea'],
                        'modelo'    => $modelo['modelo'],
                        'tm'        => intval($modelo['tm']),
                        'tt'        => intval($modelo['tt'])
                    ]
                );
            }
        }

        $fechaAPlanificar = $this->getFechaAPlanificar();
        $cortesPlanificados = $this->getCortesARealizar($fechaAPlanificar);

        $ocupacionArray = ['', '', '', '', '', ''];
        foreach ($this->ocupacionDeLineas as $index => $ocupa) {
            $ocupacionArray[$index] = $ocupa;
        }

        return $this->setResponse([
            'plan'      => $this->cortesPlaneados,
            'ocupacion' => $ocupacionArray
        ]);
    }


    public function fetchKanbansAImprimirDelDia() {
        $fecha = date('Y-m-d');
        $kanbans = Kanbans::with('modelo')->where('fecha', $fecha)
            ->whereNotNull('lote')
            ->get();

        return $this->setResponse($kanbans ? $kanbans->toArray() : []);
    }

    public function filtrarKanbansDelDia(Request $request) {

        $kanbanService = new KanbanService();
        $kanbans = $kanbanService->listarKanbansDelDia($request);
        return $this->setResponse($kanbans);
    }
}
