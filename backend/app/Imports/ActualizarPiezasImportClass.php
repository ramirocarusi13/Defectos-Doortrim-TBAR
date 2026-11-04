<?php

namespace App\Imports;

use App\Models\AusentismoDiario;
use App\Models\MaterialesPiezas;
use App\Models\Piezas;
use App\Models\rrhhAusentismo;
use App\Models\RrhhLegajos;
use DateTime;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;

class ActualizarPiezasImportClass implements ToModel {

    public function model(array $row) {
        // Log::alert($row);

        try {

            $modelo = $row[0];
            $material = null;
            $pn = ltrim(rtrim($row[3]));
            $dado = $row[4];
            $area = floatval($row[5]);
            $codigoMaterial = strval($row[1]);

            if (!$pn || $pn == '') {
                return;
            }

            //Busco el material
            $material = !$codigoMaterial ? null : MaterialesPiezas::where('codigo', $codigoMaterial)->first();

            Piezas::where('codigo', $pn)
                ->whereHas('parte', function ($q) use ($modelo) {
                    $q->whereHas('modelo', function ($w) use ($modelo) {
                        $w->where('nombre', $modelo);
                    });
                })
                ->update([
                    'area'                  => $area,
                    'material_pieza_id'     => $material?->id,
                    'dado'                  => $dado
                ]);
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("ActualizarPiezasImportClass: " . $th->getMessage());
        }
    }
}
