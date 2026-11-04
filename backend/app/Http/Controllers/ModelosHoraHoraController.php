<?php

namespace App\Http\Controllers;

use App\Models\Modelos;
use App\Models\ModelosHoraHora;
use App\Models\PlanCostura;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ModelosHoraHoraController extends Controller {

    public function getModelosHoraHora(Request $request) {
        $turno = $request->turno;
        $linea = $request->linea;
        $fecha = $request->fecha;

        $fechaPlan = DateTime::createFromFormat('Y-m-d', $fecha);

        $plan = PlanCostura::where('fecha', $fechaPlan->format('d/m/Y'))->where('linea', 'M' . $linea)->get();

        if ($plan) {
            $plan = $plan->toArray();
        } else {
            $plan = [];
        }

        $data = ModelosHoraHora::where('fecha', $fecha)->where('linea', $linea)->get();

        if (count($data) == 0) {
            $modelos = Modelos::whereHas('lineas', function ($q) use ($linea) {
                $q->where('linea_id', intval($linea));
            })
                ->get();

            // Log::alert($modelos);

            foreach ($modelos as $modelo) {

                $found_key = array_search($modelo->nombre, array_column($plan, 'modelo'));

                ModelosHoraHora::create(
                    [
                        'linea'     => $linea,
                        'turno'     => $turno,
                        'fecha'     => $fecha,
                        'modelo'    => $modelo->nombre,
                        'plan'      => $found_key > 0 ? intval($plan[$found_key][$turno == 'M' ? 'tm' : 'tt']) : 0
                    ]
                );
            }

            $data = ModelosHoraHora::where('fecha', $fecha)->where('turno', $turno)->where('linea', $linea)->get();
        }

        return $this->setResponse($data ? $data->toArray() : []);
    }

    public function store(Request $request) {

        $modelos = $request->modelos;
        $turno = $request->data['turno'];
        $linea = $request->data['linea'];
        $fecha = $request->data['fecha'];
        $nombreTurno = $request->data['nombreTurno'];
        foreach ($modelos as $mod) {
            // Log::alert($mod);

            $modelo = $mod['modelo'];
            $plan = $mod['plan'];
            $real = $mod['real'];

            ModelosHoraHora::where('linea', $linea)
                ->where('turno', $turno)
                ->where('fecha', $fecha)
                ->where('modelo', $modelo)
                ->update([
                    'nombre_turno'  => $nombreTurno,
                    'plan'          => intval($plan),
                    'real'          => intval($real)
                ]);
        }

        $data = ModelosHoraHora::where('fecha', $fecha)->where('turno', $turno)->where('linea', $linea)->get();

        return $this->setResponse($data->toArray());
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ModelosHoraHora  $modelosHoraHora
     * @return \Illuminate\Http\Response
     */
    public function show(ModelosHoraHora $modelosHoraHora) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ModelosHoraHora  $modelosHoraHora
     * @return \Illuminate\Http\Response
     */
    public function edit(ModelosHoraHora $modelosHoraHora) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ModelosHoraHora  $modelosHoraHora
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ModelosHoraHora $modelosHoraHora) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ModelosHoraHora  $modelosHoraHora
     * @return \Illuminate\Http\Response
     */
    public function destroy(ModelosHoraHora $modelosHoraHora) {
        //
    }
}
