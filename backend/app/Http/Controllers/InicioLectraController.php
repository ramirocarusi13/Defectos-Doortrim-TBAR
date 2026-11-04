<?php

namespace App\Http\Controllers;

use App\Models\InicioLectra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InicioLectraController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }

    public function store(Request $request) {
        $lectra = $request->lectra;
        $hora = $request->hora;
        $fecha = date('Y-m-d');

        try {
            InicioLectra::updateOrCreate([
                'lectra'    => $lectra,
                'fecha'     => $fecha
            ], [
                'lectra'    => $lectra,
                'hora'      => $hora,
                'fecha'     => $fecha
            ]);
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('InicioLectraController::store : ' . $th->getMessage());
            return $this->setResponse([], 'Error al setear el inicio de lectra', true);
        }

        return $this->setResponse([]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\InicioLectra  $inicioLectra
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request) {
        $lectra = $request->lectra;
        $fecha = date('Y-m-d');

        $data = InicioLectra::where('fecha', $fecha)->where('lectra', $lectra)->first();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\InicioLectra  $inicioLectra
     * @return \Illuminate\Http\Response
     */
    public function edit(InicioLectra $inicioLectra) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\InicioLectra  $inicioLectra
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, InicioLectra $inicioLectra) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\InicioLectra  $inicioLectra
     * @return \Illuminate\Http\Response
     */
    public function destroy(InicioLectra $inicioLectra) {
        //
    }
}
