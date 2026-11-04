<?php

namespace App\Services;

use App\Models\LineaOperaciones;

class LineaOperacionesService {

    public function obtenerOperacionesPorLinea(int $linea): array|null {

        $operaciones = LineaOperaciones::where('linea', $linea)->get();

        if ($operaciones) {
            return $operaciones->toArray();
        } else {
            return [];
        }
    }
}
