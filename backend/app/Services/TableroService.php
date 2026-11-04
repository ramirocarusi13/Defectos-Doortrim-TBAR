<?php

namespace App\Services;

use App\Models\Lineas;
use App\Models\Movimientos;
use App\Models\MovimientosContenido;
use App\Models\StockKanbans;
use App\Models\Ubicaciones;
use App\Models\UserOperacionLinea;
use Illuminate\Support\Facades\Log;

class TableroService {
    public function moverOperador($operario, $operacion) {
        UserOperacionLinea::updateOrCreate(
            [
                'user_id'       => $operario['id'],
            ],
            [
                'operacion_id'  => $operacion['id'],
                'user_id'       => $operario['id'],
                // 'autorizante'   => auth()->guard('api')->user()->id
            ]
        );
    }

    public function actualizarTablero($data) {
        $lineaCodigo = $data->linea_id;

        if ($data->turno == "A") {
            Lineas::where('codigo', $lineaCodigo)->update(['turno_amarillo' => $data->habilitado]);
        } else {
            Lineas::where('codigo', $lineaCodigo)->update(['turno_blanco' => $data->habilitado]);
        }
    }
}
