<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $config = Configuracion::get();

        if ($config) {
            return $this->setResponse($config->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function store(Request $request) {
        $items = $request->items;

        foreach ($items as $item) {

            $clave = $item['clave'];
            $valor = $item['valor'];

            try {
                Configuracion::UpdateOrCreate([
                    'clave' => $clave
                ], [
                    'clave' => $clave,
                    'valor' => $valor
                ]);
            } catch (\Throwable $th) {
                //throw $th;
            }
        }

        return $this->setResponse([]);
    }


    public function show(Request $request) {
        $data = Configuracion::where('clave', $request->clave)->first();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function edit(Configuracion $configuracion) {
        //
    }

    public function update(Request $request, Configuracion $configuracion) {
        //
    }

    public function destroy(Configuracion $configuracion) {
        //
    }
}
