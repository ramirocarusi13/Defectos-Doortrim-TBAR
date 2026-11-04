<?php

namespace App\Imports;

use App\Models\Modelos;
use App\Models\Partes;
use App\Models\Piezas;
use App\Models\Vehiculos;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;

class PiezasImportClass implements ToModel {


    public function model(array $row) {

        $parte = null;
        //verifico si existe la parte
        if ($row[1] <> '') {
            $mod = Modelos::where('nombre', $row[1])->first();
            $veh = Vehiculos::where('codigo', $row[0])->first();

            //SI NO EXISTE MODELO O VEHICULO NO CREO LA PARTE
            if (!$mod || !$veh) {
                return null;
            }

            $parte = Partes::where('codigo', $row[5])
                ->where('modelo_id', $mod->id)
                ->where('vehiculo_id', $veh->id)
                ->where('activo', 1)
                ->first();
        }

        if (!$parte) {
            if ($row[1] == '') {
                $parte = null;
            } else {
                $modelo = Modelos::where('nombre', $row[1])->first();
                $vehiculo = Vehiculos::where('codigo', $row[0])->first();

                //si no existe la creo
                if ($modelo) {
                    $parte = Partes::create([
                        'modelo_id'     => $modelo->id,
                        'vehiculo_id'   => $vehiculo->id,
                        'codigo'        => $row[5],
                        'tipo_id'       => intval($row[4]),
                        'lado_id'       => intval($row[2])
                    ]);
                }
            }
        }

        if ($parte) {
            // Log::alert($row);
            $existe = Piezas::where('codigo', strval($row[6]))
                ->where('dado', $row[7])
                ->where('parte_id', $parte->id)
                ->first();

            // Log::alert($existe);

            if (!$existe) {
                $min = random_int(0, 9);
                Piezas::create([
                    'codigo'    => $row[6],
                    'parte_id'  => $parte->id,
                    'dado'      => $row[7],
                    'minimo'    => $min,
                    'maximo'    => random_int(10, 20)
                ]);
            } else {
                Piezas::where('id', $existe->id)
                    ->update([
                        'p_left'    => $row[8],
                        'p_top'     => $row[9],
                        'p_width'   => $row[10],
                        'p_height'  => $row[11],
                    ]);
            }
        }
    }
}
