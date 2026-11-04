<?php

namespace App\Http\Controllers;

use App\Http\Depositos;
use App\Http\Kanban;
use App\Http\Stock;
use App\Http\WmsUnidades;
use App\Models\DespachosItems;
use App\Models\Kanbans;
use App\Models\Movimientos;
use App\Models\MovimientosContenido;
use App\Models\StockKanbans;
use App\Models\Ubicaciones;
use App\Models\UbicacionesMovimientos;
use App\Models\UnidadesUbicaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UbicacionesController extends Controller {
    private function normalizePositionName($name): string {
        return strtoupper(trim(str_replace("'", "-", strval($name ?? ''))));
    }

    public function temp() {
        $data = Stock::getFreePosition(1);
        return $this->setResponse($data->toArray());
    }

    public function cambiarEstadoUbicacion(Request $request) {
        $posicionId = $request->posicionId;
        $bloquea = $request->bloquear;

        Ubicaciones::where('id', $posicionId)->update([
            'habilitada' => $bloquea ? false : true
        ]);

        return $this->setResponse([]);
    }

    public function index() {

        //Obtener las ubicaciones disponibles

        // Ubicaciones::create(['deposito_id' => 1, 'nombre' => 'AA1', 'orden' => 1]);
        // Ubicaciones::create(['deposito_id' => 1, 'nombre' => 'AA2', 'orden' => 2]);
        // Ubicaciones::create(['deposito_id' => 1, 'nombre' => 'AA3', 'orden' => 3]);
        $data = Ubicaciones::with(['ocupacion.contenido.contenido'])->get();

        // foreach ($data as $d) {
        //     $d->contenidoUbicacion = $d->contenido;
        //     Log::alert($d);
        // }

        return $this->setResponse($data->toArray());
    }

    public function consultarKanbanPosicionLibre(Request $request) {
        $kanbanCode = $request->kanban;
        $user1 = $request->user1;
        $user2 = $request->user2;

        $unidad = WmsUnidades::KANBAN;

        $deposito = null;
        $posicion = null;
        $posicionId = null;

        try {
            $kanban = Kanban::registrarKanbanSiNoExiste($kanbanCode);
        } catch (\Throwable $th) {
            $kanban = null;
        }

        if (!$kanban) {
            return $this->setResponse([], 'El kanban ingresado no existe', true);
        }

        $kanban = Kanbans::with(['modelo'])->where('codigo', $kanban->codigo)->first();

        //Verifico si el kanban no esta guardado
        $existe = StockKanbans::where('ref', $kanban->codigo)->where(function ($q) {
            $q->where('deposito_id', '<>', Depositos::DOLLYS);
            $q->where('deposito_id', '<>', Depositos::TEMPORAL_A);
        })
            ->first();

        if ($existe) {
            $posicionId = $existe->ubicacion;
        }

        if (!is_null($posicionId)) {
            // $posicion = Ubicaciones::where('id', $posicionId)->first();
            return $this->setResponse([], 'El kanban ya se encuentra almacenado en ' . $posicionId, true);
        }

        //Verifico si el kanban está en dollys, solo puedo guardar en racks si esta en dollys o temporal A
        $existeEnDollys = StockKanbans::where('ref', $kanban->codigo)
            ->where(function ($q) {
                $q->where('deposito_id',  Depositos::DOLLYS);
                $q->orWhere('deposito_id',  Depositos::TEMPORAL_A);
            })
            ->first();

        if ($existeEnDollys) {
            $posicionId = $existeEnDollys->ubicacion_id;
        }

        if (!$posicionId) {
            return $this->setResponse([], 'El kanban no se encuentra en dollys, debe pasar por Finish Good', true);
        }

        //VERIFICO SI EL KANBAN NO ESTA EN UN DESPACHO, SI ES ASI NO PUEDO TOMARLO
        // $enDespacho = DespachosItems::whereHas('despacho', function ($q) {
        //     $q->where('pendiente', true);
        // })
        //     ->where('kanban', $kanban->codigo)
        //     ->first();
        //SI EL KANBAN SE DESPACHO YA NO PUEDE VOLVER A GUARDARSE
        $enDespacho = DespachosItems::where('kanban', $kanban->codigo)->first();
        if ($enDespacho) {
            return $this->setResponse([], 'El kanban se encuentra despachado', true);
        }

        $posicion = null;
        $depositosPrioridades = [8];
        foreach ($depositosPrioridades as $dep) {
            $posicion = Stock::getFreePosition2($dep, $unidad, 1, true);
            if ($posicion) {
                $deposito = $dep;
                break;
            }
        }

        if (!$posicion) {
            //SI NO HAY POSICION, VERIFICO SI ESTÁ EN TEMPORAL, NO LO METO NUEVAMENTE
            $existeEnTemporal = StockKanbans::where('ref', $kanban->codigo)->where(function ($q) {
                $q->where('deposito_id',  Depositos::TEMPORAL_A);
            })->first();

            if ($existeEnTemporal) {
                $ubicacionTemporal = $this->normalizePositionName($existeEnTemporal->ubicacion ?? '');
                return $this->setResponse([], 'El kanban ya se encuentra almacenado en ' . $ubicacionTemporal . ' y no hay posiciones disponibles.', true);
            }



            //SI NO HAY POSICION, LO METO EN TEMPORAL AUTOMATICAMENTE
            $ubicacionTemporal = Ubicaciones::where('deposito_id', Depositos::TEMPORAL_A)->first();
            // Log::alert($ubicacionTemporal);
            if (!$ubicacionTemporal) {
                return $this->setResponse([], 'NO HAY POSICIÓN DISPONIBLE', true);
            }

            $payload = [
                'unidad_id'     => WmsUnidades::KANBAN,
                'ubicacion_id'  => null,
                'finalizado'    => false,
                'user_id'       => $user1,
                'user_id2'      => $user2
            ];

            $movimiento = Movimientos::create($payload);

            MovimientosContenido::create([
                'movimiento_id'     => $movimiento->id,
                'ref'               => strtoupper($kanban->codigo),
                'cantidad'          => 1,
                'ubicacion_id'      => $ubicacionTemporal->id,
                'unidad_id'         => WmsUnidades::KANBAN
            ]);

            //LO ELIMINO DE OTRA POSICION
            MovimientosContenido::where('ubicacion_id', '<>', $ubicacionTemporal->id)->where('ref', strtoupper($kanban->codigo))->delete();

            $posicion = $ubicacionTemporal;
            $deposito = Depositos::TEMPORAL_A;
        }

        if ($posicion && !is_null($posicion->nombre)) {
            // Estandariza la visualización para usar siempre A-1-0, A-1-1, etc.
            $posicion->nombre = $this->normalizePositionName($posicion->nombre);
        }

        return $this->setResponse([
            'deposito'  => $deposito,
            'posicion'  => $posicion,
            'kanban'    => $kanban,
        ]);
    }

    public function posicionesLibres(Request $request, $depositoId = null) {
        $deposito = intval($depositoId ?? $request->deposito ?? Depositos::RACKS);
        if ($deposito <= 0) {
            $deposito = Depositos::RACKS;
        }

        $ubicaciones = Ubicaciones::where('deposito_id', $deposito)
            ->where('habilitada', true)
            ->orderBy('orden', 'DESC')
            ->get(['id', 'nombre', 'orden', 'capacidad']);

        $ocupacionKanban = MovimientosContenido::selectRaw('ubicacion_id, SUM(cantidad) as cantidad')
            ->where('unidad_id', WmsUnidades::KANBAN)
            ->groupBy('ubicacion_id')
            ->pluck('cantidad', 'ubicacion_id');

        $capacidadKanban = UnidadesUbicaciones::where('unidad_id', WmsUnidades::KANBAN)
            ->pluck('capacidad', 'ubicacion_id');

        $posicionesLibres = [];
        foreach ($ubicaciones as $ubicacion) {
            $ubicacionId = intval($ubicacion->id);
            $capacidad = intval($capacidadKanban[$ubicacionId] ?? 0);
            if ($capacidad <= 0) {
                continue;
            }

            $cantidadActual = floatval($ocupacionKanban[$ubicacionId] ?? 0);
            if ($cantidadActual > 0) {
                continue;
            }

            $ubicacion->nombre = $this->normalizePositionName($ubicacion->nombre);
            $posicionesLibres[] = $ubicacion;
        }

        return $this->setResponse([
            'deposito' => $deposito,
            'posiciones' => $posicionesLibres,
            'total' => count($posicionesLibres),
        ]);
    }

    public function consultarContenidoPosicion(Request $request) {
        $posicionId = $request->posicionId;

        $posicion = Ubicaciones::with('deposito')->where('id', $posicionId)->first();

        $existe = MovimientosContenido::selectRaw('ubicacion_id, sum(cantidad) as cantidad')
            ->where('ubicacion_id', $posicionId)
            ->groupBy('ubicacion_id')
            ->get();

        $libre = true;

        if ($posicion->deposito->posiciones_piso == 1 || $posicion->deposito->posiciones_piso == "1" || $posicion->deposito->posiciones_piso == true) {
            $libre = true;
        } else {
            foreach ($existe as $ub) {
                if (intval($ub->cantidad) > 0) {
                    $libre = false;
                    break;
                }
            }
        }

        return $this->setResponse(['libre' => $libre, 'ubicacion_id' => $posicionId]);
    }

    public function consultarPosicion(Request $request) {
        $producto = $request->producto;
        $deposito = null;
        $posicion = null;

        //Consulto posicion disponible, dando prioridad segun configuración
        $depositosPrioridades = [8, 1, 2, 3, 4];
        foreach ($depositosPrioridades as $dep) {
            $posicion = Stock::getFreePosition($dep);
            // Log::alert($posicion);
            if ($posicion) {
                $deposito = $dep;
                break;
            }
        }

        return $this->setResponse([
            'deposito'  => $deposito,
            'posicion'  => $posicion
        ]);
    }

    public function listarPosicionesLibres(Request $request, $depositoId = null) {
        $depositoId = intval($depositoId ?? $request->deposito_id ?? Depositos::RACKS);
        $limit = intval($request->limit ?? 20);

        if ($limit <= 0) {
            $limit = 20;
        }

        if ($limit > 500) {
            $limit = 500;
        }

        $ubicaciones = StockKanbans::selectRaw('sum(cantidad) as cantidad, ubicaciones.id as ubicacion_id, ubicaciones.nombre, VT_STOCK_KANBANS.deposito, max(ubicaciones.orden) as orden')
            ->rightJoin('ubicaciones', 'ubicaciones.id', '=', 'ubicacion_id')
            ->where('ubicaciones.deposito_id', $depositoId)
            ->where('ubicaciones.habilitada', true)
            ->groupBy('VT_STOCK_KANBANS.deposito', 'ubicaciones.id', 'ubicaciones.nombre')
            ->orderBy('orden', 'DESC')
            ->get();

        $disponibles = [];
        foreach ($ubicaciones as $ubicacion) {
            if (!is_null($ubicacion->deposito)) {
                continue;
            }

            $canStore = Stock::canStoreInPosition(WmsUnidades::KANBAN, intval($ubicacion->ubicacion_id), 1);
            if (!$canStore) {
                continue;
            }

            $disponibles[] = [
                'id' => intval($ubicacion->ubicacion_id),
                'nombre' => $ubicacion->nombre,
                'orden' => intval($ubicacion->orden),
            ];

            if (count($disponibles) >= $limit) {
                break;
            }
        }

        return $this->setResponse([
            'deposito_id' => $depositoId,
            'cantidad' => count($disponibles),
            'posiciones' => $disponibles,
        ]);
    }


    public function create() {
        //
    }


    public function store(Request $request) {
        //ALMACENO
    }


    public function show(Ubicaciones $ubicaciones) {
        //
    }


    public function edit(Ubicaciones $ubicaciones) {
        //
    }


    public function update(Request $request, Ubicaciones $ubicaciones) {
        //
    }


    public function destroy(Ubicaciones $ubicaciones) {
        //
    }
}
