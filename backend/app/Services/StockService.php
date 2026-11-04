<?php

namespace App\Services;

use App\Models\Movimientos;
use App\Models\MovimientosContenido;
use App\Models\StockKanbans;
use App\Models\Ubicaciones;
use Illuminate\Support\Facades\Log;

class StockService {

    protected int $unidadId;
    protected bool $finalizado = false;
    protected Movimientos $movimiento;

    public function __construct() {
        //
    }

    public function setFinalizado(bool $finalizado) {
        $this->finalizado = $finalizado;
    }

    public function obtenerPosicionDefault(int $depositoId) {
        return Ubicaciones::where('deposito_id', $depositoId)->first();
    }

    public function generaMovimiento(int|null $unidadId = null, $userId = null, $userId2 = null) {
        $this->unidadId = $unidadId;
        $this->movimiento = Movimientos::create([
            'unidad_id'     => $unidadId,
            'ubicacion_id'  => null,
            'finalizado'    => $this->finalizado,
            'user_id'       => $userId,
            'user_id2'      => $userId2
        ]);
    }

    public function insertaDetalle(string $ref, float $cantidad, int $ubicacionId, string|null $lote = null, int|null $unidadId = null, $refId = null, $actualizaSiExiste = false) {
        // Log::alert($refId);
        if ($actualizaSiExiste) {
            MovimientosContenido::updateOrCreate(
                [
                    'ref_id'        => $refId
                ],
                [
                    'unidad_id'     => is_null($unidadId) ? $this->unidadId : $unidadId,
                    'ubicacion_id'  => $ubicacionId,
                    'lote'          => $lote,
                    'cantidad'      => $cantidad,
                    'ref'           => $ref,
                    'movimiento_id' => $this->movimiento->id,
                    'ref_id'        => $refId
                ]
            );
        } else {
            MovimientosContenido::create([
                'unidad_id'     => is_null($unidadId) ? $this->unidadId : $unidadId,
                'ubicacion_id'  => $ubicacionId,
                'lote'          => $lote,
                'cantidad'      => $cantidad,
                'ref'           => $ref,
                'movimiento_id' => $this->movimiento->id,
                'ref_id'        => $refId
            ]);
        }
    }

    static function getStockModelo(string $modelo, int|null $deposito = null) {
        $stock = StockKanbans::where('modelo', $modelo)
            ->when(!empty($deposito), function ($q) use ($deposito) {
                $q->where('deposito_id', $deposito);
            })
            ->where('habilitada', true)
            ->get()->count();

        return $stock;
    }
}
