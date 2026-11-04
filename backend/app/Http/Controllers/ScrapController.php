<?php

namespace App\Http\Controllers;

use App\Models\CodigoFalla;
use App\Models\MaterialesPiezas;
use App\Models\Scrap;
use App\Models\User;
use App\Services\ScrapService;
use Illuminate\Http\Request;

class ScrapController extends Controller {

    public function generarScrapFunda(Request $request) {

        // $scrapService = new ScrapService;

        // $qr = $request->qr;
        // $userId = $request->user;
        // $etiqueta = EpEtiqueta::where('qr', $qr)->first();

        // if ($etiqueta) {
        //     $funda = Partes::where('codigo', $etiqueta->codigo)->first();
        //     $generoScrap = $scrapService->generaScrapDeFundaCompleta($funda, $userId);

        //     if ($generoScrap) {
        //         return $this->setResponse([], 'Scrap generado correctamente');
        //     } else {
        //         Log::error('No se pudo generar el scrap');
        //         return $this->setResponse([], 'No se pudo generar el scrap', true);
        //     }
        // }

        // return $this->setResponse([], 'La etiqueta ingresada no existe', true);
    }

    public function filter(Request $request) {
        $scrapService = new ScrapService();

        return $this->setResponse($scrapService->filtrar($request));
    }

    public function sectores() {
        $sectores = User::query()
            ->select('area')
            ->whereNotNull('area')
            ->where('area', '<>', '')
            ->distinct()
            ->orderBy('area')
            ->pluck('area')
            ->map(function ($sector) {
                return [
                    'id'     => $sector,
                    'nombre' => $sector,
                ];
            })
            ->values()
            ->toArray();

        return $this->setResponse($sectores);
    }

    public function updateMaterialPieza(Request $request, $id) {
        $material = MaterialesPiezas::where('id', $id)->first();

        if (!$material) {
            return $this->setResponse([], 'El material indicado no existe', true);
        }

        $material->fill($request->all());
        $material->save();

        return $this->setResponse($material->toArray());
    }

    public function index() {
        //
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
        $materialId = $request->material_id ?? $request->material;
        $userId = $request->user_id ?? $request->usuario_id ?? $request->usuario ?? $request->user;
        $kg = $request->kg ?? $request->peso;
        $lineaId = $request->linea_id ?? $request->linea;
        $tipoLinea = $request->tipo_linea ?? $request->tipoLinea;
        $codigoFallaId = $request->falla_id ?? $request->defecto_id ?? $request->defecto;
        $turno = getTurnoActual();

        if (empty($materialId)) {
            return $this->setResponse([], 'Debe informar el material', true);
        }

        if (empty($userId)) {
            return $this->setResponse([], 'Debe informar el usuario', true);
        }

        if ($kg === null || floatval($kg) <= 0) {
            return $this->setResponse([], 'Debe informar un peso mayor a cero', true);
        }

        if (!MaterialesPiezas::where('id', $materialId)->exists()) {
            return $this->setResponse([], 'El material indicado no existe', true);
        }

        if (!User::where('id', $userId)->exists()) {
            return $this->setResponse([], 'El usuario indicado no existe', true);
        }

        if (!empty($tipoLinea)) {
            $tipoLinea = strtoupper(trim($tipoLinea));

            if ($tipoLinea === 'MAIN') {
                $tipoLinea = 'M';
            } else if ($tipoLinea === 'SUB') {
                $tipoLinea = 'S';
            }
        }

        if (!empty($codigoFallaId)) {
            if (!CodigoFalla::where('id', $codigoFallaId)->exists()) {
                return $this->setResponse([], 'El defecto indicado no existe', true);
            }
        }

        $scrap = Scrap::create([
            'user_id'       => $userId,
            'material_id'   => $materialId,
            'kg'            => floatval($kg),
            'precio'        => $request->precio,
            'motivo'        => $request->motivo,
            'linea_id'      => $lineaId,
            'tipo_linea'    => $tipoLinea,
            'sector'        => $request->sector,
            'turno'         => $turno,
            'falla_id'      => $codigoFallaId,
            'defecto_id'    => null,
        ]);

        return $this->setResponse($scrap->toArray(), 'Scrap cargado correctamente');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Scrap  $scrap
     * @return \Illuminate\Http\Response
     */
    public function show(Scrap $scrap) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Scrap  $scrap
     * @return \Illuminate\Http\Response
     */
    public function edit(Scrap $scrap) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Scrap  $scrap
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Scrap $scrap) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Scrap  $scrap
     * @return \Illuminate\Http\Response
     */
    public function destroy(Scrap $scrap) {
        if (!$scrap) {
            return $this->setResponse([], 'El registro indicado no existe', true);
        }

        $scrap->delete();

        return $this->setResponse([], 'Registro eliminado correctamente');
    }
}
