<?php

namespace App\Http\Controllers;

use App\Models\RrhhLegajos;
use Illuminate\Http\Request;

class RrhhLegajosController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $legajos = RrhhLegajos::with('area')->get();

        if ($legajos) {
            return $this->setResponse($legajos->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function create() {
        //
    }

    public function store(Request $request) {

        if ($request->legajo_id) {
            RrhhLegajos::where('id', $request->legajo_id)->update([
                'nombre'        => $request->nombre,
                'area_id'       => $request->area_id,
                'turno'         => $request->turno,
                'motivo_baja'   => $request->motivo_baja,
                'activo'        => $request->activo
            ]);
        } else {
            RrhhLegajos::create([
                'nombre'        => $request->nombre,
                'area_id'       => $request->area_id,
                'turno'         => $request->turno,
                'motivo_baja'   => $request->motivo_baja,
                'activo'        => $request->activo
            ]);
        }



        return $this->setResponse([]);
    }

    public function show($legajoId) {
        $legajo = RrhhLegajos::with('area')->where('id', $legajoId)->first();

        if ($legajo) {
            return $this->setResponse($legajo->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\RrhhLegajos  $rrhhLegajos
     * @return \Illuminate\Http\Response
     */
    public function edit(RrhhLegajos $rrhhLegajos) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\RrhhLegajos  $rrhhLegajos
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, RrhhLegajos $rrhhLegajos) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\RrhhLegajos  $rrhhLegajos
     * @return \Illuminate\Http\Response
     */
    public function destroy(RrhhLegajos $rrhhLegajos) {
        //
    }
}
