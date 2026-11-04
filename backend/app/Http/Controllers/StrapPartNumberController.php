<?php

namespace App\Http\Controllers;

use App\Http\TipoEventoStrap;
use App\Models\StrapEvento;
use App\Models\StrapPartNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StrapPartNumberController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($modelo) {

        $data = StrapPartNumber::where('modelo', $modelo)->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function verificarEventoPendiente() {

        $data = StrapEvento::where('autoriza_user_id', null)->where('tipo', TipoEventoStrap::ERROR)->first();
        // Log::alert($data);
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
     * @param  \App\Models\StrapPartNumber  $strapPartNumber
     * @return \Illuminate\Http\Response
     */
    public function show(StrapPartNumber $strapPartNumber) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\StrapPartNumber  $strapPartNumber
     * @return \Illuminate\Http\Response
     */
    public function edit(StrapPartNumber $strapPartNumber) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\StrapPartNumber  $strapPartNumber
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, StrapPartNumber $strapPartNumber) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\StrapPartNumber  $strapPartNumber
     * @return \Illuminate\Http\Response
     */
    public function destroy(StrapPartNumber $strapPartNumber) {
        //
    }

    public function verificaPartNumber($part) {

        $data = StrapPartNumber::where('part_number', $part)->first();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([], "El mailer ingresado es inexistente", true);
        }
    }
}
