<?php

namespace App\Http\Controllers;

use App\Models\OAProduccion;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OAProduccionController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
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

        foreach ($request->data as  $item) {
            // Log::alert($item['linea']);
            // Log::alert($item);

            $fecha = DateTime::createFromFormat('d-m-Y', $item['fecha']);

            OAProduccion::updateOrCreate(
                [
                    'turno' => $item['turno'],
                    'fecha' => $fecha->format('Y-m-d'),
                    'modelo' => $item['modelo'],
                    'linea' => $item['linea'],
                ],
                [
                    'turno' => $item['turno'],
                    'fecha' => $fecha->format('Y-m-d'),
                    'modelo' => $item['modelo'],
                    'linea' => $item['linea'],
                    'plan' => intval($item['plan']),
                    'real' => intval($item['real']),
                ]
            );
        }

        return $this->setResponse([]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\OAProduccion  $oAProduccion
     * @return \Illuminate\Http\Response
     */
    public function show(OAProduccion $oAProduccion) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\OAProduccion  $oAProduccion
     * @return \Illuminate\Http\Response
     */
    public function edit(OAProduccion $oAProduccion) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\OAProduccion  $oAProduccion
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, OAProduccion $oAProduccion) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\OAProduccion  $oAProduccion
     * @return \Illuminate\Http\Response
     */
    public function destroy(OAProduccion $oAProduccion) {
        //
    }
}
