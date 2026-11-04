<?php

namespace App\Http\Controllers;

use App\Models\Modelos;
use App\Models\PlanProduccion;
use DateInterval;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanProduccionController extends Controller {

    private $tackTimes = [
        1 => 191,
        2 => 169,
        3 => 337,
        4 => 239,
        5 => 350,
        6 => 368,
        7 => 0,
        8 => 0,
        9 => 0,
        10 => 522
    ];

    public function index() {
        $fecha = date('Y-m-d');

        $datos = PlanProduccion::where('fecha', $fecha)->orderBy('linea')->orderBy('orden')->get();
        if ($datos) {
            return $this->setResponse($datos->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function create() {
        //
    }


    private function sumarTiempo($linea, $hora, $cantidad) {

        $segundos = $this->tackTimes[$linea];
        $hora->modify("+" . ($segundos * $cantidad) . " seconds");

        return $hora;
    }

    public function store(Request $request) {


        $items = $request->items;
        // Log::alert($items);
        $fecha = date('Y-m-d');

        $modeloId = null;
        $horaEsperada = null;
        $linea = null;
        $lineaAnt = null;

        foreach ($items as $item) {

            // Log::alert($item);

            $modeloId = $item[0];
            $cantidad = intval($item[1]);
            $linea = $item[2];
            $orden = $item[3];

            if (is_null($modeloId) || is_null($cantidad) || $cantidad == 0) {
                continue;
            }

            $modelo = Modelos::where('id', $modeloId)->first();

            if (!$modelo) {
                continue;
            }

            if ($linea == $lineaAnt) {
                $horaEsperada = $this->sumarTiempo($linea, $horaEsperada, $cantidad);
            } else {
                if ($orden >= 8) {
                    //ESTO YA SERIA TURNO TARDE
                    $horaEsperada = DateTime::createFromFormat('Y-m-d H:i:s', $fecha . ' 15:52:00');
                } else {
                    $horaEsperada = DateTime::createFromFormat('Y-m-d H:i:s', $fecha . ' 06:13:00');
                }
                $horaEsperada = $this->sumarTiempo($linea, @$horaEsperada, $cantidad);
            }

            try {

                PlanProduccion::updateOrCreate([
                    'fecha' => $fecha,
                    'linea' => $linea,
                    'orden' => $orden,
                ], [
                    'linea'         => $linea,
                    'modelo'        => $modelo->nombre,
                    'cantidad'      => $cantidad,
                    'fecha'         => $fecha,
                    'modelo_id'     => $modeloId,
                    'orden'         => $orden,
                    'hora_esperada' => $horaEsperada->format('Y/m/d H:i:s')
                ]);
            } catch (\Throwable $th) {
                //throw $th;
                Log::error($th->getMessage());
            }

            $lineaAnt = $item[2];
        }

        return $this->setResponse([], 'Ok');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PlanProduccion  $planProduccion
     * @return \Illuminate\Http\Response
     */
    public function show(PlanProduccion $planProduccion) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PlanProduccion  $planProduccion
     * @return \Illuminate\Http\Response
     */
    public function edit(PlanProduccion $planProduccion) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PlanProduccion  $planProduccion
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PlanProduccion $planProduccion) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PlanProduccion  $planProduccion
     * @return \Illuminate\Http\Response
     */
    public function destroy(PlanProduccion $planProduccion) {
        //
    }
}
