<?php

namespace App\Http\Controllers;

use App\Models\ModeloFalla;
use App\Models\ModeloLinea;
use App\Models\Modelos;
use App\Models\ModelosArchivos;
use App\Models\Sar\SARDoorTrim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ModelosController extends Controller {

    public function getDoorTrim() {
        $modelos = SARDoorTrim::selectRaw('nombre')->get();
        return $this->setResponse($modelos);
    }

    public function index() {
        $modelos = Modelos::with(['material', 'fila', 'color', 'lineas'])->where('activo', 1)->get()->toArray();

        return $this->setResponse($modelos);
    }

    public function activos() {
        $modelos = Modelos::select('id', 'codigo', 'nombre', 'descripcion')
            ->where('activo', 1)
            ->orderBy('codigo')
            ->get()
            ->toArray();

        return $this->setResponse($modelos);
    }

    public function getModelosConPartesYPiezas() {
        $modelos = Modelos::with([
            'partes.tipo',
            'partes.lado',
            'partes.vehiculo',
            'partes.piezas',
            'partes.piezas.material_pieza'
        ])->where('activo', 1)->get()->toArray();

        return $this->setResponse($modelos);
    }


    public function show(Modelos $modelo) {

        if (!$modelo) {
            return $this->setResponse([], "El modelo seleccionado no existe", true);
        }

        $response = Modelos::with(['archivos', 'lineas'])->where('id', $modelo->id)->first();
        return $this->setResponse($response->toArray());
    }

    public function updateLineas(Request $request, Modelos $modelo) {

        //Elimino todas las lineas existentes
        ModeloLinea::where('modelo_id', $modelo->id)->delete();
        foreach ($request->lines as $line) {
            if ($line['value'] == true) {
                ModeloLinea::create([
                    'modelo_id' => $modelo->id,
                    'linea_id'  => $line['id']
                ]);
            }
        }

        return $this->setResponse([]);
    }

    public function updateDatosMinPedido(Request $request) {

        foreach ($request->items as $modelo) {
            Modelos::where('id', $modelo['id'])
                ->update([
                    // 'minimo_buffer'        => intval($modelo['minimo']),
                    'ptopedido_buffer'     => intval($modelo['ptopedido']),
                    'consumo'               => $modelo['consumo'],
                ]);
        }
        return $this->setResponse([]);
    }

    public function update(Request $request, Modelos $modelo) {

        $actualizo = $modelo->update($request->toArray());

        if ($actualizo) {
            return $this->setResponse([]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Modelos  $modelos
     * @return \Illuminate\Http\Response
     */
    public function destroy(Modelos $modelos) {
        //
    }

    public function getArchivos($modelo) {

        $data = ModelosArchivos::where('modelo_id', $modelo)->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }
}
