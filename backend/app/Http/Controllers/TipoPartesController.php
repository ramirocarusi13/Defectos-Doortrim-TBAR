<?php

namespace App\Http\Controllers;

use App\Models\TipoPartes;
use Illuminate\Http\Request;

class TipoPartesController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $data = TipoPartes::get();

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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\TipoPartes  $tipoPartes
     * @return \Illuminate\Http\Response
     */
    public function show(TipoPartes $tipoPartes) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\TipoPartes  $tipoPartes
     * @return \Illuminate\Http\Response
     */
    public function edit(TipoPartes $tipoPartes) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\TipoPartes  $tipoPartes
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, TipoPartes $tipoPartes) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\TipoPartes  $tipoPartes
     * @return \Illuminate\Http\Response
     */
    public function destroy(TipoPartes $tipoPartes) {
        //
    }
}
