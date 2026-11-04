<?php

namespace App\Http\Controllers;

use App\Http\WmsUnidades;
use App\Models\Depositos;
use App\Models\StockKanbans;
use App\Models\Ubicaciones;
use App\Models\UnidadesUbicaciones;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DepositosController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $data = Depositos::select('id', 'descripcion', 'posiciones_piso', 'visible')->where('visible', true)->get()->toArray();
        // $data = Depositos::with('layout')->where('visible', true)->get()->toArray();
        return $this->setResponse($data);
    }

    public function get($depositoId) {
        $data = Depositos::with('layout', 'ubicaciones.unidades.unidad')->where('id', $depositoId)->first()->toArray();
        return $this->setResponse($data);
    }

    public function setUnidadCapacidadDeposito(Request $request) {
        $capacidad = intval($request->capacidad);
        $depositoId = $request->deposito;
        $unidadId = $request->unidadId;

        try {
            $ubicaciones = Ubicaciones::where('deposito_id', $depositoId)->get();

            foreach ($ubicaciones as $ub) {
                UnidadesUbicaciones::updateOrCreate(
                    ['ubicacion_id' => $ub->id, 'unidad_id' => $unidadId],
                    [
                        'ubicacion_id'  => $ub->id,
                        'unidad_id'     => $unidadId,
                        'capacidad'     => $capacidad
                    ]
                );
            }
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("DepositosController::setUnidadCapacidadDeposito: " . $th->getMessage());
            return $this->setResponse([], 'Error al actualizar: ' . $th->getMessage());
        }
        return $this->setResponse([], 'Actualizado correctamente');
    }


    public function getUbicacionById($posicionId) {
        $data = Ubicaciones::with(['unidades.unidad', 'contenido.detalle'])
            ->where('id', $posicionId)->first();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getUbicaciones($depositoId) {
        $data = Depositos::with(['ubicacionesTodas', 'ubicaciones.unidades.unidad'])->where('id', $depositoId)->first();
        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function guardarUnidadPosicion(Request $request) {
        $posicionId = $request->posicion_id;
        $unidadId = $request->unidad_id;
        $capacidad = intval($request->capacidad);

        // Log::alert($request);

        UnidadesUbicaciones::updateOrCreate([
            'ubicacion_id'  => $posicionId,
            'unidad_id'     => $unidadId,
        ], [
            'ubicacion_id'  => $posicionId,
            'unidad_id'     => $unidadId,
            'capacidad'     => $capacidad,
        ]);

        return $this->setResponse([]);
    }

    public function vaciarDeposito($depositoId) {
        $stock = StockKanbans::where('deposito_id', $depositoId)->where('unidad_id', WmsUnidades::KANBAN)->get();
        $stockService = new StockService;

        $stockService->generaMovimiento(WmsUnidades::KANBAN, null, null);

        foreach ($stock as $s) {
            $stockService->insertaDetalle($s->ref, -1, $s->ubicacion_id, $s->lote, $s->unidad_id);
        }

        return $this->setResponse([]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Depositos  $depositos
     * @return \Illuminate\Http\Response
     */
    public function show(Depositos $depositos) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Depositos  $depositos
     * @return \Illuminate\Http\Response
     */
    public function edit(Depositos $depositos) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Depositos  $depositos
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Depositos $depositos) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Depositos  $depositos
     * @return \Illuminate\Http\Response
     */
    public function destroy(Depositos $depositos) {
        //
    }
}
