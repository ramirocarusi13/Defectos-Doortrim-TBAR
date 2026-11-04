<?php

namespace App\Http;

use App\Models\DespachosItems;
use App\Models\Movimientos;
use App\Models\MovimientosContenido;
use App\Models\StockKanbans;
use App\Models\UbicacionContenido;
use App\Models\Ubicaciones;
use App\Models\UbicacionesMovimientos;
use App\Models\UnidadesUbicaciones;
use App\Models\VTMovimientosKanban;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Stock {

    //Obtengo la ubicaciÃƒÂ³n libre de un depopsito
    //TODO Validar capacidad
    static function getFreePosition(int $warehouseId, int $quantity = 1) {
        $data = Ubicaciones::withCount('ocupacion')
            ->where('habilitada', true)
            ->where('deposito_id', $warehouseId)
            ->has('ocupacion', '<', DB::raw('CAST(capacidad as int)'))
            ->orderBy('ocupacion_count', 'ASC')
            ->orderBy('orden', 'ASC')
            ->first();

        return $data ? $data : null;
    }

    //Verifico si la posiciÃƒÂ³n puede almacenar el contenido
    static function canStoreInPosition(int $udId, $positionId, int $quantity = 1): bool {

        // Log::alert($udId);
        // Log::alert($positionId);
        // Log::alert($quantity);
        if ($positionId == 1451 || $positionId == 9 || $positionId == 1450) {
            return true;
        }

        //Obtengo la posicion
        $ubicacion = Ubicaciones::with(['contenido2.unidad', 'unidades'])
            // ->where('deposito_id', $warehouseId)
            ->where('id', $positionId)->where('habilitada', true)
            ->orderBy('orden')->first();

        if (!$ubicacion) {
            //LA UBICACIÃƒâ€œN NO EXISTE
            return false;
        }

        //Obtengo la capacidad en la unidad
        $unit = UnidadesUbicaciones::where('ubicacion_id', $positionId)->where('unidad_id', $udId)->first();
        // Log::alert($unit);

        if (!$unit) {
            //LA UBICACIÃƒâ€œN NO TIENE HABILITADA LA UNIDAD INDICADA
            return false;
        }

        if ($unit->capacidad <= 0) {
            //LA UBICACIÃƒâ€œN NO TIENE CAPACIDAD PARA LA UNIDAD INDICADA
            return false;
        }

        if ($unit->capacidad < $quantity) {
            //LA UBICACIÃƒâ€œN NO TIENE CAPACIDAD PARA LA UNIDAD INDICADA
            return false;
        }

        // $ocupacion = 0;
        // $capacidad = intval($unit->capacidad);
        // $disponible = $capacidad;

        $contenido = MovimientosContenido::selectRaw('sum(isnull(cantidad,0)) as cantidad')
            ->where('ubicacion_id', $positionId)->where('unidad_id', $udId)
            ->first();

        // Log::alert($contenido);

        if (is_null($contenido)) {
            return true;
        }

        if (is_null($contenido->cantidad)) {
            return true;
        }

        try {
            if ($contenido->cantidad > $unit->capacidad) {
                // Log::alert("PASO");
                return false;
            }

            // if ($contenido->cantidad >= $quantity) {
            //     return true;
            // }

            if ($contenido->cantidad == 0) {
                return true;
            }

            // if ($quantity <= $unit->capacidad) {
            //     return true;
            // }
        } catch (\Throwable $th) {
            //throw $th;
            // Log::alert("PASO2");

            Log::alert($th->getMessage());
            return false;
        }

        return false;
    }

    //Verifico si la posiciÃƒÂ³n puede almacenar el contenido
    static function canStoreInPosition2(int $warehouseId, $positionId): bool {
        // Log::alert($warehouseId);
        // Log::alert($positionId);
        $data = Ubicaciones::withCount('ocupacion')
            ->where('deposito_id', $warehouseId)
            ->where('id', $positionId)
            ->where('habilitada', true)
            // ->has('ocupacion', '=', 0)
            ->first();

        // Log::alert($data);
        if (!$data) {
            return False;
        }

        return (intval($data->capacidad) - intval($data->ocupacion_count)) > 0;
    }

    static function restoreContentPosition($movementId) {
        UbicacionesMovimientos::where('id', $movementId)->update(['egreso' => null]);
    }

    static function downloadContentPosition(int $warehouseId, $positionId = null, $kanban = null) {

        if (!$kanban) {
            $content = Ubicaciones::with('ocupacion.contenido')->withCount('ocupacion')
                ->where('deposito_id', $warehouseId)
                ->where('id', $positionId)
                ->has('ocupacion', '>', 0)
                ->first();
        } else {
            // Log::alert("DEP: " . $kanban);
            try {

                $ubicCont = UbicacionContenido::withCount('disponible')->where('contenido', $kanban)
                    ->has('disponible', '>', 0)
                    ->first();
                // Log::alert($ubicCont);

                $content = Ubicaciones::with(['ocupacion'])
                    ->where('deposito_id', $warehouseId)
                    ->whereHas('ocupacion', function ($q) use ($ubicCont) {
                        $q->where('egreso', null);
                        $q->where('id', $ubicCont->movimiento_id);
                    })


                    // ->whereHas('ocupacion.contenido', function ($q) use ($kanban) {
                    //     $q->where('contenido', $kanban);
                    // })
                    ->first();

                if ($content) {
                    $content->ocupacion->contenido = [$ubicCont];
                }
            } catch (\Throwable $th) {
                Log::alert($th->getMessage());
            }

            // return False;
        }

        try {
            if ($content) {
                // Log::alert($content->ocupacion->id);
                if (!$kanban) {
                    $actualizo = UbicacionesMovimientos::where('id', $content->ocupacion->id)->update(['egreso' => date('Ymd')]);
                } else {
                    $actualizo = UbicacionesMovimientos::where('id', $ubicCont->movimiento_id)->update(['egreso' => date('Ymd')]);
                }
                return $content;
            }
        } catch (\Throwable $th) {
            Log::error('Stock::downloadContentPosition : ' . $th->getMessage());
            return False;
        }
    }

    static function createMovement(int $positionId, $product = '', $productType = '', $lote = '', $products = []) {
        try {
            $movimiento = UbicacionesMovimientos::create([
                'ubicacion_id'  => $positionId,
                'ingreso'       => date('Y-m-d H:i:s'),
                'egreso'        => null,
                'contenido'     => $productType
            ]);

            // //Guardo el conenido de la posicion
            if ($productType == 'KANBAN') {
                UbicacionContenido::create([
                    'movimiento_id' => $movimiento->id,
                    'detalle'       => $productType,
                    'contenido'     => $product,
                    'lote'          => $lote
                ]);
            } else {
                foreach ($products as $p) {
                    UbicacionContenido::create([
                        'movimiento_id' => $movimiento->id,
                        'detalle'       => $productType,
                        'contenido'     => $p['producto'],
                        'lote'          => $p['lote']
                    ]);
                }
            }

            return True;
        } catch (\Throwable $th) {
            //throw $th;
            return False;
        }
    }

    private static function normalizeRackPositionName($name): string {
        return strtoupper(trim(str_replace("'", "-", strval($name ?? ''))));
    }

    private static function isLegacyRackPositionName($name): bool {
        $normalized = self::normalizeRackPositionName($name);
        return preg_match('/^([A-H])-([A-X])-([0-9])$/', $normalized) === 1;
    }

    //OBTENER POSICION LIBRE PARA UNA UNIDAD ESPECIFICA, EN UN DEPOSITO ESPECIFICO
    static function getFreePosition2(int $warehouseId, int $udId, int $quantity = 1, bool $preferLegacyName = false) {
        $candidateAnyId = null;
        $candidateLegacyId = null;

        // $ubicaciones = StockKanbans::selectRaw('sum(cantidad) as cantidad, ubicaciones.nombre, VT_STOCK_KANBANS.deposito, max(ubicaciones.orden) as orden')
        $ubicaciones = StockKanbans::selectRaw('sum(cantidad) as cantidad, ubicaciones.id as ubicacion_id, ubicaciones.nombre, ubicaciones.orden, VT_STOCK_KANBANS.deposito')
            ->rightJoin('ubicaciones', 'ubicaciones.id', '=', 'ubicacion_id')
            ->when(!empty($warehouseId), function ($q) use ($warehouseId) {
                $q->where('ubicaciones.deposito_id', $warehouseId);
            })
            ->where('ubicaciones.habilitada', true)
            ->groupBy('VT_STOCK_KANBANS.deposito', 'ubicaciones.id', 'ubicaciones.nombre', 'ubicaciones.orden')
            ->orderBy('ubicaciones.orden', 'DESC') // Usa orden fisico, no nombre textual.
            ->groupBy('VT_STOCK_KANBANS.deposito', 'ubicaciones.nombre')
            // ->orderBy('orden', 'DESC') //PARA QUE COMIENZE DESDE LA F
            ->get();

        foreach ($ubicaciones as $ubicacion) {
            if (!is_null($ubicacion->deposito)) {
                continue;
            }

            if (is_null($candidateAnyId)) {
                $candidateAnyId = intval($ubicacion->ubicacion_id);
            }

            if ($preferLegacyName && is_null($candidateLegacyId) && self::isLegacyRackPositionName($ubicacion->nombre)) {
                $candidateLegacyId = intval($ubicacion->ubicacion_id);
            }
        }

        $selectedId = $preferLegacyName ? ($candidateLegacyId ?? $candidateAnyId) : $candidateAnyId;
        if (is_null($selectedId)) {
            return null;
        }

        return StockKanbans::selectRaw('*')
            ->rightJoin('ubicaciones', 'ubicaciones.id', '=', 'ubicacion_id')
            ->when(!empty($warehouseId), function ($q) use ($warehouseId) {
                $q->where('ubicaciones.deposito_id', $warehouseId);
            })
            ->where('ubicaciones.id', $selectedId)
            ->where('ubicaciones.habilitada', true)
            ->orderBy('ubicaciones.orden', 'DESC')
            ->first();
    }
    static function existeKanbanEnStock($kanbanCode) {
        $posiciones = MovimientosContenido::selectRaw('sum(isnull(cantidad,0)) as cantidad')->where('ref', $kanbanCode)->first();

        // Log::alert($posiciones);

        if (is_null($posiciones)) {
            return false;
        }

        if (is_null($posiciones->cantidad)) {
            return false;
        }

        return ($posiciones->cantidad > 0);
    }

    static function moveKanbanToAnotherWarehouse($kanban, $warehouseId) {

        DB::beginTransaction();

        //PRIMERO LO SACO DE DONDE ESTA, PARA ESO LO BUSCO
        $contenidos = MovimientosContenido::selectRaw('sum(cantidad) as cantidad, ubicacion_id')
            ->where('ref', $kanban)
            ->groupBy('ubicacion_id')
            ->get();

        $posicion = null;
        foreach ($contenidos as $contenido) {
            if ($contenido->cantidad > 0) {
                $posicion = $contenido->ubicacion_id;
                break;
            }
        }

        if (is_null($posicion)) {
            DB::rollBack();
            return false;
        }

        $movimiento = Movimientos::create([
            'unidad_id'     => WmsUnidades::KANBAN,
            'ubicacion_id'  => null,
            'finalizado'    => true
        ]);

        if (!$movimiento) {
            DB::rollBack();
            return false;
        }

        MovimientosContenido::create([
            'movimiento_id'     => $movimiento->id,
            'ref'               => $kanban,
            'cantidad'          => -1,
            'ubicacion_id'      => $posicion,
            'unidad_id'         => WmsUnidades::KANBAN
        ]);

        //AHORA LO INSERTO EN UNA NUEVA POSICION

        $newMovimiento = Movimientos::create([
            'unidad_id'     => WmsUnidades::KANBAN,
            'ubicacion_id'  => null,
            'finalizado'    => false
        ]);

        if (!$newMovimiento) {
            DB::rollBack();
            return false;
        }

        $newPosicion = Stock::getFreePosition2($warehouseId, WmsUnidades::KANBAN, 1);

        if (!$newPosicion) {
            DB::rollBack();
            return false;
        }

        MovimientosContenido::create([
            'movimiento_id'     => $newMovimiento->id,
            'ref'               => $kanban,
            'cantidad'          => 1,
            'ubicacion_id'      => $newPosicion->id,
            'unidad_id'         => WmsUnidades::KANBAN
        ]);

        DB::commit();

        return true;
    }

    static function getStockConsolidadoModeloPorDeposito($modelo, $unidad, $deposito = null, $depositosOmitir = null, $depositosIn = [], $fields = '*') {

        // $stock = StockKanbans::select($fields)->where('modelo', $modelo)
        //     ->when(!empty($depositosIn), function ($q) use ($depositosIn) {
        //         $q->whereIn('deposito_id', $depositosIn);
        //     })
        //     ->when(!empty($deposito), function ($q) use ($deposito) {
        //         $q->where('deposito_id', $deposito);
        //     })
        //     ->when(!empty($depositosOmitir), function ($q) use ($depositosOmitir) {
        //         $q->whereNotIn('deposito_id', $depositosOmitir);
        //     })
        //     ->where('habilitada', true)
        //     ->count();

        $stockQuery = StockKanbans::select($fields)->where('modelo', $modelo);

        // Filtrar por depÃƒÂ³sitos incluidos si existen
        if (is_array($depositosIn) && count($depositosIn)) {
            $stockQuery->whereIn('deposito_id', $depositosIn);
        } elseif (!empty($deposito)) {
            // Si no hay un array de depÃƒÂ³sitos, filtrar por un ÃƒÂºnico depÃƒÂ³sito
            $stockQuery->where('deposito_id', $deposito);
        }

        // Excluir depÃƒÂ³sitos especÃƒÂ­ficos si existen
        if (is_array($depositosOmitir) && count($depositosOmitir)) {
            $stockQuery->whereNotIn('deposito_id', $depositosOmitir);
        }

        // Filtrar por habilitadas
        $stock = $stockQuery->where('habilitada', true)->count();

        return $stock;
    }

    static function getStockModeloPorDeposito($deposito = null, $depositosOmitir = null, $depositosIn = []) {

        //$stockQuery = VTMovimientosKanban::selectRaw('modelo,deposito_id,sum(cantidad) as cantidad');


        $stockQuery = StockKanbans::selectRaw('modelo,deposito_id,count(*) as cantidad');

        // Filtrar por depÃƒÂ³sitos incluidos si existen
        if (is_array($depositosIn) && count($depositosIn)) {
            $stockQuery->whereIn('deposito_id', $depositosIn);
        } elseif (!empty($deposito)) {
            // Si no hay un array de depÃƒÂ³sitos, filtrar por un ÃƒÂºnico depÃƒÂ³sito
            $stockQuery->where('deposito_id', $deposito);
        }

        // Excluir depÃƒÂ³sitos especÃƒÂ­ficos si existen
        if (is_array($depositosOmitir) && count($depositosOmitir)) {
            $stockQuery->whereNotIn('deposito_id', $depositosOmitir);
        }

        // $stockQuery->whereIn('modelo', $modelos);
        $stockQuery->groupBy('modelo')->groupBy('deposito_id');

        // Filtrar por habilitadas
        // Log::alert($stockQuery->toSql());
        // $stock = $stockQuery->where('habilitada', true)->get();
        $stock = $stockQuery->get();

        // Log::alert($stock);
        return $stock;
    }
}
