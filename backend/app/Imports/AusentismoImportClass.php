<?php

namespace App\Imports;

use App\Http\Controllers\RrhhCausaAusentismoController;
use App\Models\AusentismoDiario;
use App\Models\rrhhAreas;
use App\Models\rrhhAusentismo;
use App\Models\rrhhCausaAusentismo;
use App\Models\rrhhDetalleAusentismo;
use App\Models\RrhhLegajos;
use DateTime;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;

class AusentismoImportClass implements ToModel {

    public function model(array $row) {
        /**
         * 0 = FECHA
         * 1 = NAME
         * 2 = AREA
         * 3 = TURNO
         * 4 = CAUSA
         * 5 = FECHA INICIO
         * 6 = DETALLE
         * 7 = NADA
         * 8 = FECHA ALTA
         * 9 = SITUACION
         * 10 = OBSERVACION
         */

        try {
            $nombre = $row[1];
            if ($nombre == '' || $nombre == 'Name') {
                return;
            }

            //BUSCO EL LEGAJO
            $legajo = RrhhLegajos::where('nombre', $nombre)->first();
            if (!$legajo) {
                //INTENTO CREARLO

                $areaId = null;
                if ($row[2] != '') {
                    $area = rrhhAreas::where('area', $row[2])->first();
                    if ($area) {
                        $areaId = $area->id;
                    }
                }
                $legajo = RrhhLegajos::create([
                    'nombre'    => $nombre,
                    'turno'     => $row[3],
                    'area_id'   => $areaId,
                    'activo'    => true
                ]);
                // return;
            }
            // Log::alert($row);

            $fecha = $row[0];
            $causa = $row[4];
            $fechaInicio = $row[5];
            $detalle = $row[6];
            $fechaAlta = $row[8];
            $obs = $row[10];

            $causaId = null;
            if ($causa) {
                $causa = rrhhCausaAusentismo::where('causa', trim($causa))->first();

                if ($causa) {
                    $causaId = $causa->id;
                }
            }

            $detalleId = null;
            if ($detalle) {
                $detalle = rrhhDetalleAusentismo::select('id')->where('detalle', trim($detalle))->first();

                if ($detalle) {
                    $detalleId = $detalle->id;
                }
            }

            if ($fechaInicio != '' && $fechaInicio != '*') {
                $fechaInicio = DateTime::createFromFormat('d/m/Y', $fechaInicio);
                $fechaInicio = $fechaInicio->format('Y-m-d H:i:s');
            }

            if ($fecha != ''  && $fecha != '*') {
                $fecha = DateTime::createFromFormat('d/m/Y', substr($fecha, 0, 10));
                $fecha = $fecha->format('Y-m-d H:i:s');
            }

            if ($fechaAlta != ''  && $fechaAlta != '*') {
                $fechaAlta = DateTime::createFromFormat('d/m/Y', $fechaAlta);
                $fechaAlta = $fechaAlta->format('Y-m-d H:i:s');
            }

            $ausentismo = rrhhAusentismo::where('legajo_id', $legajo->id)
                ->where('causa_id', $causaId)
                ->where('detalle_id', $detalleId)
                ->where('inicio',  $fechaInicio)
                ->first();
            // Log::alert($ausentismo);
            if ($ausentismo) {
                //Verifico si la fecha es mayor a la fecha de alta, la actualizo

                if (!is_null($ausentismo->fecha_real)) {
                    $fechaAltaBase = DateTime::createFromFormat('Y-m-d', $ausentismo->fecha_real);
                    $fechaAltaBase = $fechaAltaBase->format('Y-m-d H:i:s');


                    if ($fechaAltaBase < $fecha) {
                        rrhhAusentismo::where('id', $ausentismo->id)->update([
                            'fecha_real'    => $fecha
                        ]);
                        // $ausentismo->fecha_real = $fecha;
                        // $ausentismo->update();
                    }
                }
            } else {
                $ausentismo = rrhhAusentismo::create(
                    [
                        'legajo_id'     => $legajo->id,
                        'causa_id'      => $causaId,
                        'detalle_id'    => $detalleId,
                        'inicio'        => $fechaInicio,
                        'observaciones' => $obs,
                        'fecha_inicio'  => $fechaInicio,
                        'fecha_real'    => $fechaAlta ? $fechaAlta : null
                    ]
                );
            }

            // $ausentismo = rrhhAusentismo::updateOrCreate(
            //     [
            //         'legajo_id'     => $legajo->id,
            //         'causa_id'      => $causaId,
            //         'detalle_id'    => $detalleId,
            //         'inicio'        => $fechaInicio
            //     ],
            //     [
            //         'legajo_id'     => $legajo->id,
            //         'causa_id'      => $causaId,
            //         'detalle_id'    => $detalleId,
            //         'inicio'        => $fechaInicio,
            //         'observaciones' => $obs,
            //         'fecha_inicio'  => $fechaInicio,
            //         'fecha_real'    => $fechaAlta ? $fechaAlta : null
            //     ]
            // );

            //CREO EL AUSENTISMO DEL DIA
            AusentismoDiario::create([
                'ausentismo_id' => $ausentismo->id,
                'fecha'         => $fecha
            ]);
        } catch (\Throwable $th) {
            Log::alert("ERROR: " . $th->getMessage());
            //throw $th;
        }
    }
}
