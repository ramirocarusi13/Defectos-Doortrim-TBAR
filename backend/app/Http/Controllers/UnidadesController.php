<?php

namespace App\Http\Controllers;

use App\Models\Unidades;
use Illuminate\Http\Request;

class UnidadesController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $data = Unidades::get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
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
        $id = $request->id;
        // $nombre = $request->nombre;
        // $volumen = $request->volumen;

        $data = [
            'cantidad_unica'    => $request->cantidad_unica,
            'es_kanban'         => $request->es_kanban,
            'nombre'            => $request->nombre,
            'volumen'           => $request->volumen,
        ];

        if ($id) {
            //UPDATE
            Unidades::where('id', $id)->update($data);
        } else {
            //CREATE
            Unidades::create($data);
        }

        return $this->setResponse([]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Unidades  $unidades
     * @return \Illuminate\Http\Response
     */
    public function show($unidadId) {
        $unidad = Unidades::where('id', $unidadId)->first();

        if ($unidad) {
            return $this->setResponse($unidad->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Unidades  $unidades
     * @return \Illuminate\Http\Response
     */
    public function edit(Unidades $unidades) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Unidades  $unidades
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Unidades $unidades) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Unidades  $unidades
     * @return \Illuminate\Http\Response
     */
    public function destroy($unidadId) {

        //VERIFICO SI SE PUEDE ELIMINAR

        Unidades::where('id', $unidadId)->delete();

        return $this->setResponse([]);
    }
}
