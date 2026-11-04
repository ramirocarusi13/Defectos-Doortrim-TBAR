<?php

namespace App\Services;

use App\Http\Depositos;
use App\Http\StockPiezasLib;
use App\Models\Piezas;
use App\Models\TiendaPedido;
use App\Models\TiendaPedidoItems;
use App\Models\VTPiezas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TiendaService {

    public int $pedidoId;


    public function crearPedido(int $userId, int $fallaId, int|null $lineaId) {

        $pedido = TiendaPedido::create([
            'user_id'   => $userId,
            'falla_id'  => $fallaId,
            'linea_id'  => $lineaId
        ]);

        if ($pedido) {
            $this->pedidoId = $pedido->id;
        }
    }

    private function obtenerQrPieza(int $piezaId): string {

        $pieza = VTPiezas::where('id', $piezaId)->first();

        if (!$pieza) {
            return '';
        }

        $qr = $pieza->modelo . "]" . $pieza->codigo . "]" . $pieza->lado; //'MODELO|COD|LADO';

        return $qr;
    }

    public function agregaPieza(int $piezaId, int $cantidad = 1) {

        $qr = $this->obtenerQrPieza($piezaId);

        TiendaPedidoItems::create([
            'pedido_id' => $this->pedidoId,
            'pieza_id'  => $piezaId,
            'cantidad'  => $cantidad,
            'qr'        => $qr
        ]);
    }

    public function agregaPiezas(array $piezas, int $cantidad = 1) {

        foreach ($piezas as $pieza) {
            $this->agregaPieza($pieza['id'], $cantidad);
            // TiendaPedidoItems::create([
            //     'pedido_id' => $this->pedidoId,
            //     'pieza_id'  => $pieza['id'],
            //     'cantidad'  => $cantidad
            // ]);
        }
    }

    static function getPedidosPendientes() {
        $pedidos = TiendaPedido::with('linea', 'user', 'items.pieza.parte.modelo', 'items.pieza.material_pieza')->where('pendiente', 1)->get();

        return $pedidos;
    }

    public function finalizarPedido(Request $request) {

        $this->generaSalidaPiezasStock($request->id);

        TiendaPedido::where('id', $request->id)
            ->update([
                'pendiente'             => 0,
                'user_autorizante_id'   => auth()->guard('api')->user()->id
            ]);
    }

    private function generaSalidaPiezasStock($pedidoId) {
        $items = TiendaPedidoItems::where('pedido_id', $pedidoId)->get();
        $piezasEgresadas = [];

        foreach ($items as $item) {
            StockPiezasLib::generarMovimiento($item->pieza_id, $item->cantidad * -1, null, Depositos::TIENDA, "PEDIDO #" . $pedidoId, auth()->guard('api')->user()->id, false);
            $piezasEgresadas[] = $item->pieza_id;
        }

        StockPiezasLib::controlaPuntoOptimoPiezasEgresadasTienda($piezasEgresadas);
    }
}
