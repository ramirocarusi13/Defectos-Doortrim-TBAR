<?php

namespace App\Http\Controllers;

use App\Http\Depositos;
use App\Http\Estados;
use App\Http\StockPiezasLib;
use App\Models\CodigoFalla;
use App\Models\Kanbans;
use App\Models\Piezas;
use App\Models\TiendaPedido;
use App\Models\TiendaPedidoItems;
use App\Services\TiendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TiendaController extends Controller {


    public function getPedidosPendientes() {
        $data = TiendaService::getPedidosPendientes();
        return $this->setResponse($data->toArray());
    }

    public function finalizarPedido(Request $request) {

        $tiendaService = new TiendaService();
        $tiendaService->finalizarPedido($request);

        return $this->setResponse([]);
    }

    public function getEtiquetas() {
        //SUKS - Completo
        //SUNS - Inset
        //SUJS - Completo
        //SSBN - Completo ** LISTO

        $data = Piezas::with(['modelo', 'parte.lado'])
            // ->whereHas('modelo', function ($q) {
            //     $q->where('modelos.activo', true)
            //         ->where('modelos.nombre', 'SUNS');
            // })
            ->where('id', 3775)
            ->orWhere('id', 1898)
            ->orWhere('id', 3804)
            // ->orWhere('id', 1419)
            // ->orWhere('id', 1398)
            // ->orWhere('id', 1420)

            // ->where('id', 3891)
            // ->orWhere('id', 3922)
            // ->orWhere('id', 3858)
            // ->where('parte_id', 223)
            // ->orWhere('parte_id', 224)
            ->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getPedidoByKanban($kanbanCode) {

        $kanban = Kanbans::where('codigo', $kanbanCode)->first();

        if (!$kanban) {
            return $this->setResponse([], 'El kanban ingresado no existe', true);
        }

        $data = TiendaPedido::with(['items.pieza.parte.lado', 'kanban.modelo', 'user'])->where('kanban_id', $kanban->id)->where('pendiente', true)->first();
        if (!$data) {
            return $this->setResponse([], 'El kanban no registra pedidos pendientes', true);
        }

        return $this->setResponse($data->toArray());
    }

    public function getInfoModelByKanban($codigoKanban) {

        //Obtengo información del modelo del kanban, con sus fundas
        $kanban = Kanbans::with(['modelo.partes.lado', 'modelo.partes.tipo', 'modelo.partes.piezas'])
            ->where('codigo', $codigoKanban)
            // ->whereHas('estado', function ($query) {
            //     $query->where('estado_id', Estados::SUB_ASSY);
            //     $query->orWhere('estado_id', Estados::COSTURA);
            // })
            ->first();
        if ($kanban) {
            return $this->setResponse($kanban->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getFallasTienda() {
        $data = CodigoFalla::withCount('enTienda')
            ->orderBy('en_tienda_count', 'desc')
            ->get();

        return $this->setResponse($data->toArray());
    }

    public function setPedido(Request $request) {

        $userId = $request->user;
        $falla = $request->falla;
        $piezas = $request->piezas;
        $linea = $request->linea;

        try {

            $tiendaService = new TiendaService();
            $tiendaService->crearPedido($userId, $falla, $linea);

            if ($tiendaService->pedidoId) {
                foreach ($piezas as $pieza) {
                    $tiendaService->agregaPieza(intval($pieza['id']),  intval($pieza['cantidad']));
                }
            }

            return $this->setResponse([]);
        } catch (\Throwable $th) {
            Log::error('TiendaController::setPedido - ' . $th->getMessage());
            return $this->setResponse([], 'Ocurrió un error al grabar el pedido', true);
        }
    }

    public function egresoStockTiendaPedido($pedidoId) {

        $userId = auth()->guard('api')->user()->id;
        //Verifico disponibilidad pedido
        $pendiente = TiendaPedido::where('pendiente', true)->where('id', $pedidoId)->first();

        if (!$pendiente) {
            return $this->setResponse([], 'El pedido no se encuentra pendiente', true);
        }

        $piezas = TiendaPedidoItems::where('pedido_id', $pedidoId)->get();

        try {
            foreach ($piezas as $pieza) {
                StockPiezasLib::generarMovimiento($pieza->pieza_id, intval($pieza->cantidad) * -1, null, Depositos::TIENDA, 'EGRESO PEDIDO ' . $pedidoId, $userId);
            }

            TiendaPedido::where('id', $pedidoId)->update(['pendiente' => false, 'user_autorizante_id' => $userId]);

            return $this->setResponse([]);
        } catch (\Throwable $th) {
            Log::error('TiendaController::egresoStockTiendaPedido - ' . $th->getMessage());
            return $this->setResponse([], 'Ocurrió un error', true);
        }
    }
}
