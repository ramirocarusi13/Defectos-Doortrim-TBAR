<?php

namespace App\Imports;

use App\Models\Dados;
use App\Models\MaterialesPiezas;
use App\Models\Modelos;
use App\Models\ModeloSublineTiempo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;


class ModeloSublineTiempoImportClass implements ToModel {
    public function model(array $row) {

        try {
            $linea = $row[0];
            // $modeloId = null;
            $modelo = Modelos::where('nombre', trim($row[1]))->first();

            // Log::alert($modelo);
            $idRegistro = $row[2];

            if (!$modelo || !$linea) {
                return;
            }

            //EL TIEMPO VIENE POR 20 SETS, LO PASO A SET
            $dias = ($row[3] / 20) * 24;
            $horas =  intval($dias);
            $minutos = intval(($dias - $horas) * 60);

            $data = [
                'sublinea'      => $linea,
                'modelo_id'     => $modelo->id,
                'id_registro'   => $idRegistro,
                'tiempo'        => str_pad($horas, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutos, 2, '0', STR_PAD_LEFT) . ':00',
            ];

            $existe = ModeloSublineTiempo::where('id_registro', $idRegistro)->first();
            // Log::alert($data);

            if ($existe) {
                ModeloSublineTiempo::where('id_registro', $idRegistro)->update($data);
            } else {
                ModeloSublineTiempo::create($data);
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
