<?php

namespace App\Http\Controllers;

use App\Models\CodigoFalla;
use Illuminate\Http\Request;

class CodigoFallaController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $data = CodigoFalla::get();

        return $this->setResponse($data->toArray());
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
     * @param  \App\Models\CodigoFalla  $codigoFalla
     * @return \Illuminate\Http\Response
     */
    public function show(CodigoFalla $codigoFalla) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CodigoFalla  $codigoFalla
     * @return \Illuminate\Http\Response
     */
    public function edit(CodigoFalla $codigoFalla) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CodigoFalla  $codigoFalla
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CodigoFalla $codigoFalla) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CodigoFalla  $codigoFalla
     * @return \Illuminate\Http\Response
     */
    public function destroy(CodigoFalla $codigoFalla) {
        //
    }
}
