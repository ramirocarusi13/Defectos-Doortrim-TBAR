<?php

namespace App\Imports;

use App\Models\RrhhLegajos;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;

class LegajosImportClass implements ToModel {

    public function model(array $row) {

        try {
            $nombre = $row[1];

            if ($nombre == '') {
                return;
            }

            $areaId = intval($row[2]);
            $turno = $row[4];

            RrhhLegajos::updateOrCreate(
                [
                    'nombre'    => $nombre
                ],
                [
                    'nombre'       => $nombre,
                    'area_id'      => $areaId,
                    'turno'        => $turno,
                    'activo'       => true,
                    'motivo_baja'  => null
                ]
            );
        } catch (\Throwable $th) {
            Log::alert($th->getMessage());
            //throw $th;
        }
    }
}
