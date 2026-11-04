<?php

namespace App\Http\Controllers;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Http\Depositos;
use App\Http\Estados;
use App\Http\Kanban;
use App\Http\MotivosStock;
use App\Http\PdfKanban;
use App\Http\StockPiezasLib;
use App\Models\Depositos as ModelsDepositos;
use App\Models\EstadoKanban;
use App\Models\Kanbans;
use App\Models\KanbansReemplazo;
use App\Models\Modelos;
use App\Models\Partes;
use App\Models\Piezas;
use App\Models\StockPiezas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockPiezasController extends Controller {


    public function ingresoStockTienda(Request $request) {
        $piezas = $request->piezas;
        $esEgreso = $request?->egreso;
        $kanbanCodigo = $request?->kanban;

        Log::alert($request);
        // $kanban = null;

        if ($kanbanCodigo) {
            $kanban = Kanban::exists($kanbanCodigo);
            // $kanban = Kanbans::where('codigo', $kanbanCodigo)->first();

            if (!$kanban) {
                return $this->setResponse([], "El kanban ingresado es inexistente", true);
            }
        } else {
            $kanban = null;
        }

        // Log::alert($kanban);

        try {
            if ($kanbanCodigo) {
                StockPiezasLib::ingresoPiezasDepositoPorKanban($piezas, Depositos::TIENDA, $kanban, MotivosStock::INGRESO_TIENDA_POR_REEMPLAZO, auth()?->guard('api')?->user()->id);
                //FINALIZO EL KANBAN
                // Log::alert($kanban->id);
                KanbansReemplazo::where('kanban_id', $kanban->id)->update(['fecha_ingreso' => date('Y-m-d H:i:s')]);
            } else {
                if ($esEgreso) {
                    Log::alert("PASO EGRESO");
                    StockPiezasLib::transferenciaPiezasEntreDepositos($piezas, Depositos::SUB_ASSY, Depositos::TIENDA, null, MotivosStock::EGRESO_MANUAL, auth()?->guard('api')?->user()->id);
                } else {
                    StockPiezasLib::ingresoPiezasDeposito($piezas, Depositos::TIENDA, MotivosStock::INGRESO_TIENDA_MANUAL, auth()?->guard('api')?->user()->id);
                }
            }
        } catch (\Throwable $th) {
            Log::error("StockPiezasController::ingresoStockTienda: " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas.");
        }

        // if ($kanbanCodigo) {

        //     //Si todo esta ok, entonces finalizo el Kanban
        //     EstadoKanban::where('kanban_id', $kanban->id)->delete();

        //     EstadoKanban::create([
        //         'kanban_id' => $kanban->id,
        //         'estado_id' => Estados::FINALIZADO,
        //         'user_id'   => auth()->guard('api')->user()->id
        //     ]);
        // }

        return $this->setResponse([], "Ingresado correctamente");
    }

    public function getFileKanbanReposicion($piezaId, $modelo, $file, $capas) {

        $pdfKanban = new PdfKanban($piezaId, $modelo, $file, $capas);

        if (StockPiezasLib::existeKanbanReemplazoPendienteIngreso($pdfKanban->pieza->id)) {
            $kanbanPendiente = KanbansReemplazo::with('kanban')
                ->where('pieza_id', $pdfKanban->pieza->id)
                ->whereNull('fecha_ingreso')
                ->latest('id')
                ->first();

            if ($kanbanPendiente?->kanban) {
                $kanban = $kanbanPendiente->kanban;
                $pdfKanban->setReimpresion(true);
                $pdfKanban->setKanban($kanban);
                $pdfKanban->generate();
            }

            return;
        }

        //Verifico si ya existe un kanban abierto para esta pieza
        $kanbanExiste = KanbansReemplazo::with(['kanban', 'estado'])
            ->where('pieza_id', $pdfKanban->pieza->id)
            ->first();

        // $kanbanExiste = KanbansReemplazo::with(['kanban', 'estado' => function ($q) {
        //     $q->where('estado_id', '<>', Estados::FINALIZADO);
        // }])->where('pieza_id', $pdfKanban->pieza->id)->first();

        if ($kanbanExiste) {
            //SI existe, verifico cual es su estado
            if ($kanbanExiste->estado) {
                $estado = $kanbanExiste->estado->estado_id;
            } else {
                $estado = null;
            }

            if ($estado == Estados::FINALIZADO) {
                $kanban = Kanban::create("R", ['modelo' => $pdfKanban->modelo->id, 'pieza' => $pdfKanban->pieza->id, 'capas' => $capas, 'mes' => null]);
            } else {
                $kanban = $kanbanExiste->kanban;
                $pdfKanban->setReimpresion(true);
            }
        } else {
            $kanban = Kanban::create("R", ['modelo' => $pdfKanban->modelo->id, 'pieza' => $pdfKanban->pieza->id, 'capas' => $capas, 'mes' => null]);
        }

        if (!$kanban) {
            return;
        }

        $pdfKanban->setKanban($kanban);
        $pdfKanban->generate();
    }

    public function egresoStockTienda(Request $request) {
        $piezas = $request->piezas;
        $kanbanCodigo = $request->kanban;
        // $depositoIn = $request->depositoIn;
        $kanban = Kanbans::where('codigo', $kanbanCodigo)->first();

        //Averiguo en que deposito esta el kanban
        $stock = StockPiezas::where('kanban_id', $kanban->id)
            ->where('cantidad', '>', 0)
            ->orderBy('id', 'desc')->first();

        if (!$stock) {
            // Log::alert("StockPiezasController::egresoStockTienda - No se encuentra stock del kanban indicado: " . $kanbanCodigo);
            return $this->setResponse([], "No se encuentra stock del kanban indicado", true);
        }

        $depositoActual = $stock->deposito_id;

        try {
            //Egreso de tienda e ingreso a deposito requerido
            StockPiezasLib::transferenciaPiezasEntreDepositos($piezas, $depositoActual, Depositos::TIENDA, $kanban, MotivosStock::EGRESO_POR_KANBAN);

            //Egreso de deposito original a Scarp
            StockPiezasLib::transferenciaPiezasEntreDepositos($piezas, Depositos::SCRAP, $depositoActual, $kanban, MotivosStock::INGRESO_POR_KANBAN);
        } catch (\Throwable $th) {
            Log::error("StockPiezasController::egresoStockTienda: " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas.");
        }

        return $this->setResponse([], "Egresado correctamente");
    }

    public function stockTiendaTodasPiezas(Request $request) {
        $data = Piezas::with(['kanbanReemplazo.abierto', 'material_pieza', 'parte.modelo'])
            ->whereHas('parte.modelo', function ($q) {
                $q->where('activo', 1);
            })
            ->when(!empty($request->modelo), function ($q) use ($request) {
                $q->whereHas('parte', function ($w) use ($request) {
                    $w->where('modelo_id', $request->modelo);
                });
            })
            ->withSum('stockTienda', 'cantidad')
            ->orderBy('stock_tienda_sum_cantidad')->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getStockPiezas(Request $request) {

        $fechaDesde = $request->fecha;
        $fechaHasta = $request->fechaHasta;
        $piezaId = $request->codigo;
        $detallado = $request->detallado;
        $deposito = $request->deposito;
        $user = $request->userId;
        $kanban = $request->kanban;

        $fechaDesde = empty($fechaDesde) ? "2020-01-01" : date("Y-m-d", strtotime($fechaDesde));
        $fechaHasta = empty($fechaHasta) ? "2200-01-01" : date("Y-m-d", strtotime($fechaHasta));

        if ($detallado) {
            $response = StockPiezas::with(['pieza', 'deposito', 'pieza.material.material', 'pieza.parte.modelo'])
                ->when(!empty($piezaId), function ($q) use ($piezaId) {
                    $q->where('pieza_id', $piezaId);
                })
                ->when(!empty($deposito), function ($q) use ($deposito) {
                    $q->where('deposito_id', $deposito);
                })
                ->when(!empty($user), function ($q) use ($user) {
                    $q->where('user_id', $user);
                })
                ->when(!empty($kanban), function ($q) use ($kanban) {
                    $q->where('kanban_id', $kanban);
                })
                ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                ->orderBy('fecha')
                ->get();

            // Log::alert($response);
        } else {

            $response = StockPiezas::with(['pieza', 'deposito', 'pieza.material.material', 'pieza.parte.modelo'])
                ->when(!empty($piezaId), function ($q) use ($piezaId) {
                    $q->where('pieza_id', $piezaId);
                })
                ->when(!empty($deposito), function ($q) use ($deposito) {
                    $q->where('deposito_id', $deposito);
                })
                ->when(!empty($user), function ($q) use ($user) {
                    $q->where('user_id', $user);
                })
                ->when(!empty($kanban), function ($q) use ($kanban) {
                    $q->where('kanban_id', $kanban);
                })
                ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                ->selectRaw('sum(cantidad) as cantidad,pieza_id, deposito_id')
                ->groupBy('pieza_id')
                ->groupBy('deposito_id')
                ->havingRaw('sum(cantidad) > 0')
                ->get();
        }

        if ($response) {
            return $this->setResponse($response->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getStockAReponerTienda() {

        //Esta funcion determina la orden de producción de acuerdo a los minimos y máximos de la tienda

        $piezas = Piezas::get();
        $piezasResponse = [];

        foreach ($piezas as $pieza) {
            $stock = StockPiezas::select(DB::raw('SUM(cantidad) as stock'))->where('pieza_id', $pieza->id)
                ->where('deposito_id', Depositos::TIENDA)
                ->first()->stock;

            if (!$stock) {
                $stock = 0;
            }

            //Si el stock es menor al minimo, repongo para llegar al máximo.
            //TODO Repongo para llegar al lote optimo.

            if ($stock < intval($pieza->minimo) && intval($pieza->minimo) > 0) {
                $pieza->stock_tienda = $stock;
                $pieza->stock_reponer = $pieza->maximo - $stock; //Esto debe ser el lote optimo

                // $media = intval((intval($pieza->minimo) + intval($pieza->maximo)) / 2);
                // $pieza->stock_media = $media;

                //Si esta debajo del minimo, es critico
                if ($stock < $pieza->minimo) {
                    $pieza->stock_estado = "CRÍTICO";
                } else {
                    $pieza->stock_estado = "NORMAL";
                }

                array_push($piezasResponse, $pieza);
            }
        }


        return $this->setResponse($piezasResponse);
    }

    public function capacidadProduccionDepositos(Request $request) {
        $depositos = ModelsDepositos::get();
        $response = [];
        $res = [];

        foreach ($depositos as $deposito) {
            $data = $this->capacidadDeProduccionPorDeposito($deposito->id);
            $res['deposito'] = $deposito;
            $res['modelos'] = $data;
            // Log::alert($data);
            array_push($response, $res);
        }

        return $this->setResponse($response);
    }

    private function capacidadDeProduccionPorDeposito($depositoId = null) {
        $modelos = Modelos::with(['piezas'])->where('activo', 1)->get();

        // Log::alert($modelos);
        $response = [];
        $canCreate = 0;
        $esInicio = false;

        foreach ($modelos as $modelo) {

            $esInicio = true;
            $canCreate = 0;

            foreach ($modelo->piezas as $pieza) {
                // Log::alert($pieza);
                $stock = StockPiezas::selectRaw('sum(cantidad) as disponible')
                    ->where('pieza_id', $pieza->id)
                    ->havingRaw('sum(cantidad) > 0')
                    ->when(!empty($depositoId), function ($q) use ($depositoId) {
                        $q->where('deposito_id', $depositoId);
                    })
                    ->first();
                if ($stock) {
                    // Log::alert($stock);
                    // Log::alert($modelo->nombre);
                    // Log::alert("**************************************");

                    if ($esInicio) {
                        $canCreate = intval($stock->disponible);
                        $esInicio = false;
                    } else {
                        if (intval($stock->disponible) < $canCreate) {
                            // Log::alert("**************************************");
                            // Log::alert("PASO");
                            // Log::alert($modelo->nombre);
                            // Log::alert($stock->disponible);
                            // Log::alert($canCreate);
                            // Log::alert("**************************************");
                            $canCreate = intval($stock->disponible);
                        }
                    }
                } else {
                    // Log::alert("**************************************");
                    // Log::alert("NO HAY STOCK");
                    // Log::alert($modelo->nombre);
                    // Log::alert($depositoId);
                    // Log::alert($pieza->id);
                    // Log::alert("**************************************");

                    $canCreate = 0;
                    break; // Si un solo item no tiene stock, entones no puedo hacer la parte
                }
            }

            //Al terminar de recorrer las piezas del modelo, veo cuantos puedo crear
            if ($canCreate > 0) {
                // Log::alert("==========================================================");
                // Log::alert("MOD: " . $modelo->nombre);
                // Log::alert("DEPOSITO: " . $depositoId);
                // Log::alert($canCreate);
                array_push($response, [
                    'modelo_id' => $modelo->id,
                    'nombre'    => $modelo->nombre,
                    'capacidad' => $canCreate
                ]);
            }
        }

        return $response;
    }

    public function capacidadProduccion(Request $request) {
        $depositoId = $request->depositoId;
        $response = $this->capacidadDeProduccionPorDeposito($depositoId);
        return $this->setResponse($response);
    }
}
