<?php

namespace App\Services;

use App\Models\Kanbans;
use App\Models\ModeloLinea;
use App\Models\Modelos;

class ModeloLineaService {
    static function getPrimerLineaDeModelo(Modelos $modelo): int|null {
        $linea = null;
        $lineasModelo = ModeloLinea::where('modelo_id', $modelo->id)->first();
        if ($lineasModelo) {
            $linea = $lineasModelo->linea_id;
        }

        return $linea;
    }
}
