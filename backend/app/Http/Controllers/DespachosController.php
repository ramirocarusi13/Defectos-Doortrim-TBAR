<?php

namespace App\Http\Controllers;

use App\Http\Depositos;
use App\Http\EstadosCuarentena;
use App\Http\Kanban;
use App\Http\Stock;
use App\Http\WmsUnidades;
use App\Models\Depositos as ModelsDepositos;
use App\Models\Despachos;
use App\Models\DespachosItems;
use App\Models\DespachosPedido;
use App\Models\Kanbans;
use App\Models\Modelos;
use App\Models\Movimientos;
use App\Models\MovimientosContenido;
use App\Models\PlanProduccion;
use App\Models\Sar\TKanban;
use App\Models\StockKanbans;
use App\Models\Ubicaciones;
use App\Services\DespachoService;
use App\Http\TipoItemDespacho;
use DateInterval;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DespachosController extends Controller {

    /**
     * 1- 15:01 a 23:30
     * 2- 06:00 a 10:30
     * 3- 10:31 a 15:00
     * 4- 15:01 a 21:00
     */
    private function horaDesdeRun($run) {
        if ($run == 1) {
            return ' 15:01:00';
        } else if ($run == 2) {
            return ' 06:00:00';
        } else if ($run == 3) {
            return ' 10:31:00';
        } else if ($run == 4) {
            return ' 15:01:00';
        } else {
            return ' 00:00:10';
        }
    }

    private function horaHastaRun($run) {
        if ($run == 1) {
            return ' 23:30:00';
        } else if ($run == 2) {
            return ' 10:30:00';
        } else if ($run == 3) {
            return ' 15:00:00';
        } else if ($run == 4) {
            return ' 21:00:00';
        } else {
            return ' 00:00:00';
        }
    }

    public function consultaRunDia() {
        $fechaHoy = date('Y-m-d');

        $data = Despachos::where(function ($q) use ($fechaHoy) {
            $q->where('created_at', '>=', $fechaHoy . ' 00:00:01');
            $q->where('created_at', '<=', $fechaHoy . ' 23:59:59');
        })->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getHistory() {
        $despachos = Despachos::with(['items', 'user'])
            ->orderBy('created_at')
            ->orderBy('run')
            ->where('fecha_salida_camion', null)
            ->get();

        if ($despachos) {
            return $this->setResponse($despachos->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getPendientes($run) {

        // Log::alert($run);

        if ($run == 0) {
            $despachos = Despachos::with(['itemsCover', 'pedidoCover'])->where('pendiente', true)->first();
        } else {
            $despachos = Despachos::with(['itemsCover', 'pedidoCover'])->where('pendiente', true)->where('run', $run)->first();
        }

        if ($despachos) {
            return $this->setResponse($despachos->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function botonPresionado(Request $request) {

        // Log::alert($request);
        $fecha = new DateTime();
        if ($request->presionado) {
            $despacho = Despachos::where('fecha_llegada_camion', null)->where('pendiente', true)->orderBy('run', 'ASC')->orderBy('fecha', 'ASC')
                ->first();

            if ($despacho) {
                Despachos::where('id', $despacho->id)->update(['fecha_llegada_camion' => $fecha->format('Y-m-d H:i:s')]);
            }
        } else {
            $despacho = Despachos::where('fecha_salida_camion', null)->where('pendiente', true)->orderBy('run', 'ASC')->orderBy('fecha', 'ASC')
                ->first();

            if ($despacho) {
                Despachos::where('id', $despacho->id)->update(['fecha_salida_camion' => $fecha->format('Y-m-d H:i:s'), 'pendiente' => false]);
            }
        }

        return $this->setResponse([]);
    }

    public function verificaYPickea(Request $request) {

        $despachoId = $request->despachoId;
        $scan = $request->scan;
        $userId1 = $request->user1;
        $userId2 = $request->user2;


        // Log::alert($request);

        try {
            Kanban::registrarKanbanSiNoExiste($scan);
        } catch (\Throwable $th) {
            return $this->setResponse([], "El kanban ingresado no existe", true);
        }

        $kanbanExistente = Kanbans::with('modelo')->where('codigo', $scan)->first();
        if (!$kanbanExistente) {
            return $this->setResponse([], 'El kanban escaneado no existe', true);
        }

        //VERIFICO SI TIENE EL MODELO
        if (is_null($kanbanExistente->modelo)) {
            $tKanban = TKanban::with('modelo')->where('n_kanban', $scan)->first();

            if ($tKanban) {
                $modeloNuevo = Modelos::where('nombre', trim($tKanban->modelo->NOMBRE))->first();

                if ($modeloNuevo) {
                    Kanbans::where('codigo', $scan)->update(['modelo_id' => $modeloNuevo->id]);
                    $kanbanExistente = Kanbans::with('modelo')->where('codigo', $scan)->first();
                } else {
                    return $this->setResponse([], 'El kanban escaneado no tiene un modelo válido', true);
                }
            } else {
                return $this->setResponse([], 'El kanban escaneado no existe', true);
            }
        }

        //VERIFICO SI NO FUE PICKEADO YA
        $pickeado = DespachosItems::where('pickeado', true)->where('kanban', $scan)->where('despacho_id', $despachoId)->first();
        if ($pickeado) {
            return $this->setResponse([], 'El kanban ya fue pickeado', true);
        }

        //PRIMERO CHEQUEO SI ES POR POSICIÓN
        $pendiente = DespachosItems::where('despacho_id', $despachoId)
            ->where('kanban', $scan)
            ->where('pickeado', false)
            ->first();

        $desbalanceo = MovimientosContenido::where('ref', $scan)->where('cantidad', '>', 0)->where('lote', 'DESBALANCEO')->first();

        // Log::alert($kanbanExistente);

        if (!$pendiente) {
            //CHEQUEO POR MODELO
            //AVeriguo en que deposito está porque por modelo puede ser Dolly o Temporal A
            // $stockDeposito = StockKanbans::selectRaw('deposito_id')->where(function ($q) {
            //     $q->where('deposito_id', Depositos::DOLLYS);
            //     $q->orWhere('deposito_id', Depositos::TEMPORAL_A);
            //     $q->orWhere('deposito_id', Depositos::TEMPORAL_B);
            // })->where('ref', $scan)->first();

            $pendiente = DespachosItems::with('ubicacion')
                ->where('despacho_id', $despachoId)
                ->where('modelo', trim($kanbanExistente->modelo->nombre))
                ->where('deposito_id', '<>', Depositos::RACKS)
                // ->where('deposito_id', '<>', Depositos::TEMPORAL_A)
                ->where('pickeado', false)
                ->where(function ($q) use ($desbalanceo) {
                    if ($desbalanceo && $desbalanceo != '' && !is_null($desbalanceo)) {
                        $q->where('desbalanceo', 1);
                    } else {
                        $q->where('desbalanceo', 0);
                    }
                })
                // ->when(!empty($stockDeposito), function ($q) use ($stockDeposito) {
                //     $q->where('deposito_id', $stockDeposito->deposito_id);
                // })
                ->first();
        }

        if (!$pendiente) {
            return $this->setResponse([], 'El kanban no corresponde a un modelo a preparar', true);
        }

        //Busco el kanban en stock
        $stock = MovimientosContenido::selectRaw('sum(cantidad) as cantidad,ubicacion_id')->where('ref', $scan)
            ->whereHas('ubicacion', function ($q) use ($pendiente) {
                $q->where('deposito_id', intval($pendiente->deposito_id));
            })
            ->groupBy('ubicacion_id')->get();

        // Log::alert($stock);

        if (count($stock) == 0) {
            $stockMovido = false;
            $stockEnOtroDeposito = StockKanbans::where('ref', $scan)
                ->where('cantidad', '>', 0)
                ->first();

            if ($stockEnOtroDeposito) {
                if (
                    intval($pendiente->deposito_id) == Depositos::RACKS
                    || intval($stockEnOtroDeposito->deposito_id) == Depositos::RACKS
                ) {
                    return $this->setResponse([], 'El kanban esta almacenado en otro deposito.', true);
                }

                $ubicacionEsperada = null;
                if (!is_null($pendiente->posicion) && trim($pendiente->posicion) != '') {
                    $ubicacionEsperada = Ubicaciones::where('deposito_id', intval($pendiente->deposito_id))
                        ->where('nombre', $pendiente->posicion)
                        ->first();
                }

                if (!$ubicacionEsperada) {
                    $ubicacionEsperada = Ubicaciones::whereHas('unidades', function ($q) {
                        $q->where('unidad_id', WmsUnidades::KANBAN);
                    })
                        ->where('deposito_id', intval($pendiente->deposito_id))
                        ->first();
                }

                if (!$ubicacionEsperada) {
                    return $this->setResponse([], 'No se encontro una ubicacion disponible en el deposito esperado.', true);
                }

                DB::beginTransaction();
                try {
                    $movimiento = Movimientos::create([
                        'unidad_id'     => WmsUnidades::KANBAN,
                        'ubicacion_id'  => null,
                        'finalizado'    => false,
                        'user_id'       => $userId1,
                        'user_id2'      => $userId2
                    ]);

                    if (!$movimiento) {
                        DB::rollBack();
                        return $this->setResponse([], 'No se pudo mover el kanban al deposito esperado.', true);
                    }

                    MovimientosContenido::create([
                        'movimiento_id'     => $movimiento->id,
                        'ref'               => strtoupper($scan),
                        'cantidad'          => -1,
                        'ubicacion_id'      => $stockEnOtroDeposito->ubicacion_id,
                        'unidad_id'         => WmsUnidades::KANBAN,
                        'lote'              => $stockEnOtroDeposito->lote
                    ]);

                    MovimientosContenido::create([
                        'movimiento_id'     => $movimiento->id,
                        'ref'               => strtoupper($scan),
                        'cantidad'          => 1,
                        'ubicacion_id'      => $ubicacionEsperada->id,
                        'unidad_id'         => WmsUnidades::KANBAN,
                        'lote'              => $stockEnOtroDeposito->lote
                    ]);

                    DB::commit();
                    $stockMovido = true;
                } catch (\Throwable $th) {
                    DB::rollBack();
                    Log::error('DespachosController::verificaYPickea mover a deposito esperado : ' . $th->getMessage());
                    return $this->setResponse([], 'No se pudo mover el kanban al deposito esperado.', true);
                }
            }
            //SI LLEGO ACA, EXISTE PERO NO ESTA EN EL DEPOSITO INDICADO, ME FIJO SI ESTA EN OTRO DEPOSITO, SI NO, LO DOY DE ALTA DONDE LO PIDEN
            $stock = MovimientosContenido::selectRaw('sum(cantidad) as cantidad,ubicacion_id')->where('ref', $scan)
                ->groupBy('ubicacion_id')
                ->get();

            if (count($stock) > 0 && !$stockMovido) {
                return $this->setResponse([], 'El kanban está almacenado en otro deposito.', true);
            }

            if (!$stockMovido) {
                $ubicacionNueva = Ubicaciones::whereHas('unidades', function ($q) {
                    $q->where('unidad_id', WmsUnidades::KANBAN);
                })
                    ->where('deposito_id', intval($pendiente->deposito_id))
                    ->first();

            if (!$ubicacionNueva) {
                return $this->setResponse([], 'El kanban no está registrado en stock o no está pendiente de retiro.', true);
            }

            //NO EXISTE EN STOCK, LO CREO
            $payload = [
                'unidad_id'     => WmsUnidades::KANBAN,
                'ubicacion_id'  => null,
                'finalizado'    => false,
                'user_id'       => $userId1,
                'user_id2'      => $userId2
            ];

            $movimiento = Movimientos::create($payload);

            if (!$movimiento) {
                return $this->setResponse([], 'El kanban no está registrado en stock o no está pendiente de retiro.', true);
            }

            MovimientosContenido::create([
                'movimiento_id'     => $movimiento->id,
                'ref'               => strtoupper($scan),
                'cantidad'          => 1,
                'ubicacion_id'      => $ubicacionNueva->id,
                'unidad_id'         => WmsUnidades::KANBAN
            ]);
            }

            $stock = MovimientosContenido::selectRaw('sum(cantidad) as cantidad,ubicacion_id')->where('ref', $scan)
                ->whereHas('ubicacion', function ($q) use ($pendiente) {
                    $q->where('deposito_id', intval($pendiente->deposito_id));
                })
                ->groupBy('ubicacion_id')
                ->get();
        }

        if (!$stock) {
            return $this->setResponse([], 'El kanban no está registrado en stock o no está pendiente de retiro', true);
        }

        $seleccionado = null;
        foreach ($stock as $s) {
            if (intval($s->cantidad) > 0) {
                //Verifico si esta reservado
                $reservado = DespachosItems::where('kanban', $scan)->where('pickeado', false)->where('posicion', null)->first();

                if (!$reservado) {
                    $seleccionado = $s;
                    continue;
                }
            }
        }

        if (!$seleccionado) {
            return $this->setResponse([], 'El kanban no está registrado en stock o no está pendiente de retiro', true);
        }

        $aCuarentena = false;
        //VERIFICO SI ES UN KANBAN DE DESBALANCEO
        $aCuarentena = ($desbalanceo || ($pendiente->deposito_id == Depositos::RACKS || $pendiente->deposito_id == Depositos::TEMPORAL_A));

        DespachosItems::where('id', $pendiente->id)
            ->where(function ($q) use ($desbalanceo) {
                if ($desbalanceo && $desbalanceo != '' && !is_null($desbalanceo)) {
                    $q->where('desbalanceo', 1);
                } else {
                    $q->where('desbalanceo', 0);
                }
            })
            ->update([
                'pickeado'          => True,
                'kanban'            => $scan,
                'cuarentena'        => $aCuarentena,
                'fecha_calidad'     => $aCuarentena ? null : date('Y-m-d'),
                'estado_calidad'    => $aCuarentena ? null : EstadosCuarentena::APROBADO,
                'user_id'           => $userId1,
                'user_id2'          => $userId2,
            ]);

        //ENVIO EL KANBAN A EL DEPOSITO DE DESPACHOS
        $posicionExistente = StockKanbans::where('ref', $scan)->where('cantidad', '>', 0)->first();
        // $posicionExistente = MovimientosContenido::where('ref', $scan)->where('cantidad', '>', 0)->first();
        $posicionDespacho = Ubicaciones::where('deposito_id', Depositos::DESPACHOS)->first();

        if ($posicionExistente) {
            $payload = [
                'unidad_id'     => WmsUnidades::KANBAN,
                'ubicacion_id'  => null,
                'finalizado'    => false,
                'user_id'       => $userId1,
                'user_id2'      => $userId2
            ];

            $movimiento2 = Movimientos::create($payload);

            //ENTRA AL DEPOSITO DE DESPACHOS
            MovimientosContenido::create([
                'movimiento_id' => $movimiento2->id,
                'ref'           => $scan,
                'cantidad'      => 1,
                'lote'          => $posicionExistente->lote,
                'unidad_id'     => WmsUnidades::KANBAN,
                'ubicacion_id'  => $posicionDespacho->id
            ]);

            //LO DOY DE BAJA DEL DEPOSITO ACTUAL
            MovimientosContenido::create([
                'movimiento_id' => $movimiento2->id,
                'ref'           => $scan,
                'cantidad'      => -1,
                'lote'          => $posicionExistente->lote,
                'unidad_id'     => WmsUnidades::KANBAN,
                'ubicacion_id'  => $posicionExistente->ubicacion_id
            ]);
        }

        return $this->setResponse([], 'Pickeado correctamente');
    }

    public function setPickeado($id) {

        try {
            DespachosItems::where('id', $id)->update(['pickeado' => true]);
            return $this->setResponse([]);
        } catch (\Throwable $th) {
            //throw $th;
            return $this->setResponse([], $th->getMessage(), true);
        }
    }

    private function getMonthName($month) {
        if ($month == "01") {
            return "ENE";
        } else if ($month == "02") {
            return "FEB";
        } else if ($month == "03") {
            return "MAR";
        } else if ($month == "04") {
            return "ABR";
        } else if ($month == "05") {
            return "MAY";
        } else if ($month == "06") {
            return "JUN";
        } else if ($month == "07") {
            return "JUL";
        } else if ($month == "08") {
            return "AGO";
        } else if ($month == "09") {
            return "SEP";
        } else if ($month == "10") {
            return "OCT";
        } else if ($month == "11") {
            return "NOV";
        } else if ($month == "12") {
            return "DIC";
        }
    }

    public function informaSalidaCamion($id) {
        $fecha = new DateTime();

        DB::beginTransaction();
        $despachoService = new DespachoService($id);

        Despachos::where('id', $id)->update([
            'fecha_salida_camion'   => $fecha->format('Y-m-d H:i:s'),
            'pendiente'             => false
        ]);

        $despachoService->stockService->generaMovimiento(WmsUnidades::KANBAN);

        $despachoService->egresoStockItems();

        DB::commit();
        return $this->setResponse([], "Finalizado correctamente");
    }

    public function consultaRechazados($id) {

        $rechazados = DespachosItems::with('ubicacion.deposito')->where('despacho_id', $id)->where('estado_calidad', EstadosCuarentena::RECHAZADO)->get();

        if ($rechazados) {
            return $this->setResponse($rechazados->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function consultaPorModelo(Request $request) {

        $pedido = $request->pedido;
        $stockCajas = [];
        $stockCajasTemporal = [];
        $mesesEnCajas = [];
        $stockDollys = [];
        $stockDollysDesbalanceo = [];
        $response = [];
        $conReservas = $request->conReserva;
        $run = $request->run;
        $esEdicion = $request->edit;
        $data = [];

        // Log::alert($request);

        // KanbanService::fixKanbanSinModelos();

        $filtroModelos = [];
        foreach ($pedido as $modelo) {
            array_push($filtroModelos, $modelo['modelo']);
        }

        // Log::info("Modelos a filtrar: " . json_encode($filtroModelos));

        $enRack = MovimientosContenido::with(['kanban.modelo', 'reservado', 'ubicacion'])
            ->selectRaw('sum(cantidad) as cantidad,ref,ubicacion_id')
            ->whereHas('ubicacion', function ($q) {
                // $q->where('deposito_id', Depositos::RACKS);
                // $q->orWhere('deposito_id', Depositos::TEMPORAL_A);
                $q->whereIn('deposito_id', [Depositos::RACKS, Depositos::TEMPORAL_A]);
            })
            ->whereHas('ubicacion', function ($q) {
                $q->where('habilitada', true);
            })
            ->whereHas('kanban.modelo', function ($q) use ($filtroModelos) {
                $q->whereIn('nombre', $filtroModelos);
            })
            ->groupBy('ref', 'ubicacion_id')
            ->orderBy('ref', 'ASC')
            ->havingRaw('SUM(cantidad)>?', [0])
            ->get();


        // Log::alert("RACKS");

        //A PEDIDO DE DANY, DE TEMP A TIENE QUE DEJAR SACAR COMO DOLLY,POR MODELO
        foreach ($enRack as $rack) {

            if (intval($rack->cantidad) <= 0) {
                continue;
            }

            if ((!$conReservas && intval($rack->cantidad) <= 0) || (!$conReservas && $rack->reservado)) {
                continue;
            }

            if (is_null($rack->kanban)) {
                Kanban::registrarKanbanSiNoExiste($rack->ref);
                $kanbanLeido = Kanbans::with('modelo')->where('codigo', $rack->ref)->first();
            } else {
                $kanbanLeido = $rack->kanban;
            }

            //VERIFICO SI EXISTE EN UN DESPACHO
            if (!$conReservas) {
                $existeEnDespacho = DespachosItems::whereHas('despacho', function ($q) {
                    $q->where('pendiente', true);
                })->where('kanban', $kanbanLeido->codigo)->first();

                if ($existeEnDespacho) {
                    continue;
                }
            }

            if (!is_null($kanbanLeido)) {

                $data = [
                    'modelo'    => $kanbanLeido->modelo->nombre,
                    'deposito'  => $rack->ubicacion->deposito_id,
                    'kanban'    => $kanbanLeido,
                    'ubicacion' => $rack->ubicacion,
                    'reserva'   => $rack->reservado
                ];

                if ($rack->ubicacion->deposito_id == Depositos::TEMPORAL_A) {
                    array_push($stockCajasTemporal, $data);
                } else {
                    array_push($stockCajas, $data);
                }

                if ($rack->ubicacion->deposito_id == Depositos::RACKS) {

                    //Averiguo que mes es y lo agrego al array si no existe
                    $keyMonth = $this->getMonthName(substr($kanbanLeido->codigo, 3, 2)) . ' 20' . substr($kanbanLeido->codigo, 1, 2);
                    $mod = $this->searchForId($kanbanLeido->modelo->nombre, $mesesEnCajas, 'modelo');
                    if ($mod == "") {
                        array_push($mesesEnCajas, [
                            'modelo' => $kanbanLeido->modelo->nombre,
                            'items' => []
                        ]);
                    }
                    $key = $this->searchForId($kanbanLeido->modelo->nombre, $mesesEnCajas, "modelo");
                    $val = $this->searchForId($keyMonth, $mesesEnCajas[$key]['items'], 'name');

                    if ($val == '') {

                        $items = $mesesEnCajas[$key]['items'];
                        array_push($items, [
                            'name'      => $keyMonth,
                            'key'       => '20' . substr($kanbanLeido->codigo, 1, 2) . '-' .  substr($kanbanLeido->codigo, 3, 2)
                        ]);

                        $mesesEnCajas[$key]['items'] = $items;
                    }
                }
            } else {
                Log::error($rack);
            }
        }


        // Log::alert("RACKS1");

        $idsUsados = [];

        $enDollys = StockKanbans::selectRaw('vt_stock_kanbans.modelo as mod,vt_stock_kanbans.ubicacion_id,vt_stock_kanbans.ref')
            ->leftJoin('despachos_items', 'ref', 'despachos_items.kanban')
            ->whereIn('vt_stock_kanbans.modelo', $filtroModelos)
            ->where('vt_stock_kanbans.deposito_id', Depositos::DOLLYS)
            ->where('despachos_items.despacho_id', null)
            ->orderBy('vt_stock_kanbans.modelo', 'ASC')
            ->get();
        // Log::alert("RACKS2");

        // Log::alert(json_encode($enDollys, JSON_PRETTY_PRINT));

        $despacho = Despachos::where('run', $run)->where('pendiente', true)->first();

        foreach ($enDollys as $dolly) {

            // if ($dolly->mod == 'SFTS') {
            //     Log::alert($dolly);
            // }


            //VERIFICO SI ENCUENTRO UN PENDIENTE DEL MODELO
            $pendientes = DespachosItems::where('modelo', $dolly->mod)
                ->where(function ($q) {
                    $q->where('deposito_id', Depositos::DOLLYS);
                    $q->orWhere('produccion', true);
                })
                ->whereNotIn('id', $idsUsados)
                ->when(!empty($despacho), function ($q) use ($despacho, $esEdicion, $run) {
                    if ($esEdicion) {
                        $q->whereHas('despacho', function ($w) use ($run) {
                            $w->where('run', '<', $run);
                        });
                        // $q->where('despacho_id', '<', $despacho->id);
                    }
                })
                ->whereHas('despacho', function ($q) {
                    $q->where('pendiente', true);
                })
                ->where('pickeado', false)
                ->first();

            if ($pendientes) {

                // if ($dolly->mod == 'SFTS') {
                //     Log::alert($pendientes);
                // }
                //SI HAY PENDIENTES, ENTONCES NO LO TENGO EN CUENTA YA QUE LO TENGO EN OTRO RUN AUN SIN CERRAR
                array_push($idsUsados, $pendientes->id);
            } else {
                $data = [
                    'modelo'    => $dolly->mod,
                    'deposito'  => Depositos::DOLLYS,
                    'kanban'    => $dolly->ref,
                    'ubicacion' => Ubicaciones::where('id', $dolly->ubicacion_id)->first()
                ];
                array_push($stockDollys, $data);
            }
        }

        // Log::alert("RACKS3");


        $enDollysDesbalanceo = StockKanbans::selectRaw('vt_stock_kanbans.modelo as mod,vt_stock_kanbans.ubicacion_id,vt_stock_kanbans.ref')
            ->leftJoin('despachos_items', 'ref', 'despachos_items.kanban')
            ->whereIn('vt_stock_kanbans.modelo', $filtroModelos)
            ->where('vt_stock_kanbans.deposito_id', Depositos::DOLLYS)
            ->where('vt_stock_kanbans.lote', 'DESBALANCEO')
            ->where('despachos_items.despacho_id', null)
            ->orderBy('vt_stock_kanbans.modelo', 'ASC')
            ->get();

        foreach ($enDollysDesbalanceo as $dolly) {
            //VERIFICO SI ENCUENTRO UN PENDIENTE DEL MODELO
            $pendientes = DespachosItems::where('modelo', $dolly->mod)
                ->where(function ($q) {
                    $q->where('deposito_id', Depositos::DOLLYS);
                    $q->orWhere('produccion', true);
                })
                ->where('desbalanceo', true)
                ->whereNotIn('id', $idsUsados)
                ->when(!empty($despacho), function ($q) use ($despacho, $esEdicion, $run) {
                    if ($esEdicion) {
                        $q->whereHas('despacho', function ($w) use ($run) {
                            $w->where('run', '<', $run);
                        });
                        // $q->where('despacho_id', '<', $despacho->id);
                    }
                })
                ->whereHas('despacho', function ($q) {
                    $q->where('pendiente', true);
                })
                ->where('pickeado', false)
                ->first();

            if ($pendientes) {
                //SI HAY PENDIENTES, ENTONCES NO LO TENGO EN CUENTA YA QUE LO TENGO EN OTRO RUN AUN SIN CERRAR
                array_push($idsUsados, $pendientes->id);
            } else {
                $data = [
                    'modelo'    => $dolly->mod,
                    'deposito'  => Depositos::DOLLYS,
                    'kanban'    => $dolly->ref,
                    'ubicacion' => Ubicaciones::where('id', $dolly->ubicacion_id)->first()
                ];
                array_push($stockDollysDesbalanceo, $data);
            }
        }

        //REVISO LO QUE VA A SALIR DE PRODUCCION
        $fechaActual = date('Y-m-d');
        $horaActual = new DateTime();

        $horaDesde = DateTime::createFromFormat('Y-m-d H:i:s', $fechaActual . $this->horaDesdeRun($run));
        $horaHasta = DateTime::createFromFormat('Y-m-d H:i:s', $fechaActual . $this->horaHastaRun($run));

        if ($horaActual > $horaDesde && $horaActual < $horaHasta) {
            $horaDesde = $horaActual;
        }

        $enProduccion = PlanProduccion::whereIn('modelo', $filtroModelos)
            ->where('fecha', date('Y-m-d'))
            ->whereBetween('hora_esperada', [$horaDesde->format('Y-m-d H:i:s'), $horaHasta->format('Y-m-d H:i:s')])
            ->get();

        //SOLO PARA RUN 1 o 4
        if ($run == 1 || $run == 4) {
            foreach ($enProduccion as $prod) {
                $despachosItems = DespachosItems::where('modelo', $prod->modelo)
                    ->where('produccion', true)
                    ->whereHas('despacho', function ($q) use ($run) {
                        $q->where('pendiente', True);
                        $q->where(function ($k) use ($run) {
                            if ($run == 1) {
                                $k->where('run', 4);
                            } else {
                                $k->where('run', 1);
                            }
                        });
                    })
                    ->get()->count();

                if ($despachosItems > 0) {
                    $prod->cantidad = intval($prod->cantidad) - ($despachosItems * 10);
                }
            }
        }

        $response['racks']      = $stockCajas;
        $response['temporal']   = $stockCajasTemporal;
        $response['dollys']     = $stockDollys;
        $response['meses']      = $mesesEnCajas;
        $response['produccion'] = $enProduccion;
        $response['desbalanceo'] = $stockDollysDesbalanceo;

        return $this->setResponse($response);
    }

    public function store(Request $request) {

        $seleccionados = $request->pedido['seleccionados'];
        $pedido = $request->pedido['pedido'];

        $fecha = DateTime::createFromFormat('Y-m-d', $request->pedido['fecha']);

        $id = intval($request->pedido['id']);
        $esModificacion = ($id > 0);

        if (!$esModificacion) {
            //Verifico si no existe un run con esa fecha
            $existe = Despachos::where('fecha', $fecha->format('Y-m-d'))->where('run', $request->pedido['run'])->first();
            if ($existe) {
                return $this->setResponse([], 'Ya existe el run ' . $request->pedido['run'] . ' en la fecha indicada.', true);
            }
        }

        DB::beginTransaction();

        try {

            if (!$id) {
                $despacho = Despachos::create([
                    'user_id'   => null, //auth()->guard('api')->user()->id,
                    'pendiente' => true,
                    'run'       => $request->pedido['run'],
                    'fecha'     => $fecha->format('Y-m-d')
                ]);

                if (!$despacho) {
                    return $this->setResponse([], 'Ocurrió un error al guardar el despacho', true);
                }
            }

            if ($esModificacion) {
                DespachosPedido::where('despacho_id', $id)->delete();
                //Lo que ya se pickeo no se elimina
                DespachosItems::where('despacho_id', $id)->where('pickeado', false)->delete();
            } else {
                $id = $despacho->id;
            }

            $tipo = '';
            foreach ($seleccionados as $p) {

                try {
                    $tipo = $p['tipo'];
                } catch (\Throwable $th) {
                    //throw $th;
                    $tipo = 'COVER';
                }

                $deProduccion = 0;
                $deCl2 = 0;
                $deDesbalanceo = 0;
                $deTemporal = 0;

                // Log::alert($p);
                $deposito = ModelsDepositos::where('id', $p['deposito'])->first();
                $posPiso = true;
                if ($deposito) {
                    if ($p['deposito'] == Depositos::TEMPORAL_A) {
                        $posPiso = true; //POR AHORA PARA QUE NO ME PIDA KANBAN, SI NO MODELO
                    } else {
                        $posPiso =  intval($deposito->posiciones_piso == 1);
                    }
                }

                if ($esModificacion) {
                    if ($tipo == 'COVER') {
                        if (is_null($p['ubicacion'])) {
                            $existe = DespachosItems::select('id')
                                ->where('pedido', null)
                                ->where('deposito_id', $p['deposito'])
                                ->where('modelo', $p['modelo'])
                                ->where('despacho_id', $id)
                                ->where('tipo', TipoItemDespacho::COVER)
                                ->first();
                        } else {
                            $existe = DespachosItems::select('id')->where('posicion', $p['ubicacion']['nombre'])
                                ->where('deposito_id', $p['deposito'])
                                ->where('despacho_id', $id)
                                ->where('tipo', TipoItemDespacho::COVER)
                                ->first();
                        }
                    } else if ($tipo == 'DOORTRIM') {
                        $existe = DespachosItems::select('id')->where('modelo', $p['modelo'])->where('despacho_id', $id)->where('tipo', TipoItemDespacho::DOOR_TRIM)->first();
                    }
                } else {
                    $existe = false;
                }

                if (!$existe) {

                    try {
                        $esProduccion = $p['produccion'];
                    } catch (\Throwable $th) {
                        $esProduccion = 0;
                    }

                    try {
                        $esTemporal = $p['temporal'];
                    } catch (\Throwable $th) {
                        $esTemporal = 0;
                    }

                    try {
                        $cl2 = $p['cl2'];
                    } catch (\Throwable $th) {
                        $cl2 = 0;
                    }

                    try {
                        $esDesbalanceo = $p['desbalanceo'];
                    } catch (\Throwable $th) {
                        $esDesbalanceo = 0;
                    }

                    if ($esDesbalanceo != 1 && $esDesbalanceo != 0) {
                        $esDesbalanceo = 0;
                    }

                    if ($esProduccion == 1 || $esProduccion == "1") {
                        $deProduccion++;
                    }

                    if ($esDesbalanceo == 1 || $esDesbalanceo == "1") {
                        $deDesbalanceo++;
                    }

                    if ($esTemporal == 1 || $esTemporal == "1") {
                        $deTemporal++;
                    }

                    if ($cl2 == 1 || $cl2 == "1") {
                        $deCl2++;
                    }

                    DespachosItems::create([
                        'despacho_id'   => $id,
                        'pickeado'      => false,
                        'kanban'        => $posPiso ? null : $p['kanban']['codigo'],
                        'posicion'      => $posPiso ? null : $p['ubicacion']['nombre'],
                        'deposito_id'   => $p['deposito'],
                        'modelo'        => $p['modelo'],
                        'pedido'        => '1',
                        'produccion'    => $esProduccion, //$p['produccion']
                        'desbalanceo'   => $esDesbalanceo,
                        'temporal'      => $esTemporal,
                        'cl2'           => $cl2,
                        'tipo'          => TipoItemDespacho::COVER
                        // 'cuarentena'    => true
                    ]);
                } else {
                    DespachosItems::where('id', $existe->id)->update(['pedido' => '1']);
                }
            }

            foreach ($pedido as $p) {
                // $deProduccion = $p['produccion'];
                // $deDesbalanceo = $p['desbalanceo'];
                try {
                    $deDesbalanceo = $p['desbalanceo'];
                } catch (\Throwable $th) {
                    $deDesbalanceo = 0;
                }

                try {
                    $deProduccion = $p['produccion'];
                } catch (\Throwable $th) {
                    $deProduccion = 0;
                }

                try {
                    $deTemporal = $p['temporal'];
                } catch (\Throwable $th) {
                    $deTemporal = 0;
                }

                try {
                    $deCl2 = $p['cl2'];
                } catch (\Throwable $th) {
                    $deCl2 = 0;
                }

                DespachosPedido::create([
                    'modelo'        => $p['modelo'],
                    'despacho_id'   => $id,
                    'pedido'        => $p['cantidad'],
                    'pendiente'     => $p['tipo'] == 'COVER' ? (intval($p['cantidad']) - $deProduccion - $deDesbalanceo - $deCl2) : 0,
                    'produccion'    => $deProduccion, //$p['produccion'],
                    'desbalanceo'   => $deDesbalanceo, //$p['desbalanceo'],
                    'temporal'      => $deTemporal,
                    'cl2'           => $deCl2, //$p['desbalanceo'],
                    'tipo'          => $p['tipo']
                ]);

                if ($p['tipo'] == 'DOORTRIM' && intval($p['cantidad']) > 0) {
                    DespachosItems::create([
                        'despacho_id'       => $id,
                        'pickeado'          => false,
                        'kanban'            => null,
                        'posicion'          => null,
                        'estado_calidad'    => 'aprobado',
                        'cuarentena'        => 0,
                        'fecha_calidad'     => date('Y-m-d H:i:s'),
                        'deposito_id'       => Depositos::ARHRDT,
                        'modelo'            => $p['modelo'],
                        'pedido'            => intval($p['cantidad']),
                        'produccion'        => 0, //$p['produccion']
                        'desbalanceo'       => 0,
                        'cl2'               => 0,
                        'temporal'          => 0,
                        'tipo'              => TipoItemDespacho::DOOR_TRIM
                    ]);
                }
            }

            DespachosItems::where('despacho_id', $id)->update(['pedido' => null]);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return $this->setResponse([], $th->getMessage(), true);
        }

        DB::commit();
        return $this->setResponse([]);
    }

    public function showRun(Request $request) {

        $runId = $request->runId;
        $fecha = $request->fecha;
        $run = $request->run;
        $modeloId = $request->modelo;
        $modelo = null;
        $detalle = $request->detalle;

        if (!is_null($modeloId)) {
            $modelo = Modelos::where('id', $modeloId)->first();
            if ($modelo) {
                $modelo = $modelo->nombre;
            }
        }

        // Log::alert("PASO1");

        if ($runId) {
            $despachos = Despachos::with(['items.despacho', 'items.kanban', 'items.ubicacion.deposito', 'pedido', 'items.FgDataSar', 'items.FgData'])
                ->where('id', $runId)
                ->when(!empty($modelo), function ($q) use ($modelo) {
                    $q->whereHas('items', function ($w) use ($modelo) {
                        $w->where('modelo', $modelo);
                    });
                })
                ->orderBy('created_at', 'DESC')
                ->first();
        } else {
            if ($detalle) {
                // Log::alert("PASO3");
                $fFechaDesde = null;
                $fFechaHasta = null;

                if (count($fecha) > 0) {
                    $fFechaDesde = new DateTime($fecha[0]);
                    $fFechaHasta = new DateTime($fecha[1]);
                }

                $despachos = DespachosItems::with(['despacho', 'kanban', 'ubicacion.deposito'])
                    ->when(!empty($fFechaDesde), function ($q) use ($fFechaDesde, $fFechaHasta) {
                        $q->whereHas('despacho', function ($w) use ($fFechaDesde, $fFechaHasta) {
                            $w->where('fecha', '>=', $fFechaDesde);
                            $w->where('fecha', '<=', $fFechaHasta);
                        });
                    })
                    ->when(!empty($run), function ($q) use ($run) {
                        $q->whereHas('despacho', function ($w) use ($run) {
                            $w->where('run', $run);
                        });
                    })
                    ->when(!empty($modelo), function ($q) use ($modelo) {
                        $q->where('modelo', $modelo);
                    })
                    ->orderBy('modelo', 'DESC')
                    ->get();
            } else {
                // Log::alert("PASO2");

                $despachos = Despachos::with(['items.despacho', 'items.kanban', 'items.ubicacion.deposito', 'pedido', 'items.FgDataSar', 'items.FgData'])
                    ->when(!empty($run), function ($q) use ($run) {
                        $q->where('run', $run);
                    })
                    ->where('fecha', $fecha)
                    ->when(!empty($modelo), function ($q) use ($modelo) {
                        $q->whereHas('items', function ($w) use ($modelo) {
                            $w->where('modelo', $modelo);
                        });
                    })
                    ->orderBy('run', 'DESC')
                    ->first();
            }
        }

        if ($despachos) {
            // Log::alert($despachos);
            return $this->setResponse($despachos->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function show($despachoId) {
        $despachos = Despachos::with(['items.kanban', 'items.ubicacion', 'pedido'])
            ->where('id', $despachoId)->first();

        if ($despachos) {
            $fechaActual = date('Y-m-d');
            $fechaRun = DateTime::createFromFormat('Y-m-d', $despachos->fecha);

            if ($despachos->run == 1 && ($fechaActual == $fechaRun->format('Y-m-d'))) {
                $fechaRun = $fechaRun->sub(new DateInterval('P1D'));
                $fechaActual = $fechaRun->format('Y-m-d');
            }

            $horaDesde = DateTime::createFromFormat('Y-m-d H:i:s', $fechaActual . $this->horaDesdeRun($despachos->run));
            $horaHasta = DateTime::createFromFormat('Y-m-d H:i:s', $fechaActual . $this->horaHastaRun($despachos->run));

            $enProduccion = PlanProduccion::where('fecha', $fechaActual)
                ->whereBetween('hora_esperada', [$horaDesde->format('Y-m-d H:i:s'), $horaHasta->format('Y-m-d H:i:s')])
                ->get();

            $despachos->produccion = $enProduccion;

            return $this->setResponse($despachos->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    private function searchForId($value, $array, $field) {
        foreach ($array as $key => $val) {
            if ($val[$field] === $value) {
                return $key;
            }
        }
        return "";
    }

    public function getKanbansEnCuarentena() {

        $data = Despachos::with('itemsEnCuarentena')
            ->whereHas('items', function ($q) {
                $q->where('cuarentena', true);
            })
            ->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function cambiarEstadoItemDespacho(Request $request) {

        $itemId = $request->itemId;
        $estado = $request->estado;
        $despachoId = $request->despachoId;
        $kanban = $request->kanban;

        if ($itemId) {
            $existe = DespachosItems::where('id', $itemId)->where('despacho_id', $despachoId)->first(); //where('estado_calidad', null)->where('cuarentena', true)->first();
        } else {
            if ($despachoId) {
                $existe = DespachosItems::where('kanban', $kanban)->where('despacho_id', $despachoId)->first(); //where('estado_calidad', null)->where('cuarentena', true)->first();
            } else {
                $existe = DespachosItems::where('kanban', $kanban)->first(); //where('cuarentena', true)->where('estado_calidad', null)->first();
            }
        }

        if (!$existe) {
            return $this->setResponse([], 'El kanban ingresado no existe en el despacho actual o ya se actualizo el estado', true);
        }

        DespachosItems::where('id', $existe->id)->update([
            'fecha_calidad'     => date('Y-m-d H:i:s'),
            'estado_calidad'    => $estado,
            'cuarentena'        => $estado == EstadosCuarentena::APROBADO ? false : true,
            'user_id_calidad'   => null //TODO REVISAR // auth()->guard('api')->user()->id
        ]);

        return $this->setResponse([], 'Validado correctamente');
    }

    public function asignarKanbanEnvio(Request $request) {
        $kanban = $request->kanban;
        $kanbanEnvio = $request->kanban_envio;
        $despachoId = $request->despachoId;

        //Verifico si el kanban de envio no está asignado a otro envio
        $existe = DespachosItems::where('kanban_envio', $kanbanEnvio)->first();
        if ($existe) {
            return $this->setResponse([], "El kanban de envío ya se utilizo para el kanban " . $existe->kanban, true);
        }

        //Verifico si existe el kanban en el despacho, si no tiene asignado ya un envio o si fue pickeado
        $existeKanban = DespachosItems::where('kanban', $kanban)
            ->where('despacho_id', $despachoId)
            ->where('kanban_envio', null)
            ->where('pickeado', true)
            ->first();
        if (!$existeKanban) {
            return $this->setResponse([], "El kanban no existe en el despacho, ya fue asignado a un envío o no fue pickeado aún.", true);
        }

        $dataEnvio = explode("-", $kanbanEnvio);
        //Verifico si el kanban de envio coincide con el run y con el modelo
        $despacho = Despachos::where('id', $despachoId)->first();
        if (intval($dataEnvio[1]) != intval($despacho->run)) {
            return $this->setResponse([], "El kanban de envío no corresponde al RUN actual", true);
        }

        if ($dataEnvio[3] != $existeKanban->modelo) {
            return $this->setResponse([], "El kanban de envío no corresponde con el modelo del kanban indicado", true);
        }

        try {
            DespachosItems::where('despacho_id', $despachoId)->where('kanban', $kanban)->update(['kanban_envio' => $kanbanEnvio]);
        } catch (\Throwable $th) {
            //throw $th;
            return $this->setResponse([], 'Ocurrió un error al asignar. Intente nuevamente.', true);
        }

        return $this->setResponse([], "Asignado correctamente");
    }

    public function retornarADollys(Request $request) {

        $id = $request->id;

        $item = DespachosItems::where('id', $id)->first();

        if (!$item) {
            return $this->setResponse([], 'No existe');
        }

        $posicionDespacho = Ubicaciones::where('deposito_id', Depositos::DESPACHOS)->where('habilitada', true)->first();
        $posicionDollys = Ubicaciones::where('deposito_id', Depositos::DOLLYS)->where('habilitada', true)->first();


        //Muevo al deposito de dollys

        $movimiento = Movimientos::create([
            'unidad_id'     => WmsUnidades::KANBAN,
            'ubicacion_id'  => null,
            'finalizado'    => true
        ]);

        MovimientosContenido::create([
            'movimiento_id'     => $movimiento->id,
            'ref'               => $item->kanban,
            'cantidad'          =>  -1,
            'ubicacion_id'      => $posicionDespacho->id,
            'unidad_id'         => WmsUnidades::KANBAN
        ]);

        MovimientosContenido::create([
            'movimiento_id'     => $movimiento->id,
            'ref'               => $item->kanban,
            'cantidad'          => 1,
            'ubicacion_id'      => $posicionDollys->id,
            'unidad_id'         => WmsUnidades::KANBAN
        ]);

        DespachosItems::where('id', $item->id)->update([
            'deposito_id'       => Depositos::DOLLYS,
            'posicion'          => null,
            'kanban'            => null,
            'estado_calidad'    => null,
            'user_id_calidad'   => null,
            'fecha_calidad'     => null,
            'pickeado'          => false
        ]);

        return $this->setResponse([], 'Retornaado correctamente!');
    }

    public function destroyItem($id) {

        $existe = DespachosItems::where('id', $id)->first();

        if ($existe) {
            DespachosItems::where('id', $id)->delete();

            $pedido = DespachosPedido::where('despacho_id', $existe->despacho_id)->where('modelo', $existe->modelo)->first();

            if ($pedido) {
                DespachosPedido::where('id', $pedido->id)->update(['pedido' => $pedido->pedido - 1]);
            }


            return $this->setResponse([]);
        }

        return $this->setResponse([]);
    }

    public function intercambiaRechazado(Request $request) {

        $despachoItemId = $request->despachoItemId;
        $depositoId = $request->depositoId;
        $hayStock = false;
        $existe = DespachosItems::where('id', $despachoItemId)->first();

        if (!$existe) {
            return $this->setResponse([], 'No existe el item seleccionado', true);
        }

        //PASO EL KANBAN A EL DEPOSITO DE RECHAZOS
        $creo = Stock::moveKanbanToAnotherWarehouse($existe->kanban, Depositos::RECHAZOS);
        if (!$creo) {
            return $this->setResponse([], 'No se pudo enviar al deposito de rechazos', true);
        }

        $modelo = $existe->modelo;
        $posicion = null;
        $deposito = ModelsDepositos::where('id', $depositoId)->first();

        //VERIFICO SI HAY STOCK EN EL DEPOSITO INDICADO
        $stock = MovimientosContenido::with(['kanban.modelo', 'reservado', 'ubicacion'])
            ->selectRaw('sum(cantidad) as cantidad')
            ->whereHas('ubicacion', function ($q) use ($depositoId) {
                $q->where('deposito_id', $depositoId);
            })
            ->where('unidad_id', WmsUnidades::KANBAN)
            ->whereHas('kanban.modelo', function ($q) use ($existe) {
                $q->where('nombre', $existe->modelo);
            })
            ->first();

        if ($stock) {
            $hayStock = ($stock->cantidad > 0);
        }

        if (!$deposito->posiciones_piso) {
            //ES POR POSICION, RESPETO FIFO
            $enRack = MovimientosContenido::with(['kanban.modelo', 'reservado', 'ubicacion'])
                ->selectRaw('sum(cantidad) as cantidad,ref,ubicacion_id')
                ->whereHas('ubicacion', function ($q) use ($depositoId) {
                    $q->where('deposito_id', $depositoId);
                })
                ->whereHas('kanban.modelo', function ($q) use ($existe) {
                    $q->where('nombre', $existe->modelo);
                })
                ->groupBy('ref', 'ubicacion_id')
                ->orderBy('ref', 'ASC')
                ->get();

            foreach ($enRack as $rack) {
                if (intval($rack->cantidad) < 0) {
                    continue;
                }

                if ($rack->reservado) {
                    continue;
                }

                if (is_null($posicion)) {
                    $posicion = $rack->ubicacion->nombre;
                    $kanban = $rack->ref;
                    break;
                }
            }
        } else {
            //NO RESPETO FIFO
            $posicion = null;
            $kanban = null;
        }

        if (!$hayStock) {
            return $this->setResponse([], "No hay stock en el deposito indicado", true);
        }

        DespachosItems::create([
            'despacho_id'       => $existe->despacho_id,
            'pickeado'          => false,
            'kanban'            => $kanban,
            'posicion'          => $posicion,
            'modelo'            => $modelo,
            'deposito_id'       => $depositoId,
            'pedido'            => null,
            'cuarentena'        => null,
            'fecha_calidad'     => null,
            'user_id_calidad'   => null,
            'estado_calidad'    => null,
            'kanban_envio'      => null,
            'user_id'           => $existe->user_id,
            'user_id2'          => $existe->user_id2,
            'produccion'        => 0, //$existe->produccion
        ]);

        DespachosItems::where('id', $despachoItemId)->delete();

        return $this->setResponse([]);
    }
}
