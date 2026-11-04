<?php

namespace App\Http\Controllers;

use App\Models\Filas;
use Illuminate\Http\Request;

class FilasController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $data = Filas::get()->toArray();

        return $this->setResponse($data);
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
     * @param  \App\Models\Filas  $filas
     * @return \Illuminate\Http\Response
     */
    public function show(Filas $filas) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Filas  $filas
     * @return \Illuminate\Http\Response
     */
    public function edit(Filas $filas) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Filas  $filas
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Filas $filas) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Filas  $filas
     * @return \Illuminate\Http\Response
     */
    public function destroy(Filas $filas) {
        //
    }
}
