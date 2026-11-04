<?php

namespace App\Http\Controllers;

use App\Http\Depositos;
use App\Http\Estados;
use App\Http\Kanban;
use App\Http\Stock;
use App\Http\WmsUnidades;
use App\Models\Depositos as ModelsDepositos;
use App\Models\Kanbans;
use App\Models\Movimientos;
use App\Models\MovimientosContenido;
use App\Models\StockKanbans;
use App\Models\UbicacionContenido;
use App\Models\Ubicaciones;
use App\Models\UbicacionesMovimientos;
use App\Support\PositionName;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UbicacionesMovimientosController extends Controller {

    private function normalizeScanValue($value): string {
        return strtoupper(trim(str_replace("'", "-", strval($value ?? ''))));
    }

    public function index() {
        //
    }

    public function moverRechazos(Request $request) {

        $kanban = $request->kanban;

        //Busco en que posicion está
        $stock = StockKanbans::where('ref', $kanban)->first();

        if (!$stock) {
            return $this->setResponse([], 'El kanban no existe en stock', true);
        }

        $posicionRechazo = Ubicaciones::where('deposito_id', Depositos::RECHAZOS)->where('habilitada', true)->first();
        $posicionDollys = Ubicaciones::where('deposito_id', Depositos::DOLLYS)->where('habilitada', true)->first();

        if (!$posicionRechazo) {
            return $this->setResponse([], 'No existe una posición de rechazos.', true);
        }

        //VERIFICO SI EXISTE EN RECHAZOS
        $existeRechazo = MovimientosContenido::selectRaw('sum(cantidad) as cantidad')->where('ubicacion_id', $posicionRechazo->id)->where('ref', $kanban)->first();
        $existe = false;
        if ($existeRechazo) {
            if ($existeRechazo->cantidad > 0) {
                $existe = true;
            }
        }

        $movimiento = Movimientos::create([
            'unidad_id'     => WmsUnidades::KANBAN,
            'ubicacion_id'  => null,
            'finalizado'    => false
        ]);

        if ($existe) {
            //LO INSERTO EN DOLLYS
            MovimientosContenido::create([
                'movimiento_id'     => $movimiento->id,
                'ref'               => $kanban,
                'cantidad'          => 1,
                'ubicacion_id'      => $posicionDollys->id,
                'unidad_id'         => WmsUnidades::KANBAN,
                'lote'              => 'DESBALANCEO'
            ]);

            //LO SACO E INSERTO EN DOLLYS
            MovimientosContenido::where('ubicacion_id', $posicionRechazo->id)->where('ref', $kanban)->delete();

            return $this->setResponse([], 'Kanban ingresado a dollys correctamente!');
        } else {
            //LO INSERTO EN CUARENTENA


            MovimientosContenido::create([
                'movimiento_id'     => $movimiento->id,
                'ref'               => $kanban,
                'cantidad'          => 1,
                'ubicacion_id'      => $posicionRechazo->id,
                'unidad_id'         => WmsUnidades::KANBAN,
                'lote'              => 'DESBALANCEO'
            ]);

            //LO SACO DE DOLLYS
            MovimientosContenido::where('ubicacion_id', $stock->ubicacion_id)->where('ref', $kanban)->delete();

            return $this->setResponse([], 'Kanban ingresado a cuarentena correctamente!');
        }
    }

    public function transferenciaEntrePosiciones(Request $request) {
        // $depositoOrigen = intval($request->deposito_or);
        // $depositoDestino = intval($request->deposito_dest);
        $posicionOrigen = intval($request->pos_or);
        $posicionDestino = intval($request->pos_dest);
        $kanban = $request->kanban;
        $userId = intval($request->user1);

        if (!$posicionDestino) {
            return $this->setResponse([], 'Debe informar la posición destino.', true);
        }

        if (!is_null($kanban)) {
            //Verifico si el kanban esta en la posición indicada
            $disponible = MovimientosContenido::selectRaw('ubicacion_id, sum(cantidad) as cantidad')
                ->where('ubicacion_id', $posicionOrigen)
                ->where('ref', $kanban)
                ->groupBy('ubicacion_id')
                ->first();

            if (!$disponible) {
                return $this->setResponse([], 'El kanban no se encuentra en la posición indicada.', true);
            }
        } else {
            //Verifico si hay contenido en posicion origen
            $disponible = MovimientosContenido::selectRaw('ubicacion_id, sum(cantidad) as cantidad')
                ->where('ubicacion_id', $posicionOrigen)
                ->groupBy('ubicacion_id')
                ->get();

            $origenOk = false;

            foreach ($disponible as $ub) {
                if (intval($ub->cantidad) > 0) {
                    $origenOk = true;
                    break;
                }
            }

            if (!$origenOk) {
                return $this->setResponse([], 'La posición origen no tiene contenido.', true);
            }
        }


        $posicionD = Ubicaciones::with('deposito')->where('id', $posicionDestino)->first();

        if (!$posicionD) {
            return $this->setResponse([], 'La posición destino no existe.', true);
        }

        //Verifico si la posicion destino está libre
        $disponible = MovimientosContenido::selectRaw('ubicacion_id, sum(cantidad) as cantidad')
            ->where('ubicacion_id', $posicionDestino)
            ->groupBy('ubicacion_id')
            ->get();

        $destinoOk = true;

        if ($posicionD->deposito->posiciones_piso == 1 || $posicionD->deposito->posiciones_piso == "1" || $posicionD->deposito->posiciones_piso == true) {
            $destinoOk = true;
        } else {

            foreach ($disponible as $ub) {
                if (intval($ub->cantidad) > 0) {
                    $destinoOk = false;
                    break;
                }
            }
        }

        if (!$destinoOk) {
            return $this->setResponse([], 'La posición destino está ocupada.', true);
        }

        DB::beginTransaction();

        //Genero un movimiento
        $movimiento = Movimientos::create([
            'unidad_id'     => WmsUnidades::KANBAN,
            'ubicacion_id'  => null,
            'finalizado'    => true,
            'user_id'       => $userId > 0 ? $userId : null,
        ]);

        if (!$movimiento) {
            DB::rollBack();
            // Log::error($movimiento);
            return $this->setResponse([], 'Ocurrió un error.', true);
        }

        if (!is_null($kanban)) {
            $contenido = MovimientosContenido::selectRaw('sum(cantidad) as cantidad,ref,unidad_id,lote')
                ->where('ubicacion_id', $posicionOrigen)
                ->where('ref', $kanban)
                ->groupBy('ref', 'unidad_id', 'lote')
                ->get();
        } else {
            $contenido = MovimientosContenido::selectRaw('sum(cantidad) as cantidad,ref,unidad_id,lote')
                ->where('ubicacion_id', $posicionOrigen)
                ->groupBy('ref', 'unidad_id', 'lote')
                ->havingRaw('SUM(cantidad) > ?', [0])
                ->get();
        }

        //Doy de baja el contenido original y lo doy de alta en la nueva posición
        foreach ($contenido as $item) {
            MovimientosContenido::create([
                'movimiento_id'     => $movimiento->id,
                'ref'               => $item->ref,
                'cantidad'          => $item->cantidad * -1,
                'ubicacion_id'      => $posicionOrigen,
                'unidad_id'         => $item->unidad_id,
                'lote'              => $item->lote
            ]);

            MovimientosContenido::create([
                'movimiento_id'     => $movimiento->id,
                'ref'               => $item->ref,
                'cantidad'          => $item->cantidad,
                'ubicacion_id'      => $posicionDestino,
                'unidad_id'         => $item->unidad_id,
                'lote'              => $item->lote
            ]);
        }

        Db::commit();

        return $this->setResponse([], 'Transferencia exitosa');
    }

    public function moverKanbanEntreRacks(Request $request) {
        $kanban = $this->normalizeScanValue($request->kanban);
        $posicionDestinoNombre = $this->normalizeScanValue($request->posicion_destino);
        $confirmarIntercambio = boolval($request->confirmar_intercambio);
        $userId = intval($request->user1);

        if ($userId <= 0) {
            return $this->setResponse([], 'Debe informar el usuario que realiza el movimiento.', true);
        }

        $usuario = User::select('id', 'rol')->where('id', $userId)->first();
        if (!$usuario || intval($usuario->rol) !== 60) {
            return $this->setResponse([], 'No autorizado para mover entre racks.', true);
        }

        if ($kanban == '') {
            return $this->setResponse([], 'Debe informar el kanban.', true);
        }

        if ($posicionDestinoNombre == '') {
            return $this->setResponse([], 'Debe informar la posición destino.', true);
        }

        $stockOrigen = StockKanbans::where('ref', $kanban)
            ->where('cantidad', '>', 0)
            ->first();

        if (!$stockOrigen) {
            return $this->setResponse([], 'El kanban indicado no existe en stock.', true);
        }

        $posicionOrigen = Ubicaciones::where('id', $stockOrigen->ubicacion_id)->first();
        if (!$posicionOrigen) {
            return $this->setResponse([], 'No se pudo identificar la posición de origen.', true);
        }

        if (intval($posicionOrigen->deposito_id) !== Depositos::RACKS) {
            return $this->setResponse([], 'El kanban no se encuentra en RACKS.', true);
        }

        $posicionDestino = Ubicaciones::where('deposito_id', Depositos::RACKS)
            ->where('habilitada', true)
            ->whereRaw('UPPER(nombre) = ?', [$posicionDestinoNombre])
            ->first();

        if (!$posicionDestino) {
            return $this->setResponse([], 'La posición destino no existe en RACKS.', true);
        }

        if (intval($posicionOrigen->id) === intval($posicionDestino->id)) {
            return $this->setResponse([], 'El kanban ya se encuentra en la posición destino.', true);
        }

        $kanbanEnDestino = StockKanbans::where('ubicacion_id', $posicionDestino->id)
            ->where('cantidad', '>', 0)
            ->where('ref', '<>', $kanban)
            ->first();

        if ($kanbanEnDestino && !$confirmarIntercambio) {
            return $this->setResponse([
                'requiere_intercambio'   => true,
                'kanban_destino'         => $kanbanEnDestino->ref,
                'posicion_origen'        => $this->normalizeScanValue($posicionOrigen->nombre),
                'posicion_destino'       => $this->normalizeScanValue($posicionDestino->nombre),
            ], 'La posición destino está ocupada por ' . $kanbanEnDestino->ref . '. ¿Desea intercambiar?', true);
        }

        if (!$kanbanEnDestino && !Stock::canStoreInPosition(WmsUnidades::KANBAN, $posicionDestino->id, 1)) {
            return $this->setResponse([], 'La posición destino no tiene capacidad.', true);
        }

        DB::beginTransaction();

        try {
            $movimiento = Movimientos::create([
                'unidad_id'     => WmsUnidades::KANBAN,
                'ubicacion_id'  => null,
                'finalizado'    => true,
                'user_id'       => $userId > 0 ? $userId : null,
            ]);

            if (!$movimiento) {
                DB::rollBack();
                return $this->setResponse([], 'No se pudo registrar el movimiento.', true);
            }

            MovimientosContenido::create([
                'movimiento_id'     => $movimiento->id,
                'ref'               => $kanban,
                'cantidad'          => -1,
                'ubicacion_id'      => $posicionOrigen->id,
                'unidad_id'         => WmsUnidades::KANBAN,
                'lote'              => $stockOrigen->lote ?? null,
            ]);

            MovimientosContenido::create([
                'movimiento_id'     => $movimiento->id,
                'ref'               => $kanban,
                'cantidad'          => 1,
                'ubicacion_id'      => $posicionDestino->id,
                'unidad_id'         => WmsUnidades::KANBAN,
                'lote'              => $stockOrigen->lote ?? null,
            ]);

            if ($kanbanEnDestino) {
                MovimientosContenido::create([
                    'movimiento_id'     => $movimiento->id,
                    'ref'               => $kanbanEnDestino->ref,
                    'cantidad'          => -1,
                    'ubicacion_id'      => $posicionDestino->id,
                    'unidad_id'         => WmsUnidades::KANBAN,
                    'lote'              => $kanbanEnDestino->lote ?? null,
                ]);

                MovimientosContenido::create([
                    'movimiento_id'     => $movimiento->id,
                    'ref'               => $kanbanEnDestino->ref,
                    'cantidad'          => 1,
                    'ubicacion_id'      => $posicionOrigen->id,
                    'unidad_id'         => WmsUnidades::KANBAN,
                    'lote'              => $kanbanEnDestino->lote ?? null,
                ]);
            }

            DB::commit();
            $origenNombre = $this->normalizeScanValue($posicionOrigen->nombre);
            $destinoNombre = $this->normalizeScanValue($posicionDestino->nombre);

            return $this->setResponse([
                'movimiento_id'      => $movimiento->id,
                'kanban'             => $kanban,
                'origen'             => $origenNombre,
                'destino'            => $destinoNombre,
                'intercambio'        => $kanbanEnDestino ? true : false,
                'kanban_intercambio' => $kanbanEnDestino ? $kanbanEnDestino->ref : null,
                'fecha'              => $movimiento->created_at,
                'user_id'            => $movimiento->user_id,
            ], $kanbanEnDestino ? 'Intercambio realizado correctamente.' : 'Movimiento realizado correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("UbicacionesMovimientosController::moverKanbanEntreRacks : " . $th->getMessage());
            return $this->setResponse([], 'Ocurrió un error al mover el kanban.', true);
        }
    }

    public function transferencias(Request $request) {
        $depositoOrigen = intval($request->deposito_or);
        $depositoDestino = intval($request->deposito_dest);
        $posicionOrigen = intval($request->pos_or);
        $posicionDestino = intval($request->pos_dest);
        $kanban = $request->kanban;

        if (!$posicionOrigen) {
            //Verifico existencia Kanban en deposito
            $data = Ubicaciones::with('ocupacion.contenido')
                ->where('deposito_id', $depositoOrigen)
                ->whereHas('ocupacion.contenido', function ($q) use ($kanban) {
                    $q->where('contenido', $kanban);
                })
                ->first();

            // Log::alert($data);

            if (!$data) {
                return $this->setResponse([], 'El kanban ingresado no existe en el deposito indicado', true);
            }
        } else {
            //Verifico si la posición origen tiene contenido
            if (Stock::canStoreInPosition($depositoOrigen, $posicionOrigen)) {
                return $this->setResponse([], 'La posición origen no tiene contenido', true);
            }
        }

        //Verifico si la posición destino está vacia
        if (!Stock::canStoreInPosition($depositoDestino, $posicionDestino)) {
            return $this->setResponse([], 'La posición destino no tiene capacidad', true);
        }

        // return $this->setResponse([], 'OK', true);


        //Descargo de origen
        $descargo = Stock::downloadContentPosition($depositoOrigen, $posicionOrigen, $kanban);

        if ($descargo) {
            //Cargo en destino
            $creo = Stock::createMovement($posicionDestino, $descargo->ocupacion->contenido[0]->contenido, $descargo->ocupacion->contenido[0]->detalle);
            if (!$creo) {
                //Retorno el stock a su posicion original
                Stock::restoreContentPosition($descargo->ocupacion->id);
                return $this->setResponse([], "Ocurrió un error. Comuniquese con el encargado de sistemas.", true);
            }
        } else {
            return $this->setResponse([], 'No se pudo realizar la transferencia. Comuniquese con el encargado de sistemas.', true);
        }

        return $this->setResponse([]);
    }

    public function store(Request $request) {
        //Al almacenar, verifico que producto es, si es asignación automática, donde lo envio
        $asignacionAutomatica = $request->automatico;
        $producto = $request->producto;
        $tipoProducto = $request->tipoProducto;
        $deposito = $request->deposito;
        $productos = $request->productos;
        $lote = $request->lote;
        $posicionId = (int) $request->posicion; //Esto es si informo posicion manual, si no la informo, tomo la automatica del deposito indicado
        $posicionName =  $request->posicionName; //Esto es si informo posicion manual, si no la informo, tomo la automatica del deposito indicado
        $posicion = null;

        if ((!$producto || !$tipoProducto) && count($productos) == 0) {
            return $this->setResponse([], 'Debe indicar el producto a almacenar', true);
        }

        if (!$asignacionAutomatica) {
            if (!$deposito) {
                return $this->setResponse([], 'Debe informar el deposito', true);
            }

            if (!$posicionId && !$posicionName) {
                //Obtengo la posición del deposito indicado
                $posicion = Stock::getFreePosition($deposito);
            } else {
                if (!$posicionName) {
                    $posicion = Ubicaciones::where('id', $posicionId)->first();
                } else {
                    $positionCandidates = PositionName::candidates($posicionName);
                    $posicion = Ubicaciones::whereIn('nombre', $positionCandidates)
                        ->where('deposito_id', $deposito)
                        ->first();
                    // Log::alert($posicion);
                }
            }

            if (!$posicion) {
                //Si no encontre disponibilidad
                return $this->setResponse([], 'No hay espacio suficiente en el deposito indicado', true);
            }

            //Verifico si la posición informada tiene capacidad/esta libre
            if (!Stock::canStoreInPosition($deposito, $posicion->id)) {
                return $this->setResponse([], 'No hay espacio suficiente en la posición indicada', true);
            }
        }

        if ($asignacionAutomatica) {
            //Busco si el producto tiene prioridad de depositos

            if (!$deposito) {
                //TODO obtener de base por producto
                $depositosPrioridades = [8, 1, 10, 11];
                foreach ($depositosPrioridades as $dep) {
                    $posicion = Stock::getFreePosition($dep);
                    if ($posicion) {
                        $deposito = $dep;
                        break;
                    }
                }
            } else {
                $posicion = Stock::getFreePosition($deposito);
            }

            if (!$posicion) {
                return $this->setResponse([], 'No hay ubicaciones disponibles', true);
            }
        }

        try {
            if ($tipoProducto == 'KANBAN') {
                try {
                    $t = Kanban::registrarKanbanSiNoExiste($producto);
                } catch (\Throwable $th) {
                    //throw $th;
                    return $this->setResponse([], $th->getMessage(), true);
                }

                //Verifico si no esta almacenado
                $existe = UbicacionContenido::where('detalle', 'KANBAN')->where('contenido', $producto)->first();
                if ($existe) {
                    return $this->setResponse([], "El kanban ya se encuentra almacenado", true);
                }
            }

            //ALMACENO
            // Creo el movimiento
            if ($tipoProducto == "KANBAN") {
                $lote = substr($producto, 5, 2) . '/' . substr($producto, 3, 2) . '/20' . substr($producto, 1, 2);
                $creo = Stock::createMovement($posicion->id, $producto, $tipoProducto, $lote);
                if (!$creo) {
                    //Retorno el stock a su posicion original
                    return $this->setResponse([], "Ocurrió un error. Comuniquese con el encargado de sistemas.", true);
                }

                //SI ALMACENO Y ES KANBAN, INTENTO CAMBIARLE EL ESTADO
                if ($tipoProducto == 'KANBAN') {
                    $kanban = Kanbans::where('codigo', $producto)->first();
                    Kanban::changeStatus($kanban, Estados::FINALIZADO);
                }
            } else {
                $creo = Stock::createMovement($posicion->id, '', $tipoProducto, '', $productos);
                if (!$creo) {
                    //Retorno el stock a su posicion original
                    return $this->setResponse([], "Ocurrió un error. Comuniquese con el encargado de sistemas.", true);
                }
            }


            return $this->setResponse(['posicion' => $posicion]);
        } catch (\Throwable $th) {
            Log::error("UbicacionesMovimientosController::store : " . $th->getMessage());
            return $this->setResponse([], "", true);
        }
    }

    public function reporte(Request $request) {
        //UTILIZADO PARA REPORTAR STOCK
        //OBTENGO LAS POSICIONES, Y SU CONTENIDO
        // $conContenido = $request->conContenido;
        $depositoId = $request->deposito;
        $ubicacion = $request->ubicacion;
        $modeloId = $request->modelo;

        // $conContenido = true;

        if ($ubicacion) {
            $ubicacion = str_replace("'", "-", $ubicacion);
        }

        //SI FILTRO MODELO, FILTRO POR TIPO DE PRODUCTO KANBAN
        if ($modeloId) {
            $data = Ubicaciones::with(['deposito', 'ocupacion.cont'])->withCount('ocupacion')
                ->when(!empty($depositoId), function ($q) use ($depositoId) {
                    $q->where('deposito_id', $depositoId);
                })
                ->when(!empty($ubicacion), function ($q) use ($ubicacion) {
                    $q->whereIn('nombre', PositionName::candidates($ubicacion));
                })
                ->whereHas('ocupacion', function ($q) use ($modeloId) {
                    $q->where('contenido', 'KANBAN');
                    $q->whereHas('cont.kanban', function ($query) use ($modeloId) {
                        $query->where('modelo_id', $modeloId);
                    });
                })
                ->has('ocupacion', '>', 0)
                ->get();
        } else {
            $data = Ubicaciones::with(['deposito', 'ocupacion.cont'])->withCount('ocupacion')
                ->when(!empty($depositoId), function ($q) use ($depositoId) {
                    $q->where('deposito_id', $depositoId);
                })
                ->when(!empty($ubicacion), function ($q) use ($ubicacion) {
                    $q->whereIn('nombre', PositionName::candidates($ubicacion));
                })
                ->has('ocupacion', '>', 0)
                ->get();
        }

        foreach ($data as $d) {
            if ($d->ocupacion) {
                foreach ($d->ocupacion->cont as $c) {
                    if ($c->detalle == 'KANBAN') {
                        $kanban = Kanban::registrarKanbanSiNoExiste($c->contenido);
                        if (!$c->lote) {
                            $c->lote = substr($c->contenido, 5, 2) . '/' . substr($c->contenido, 3, 2) . '/20' . substr($c->contenido, 1, 2);
                        }
                        if ($kanban) {
                            $c->nombre = $kanban->modelo->nombre;
                        } else {
                            $c->nombre = "INEXISTENTE";
                        }
                    }
                }
            }
        }

        $dataDeposito = ModelsDepositos::withCount('ubicacionesOcupadas', 'ubicaciones')->where('id', $depositoId)->first();

        $response = [
            'stock'         => $data ? $data->toArray() : [],
            'depositoInfo'  => $dataDeposito ? $dataDeposito->toArray() : []
        ];

        return $this->setResponse($response);

        // if ($data) {
        //     return $this->setResponse($data->toArray());
        // } else {
        //     return $this->setResponse([]);
        // }
    }

    public function show(UbicacionesMovimientos $ubicacionesMovimientos) {
        //
    }

    public function edit(UbicacionesMovimientos $ubicacionesMovimientos) {
        //
    }

    public function update(Request $request, UbicacionesMovimientos $ubicacionesMovimientos) {
        //
    }

    public function destroy(UbicacionesMovimientos $ubicacionesMovimientos) {
        //
    }
}
