<?php

namespace App\Services;

use App\Http\LineasModelo;
use App\Models\Kanbans;
use App\Models\Partes;

class ParteService {

    static function getPartesByKanban(Kanbans $kanban): Partes {

        $partes = Partes::with(['tipo', 'lado'])
            ->whereHas('tipo', function ($q) use ($kanban) {
                if ($kanban->modelo->airbag) {
                    //SI TIENE AIRBAG LO SACO SIN BACK
                    $q->where('tipo', 'CUSHION');
                    $q->orWhere('tipo', 'T-UP');
                } else {
                    $q->where('tipo', 'CUSHION');
                    $q->orWhere('tipo', 'T-UP');
                    $q->orWhere('tipo', 'BACK');
                }
            })
            ->whereHas('vehiculo', function ($q) use ($kanban) {
                $vehiculo = LineasModelo::VEHICULOS[$kanban->modelo->nombre];
                if (strpos($vehiculo, '/') > 0) {
                    $vehiculo = substr($vehiculo, 0, strpos($vehiculo, '/'));
                }
                $q->where('codigo', 'like', '%' . $vehiculo . '%');
            })
            ->where('modelo_id', $kanban->modelo_id)
            ->where('activo', true)
            ->orderBy('vehiculo_id', 'ASC')
            ->orderBy('lado_id', 'ASC')
            ->get();

        return $partes;
    }
}
