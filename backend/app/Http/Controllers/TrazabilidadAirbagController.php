<?php

namespace App\Http\Controllers;

use App\Http\EventosStrap;
use App\Models\Sar\SAREtiquetaAirbag;
use App\Models\StrapEvento;
use Illuminate\Http\Request;

class TrazabilidadAirbagController extends Controller {

    public function getAirbagTrazabilidad(Request $request) {
        $codigo = $request->codigo;
        $data = SAREtiquetaAirbag::with(['modelo'])->where('id', $codigo)->first();

        if (!$data) {
            return $this->setResponse([], "La etiqueta ingresada es inexistente", true);
        }

        $eventos = StrapEvento::with(['strap', 'user', 'solicitante', 'autorizante'])
            ->where('kanban', $data->KANBAN)
            ->orWhere(function ($q) {
                $q->where('evento', EventosStrap::DESCARGA_NORMAL)
                    ->where('evento', EventosStrap::DESCARGA_PARCIAL_REPOSICION);
            })->get();

        $data->eventos = $eventos;

        return $this->setResponse($data->toArray());
    }
}
