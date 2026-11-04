<?php

namespace App\Http\Controllers;


use App\Models\ProduccionParada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProduccionParadaController extends Controller {

    public function store(Request $request) {

        $item = $request->item;
        $parada = $request->data;
        $categoria = null;
        $grupo = null;

        if ($parada['motivo']) {
            $tempMotivo = explode("-", $parada['motivo']);
            if (count($tempMotivo) > 0) {
                $grupo = trim($tempMotivo[0]);
                $categoria = trim($tempMotivo[1]);
            }
        }

        ProduccionParada::create([
            'fecha'         => $item['fecha'],
            'turno_nombre'  => $item['turno_nombre'],
            'turno'         => $item['turno'],
            'intervalo'     => $item['intervalo'],
            'area'          => $parada['area'],
            'shop'          => $parada['shop'],
            'minutos'       => $parada['minutos'],
            'categoria'     => $categoria,
            'grupo'         => $grupo,
            'contramedida'  => $parada['contramedida'],
            'causa'         => $parada['causa']
        ]);

        return $this->setResponse([]);
    }

    public function search(Request $request) {
        // Log::alert($request->filtros);
        $paradas = ProduccionParada::where($request->filtros)->get();

        return $this->setResponse($paradas ? $paradas->toArray() : []);
    }

    public function destroy(ProduccionParada $parada) {
        // Log::alert($parada);
        $parada->delete();
        return $this->setResponse([]);
    }
}
