<?php

namespace App\Imports;

use App\Models\Modelos;
use App\Models\Partes;
use App\Models\Piezas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;


class PiezasUpdateImportClass implements ToModel {
    public function model(array $row) {


        $modeloName = $row[0];
        $pieza = $row[1];
        $cantidad = $row[2];
        $archivo = $row[3];
        $partesIds = [];

        $modelo = Modelos::where('nombre', $modeloName)->first();

        if (!$modelo) {
            return;
        }

        $partes = Partes::where('modelo_id', $modelo->id)->where('activo', 1)->get();

        if (!$partes) {
            return;
        }

        foreach ($partes as $parte) {
            array_push($partesIds, $parte->id);
        }

        // $len = substr_count($pieza, "-");
        $piezas = explode("-", $pieza);

        // Log::alert("=======================================================");
        // Log::alert($pieza);
        $temp = $piezas;

        for ($i = 0; $i < count($piezas); $i++) {
            $cod = "";
            // Log::alert($piezas[$i]);
            for ($j = 0; $j < count($temp) - $i; $j++) {
                if ($cod == "") {
                    $cod = $temp[$j];
                } else {
                    $cod = $cod . "-" . $temp[$j];
                }
            }
            // Log::alert("COD : " . $cod);

            Piezas::whereIn('parte_id', $partesIds)->where('codigo', $cod)->update([
                'pto_optimo' => intval($cantidad),
                'kanban_reposicion' => $archivo
            ]);
        }


        // Piezas::whereIn('parte_id', $partesIds)->where('codigo', $pieza)->update([
        //     'pto_optimo' => intval($cantidad),
        //     'kanban_reposicion' => $archivo
        // ]);

        // Log::alert($partesIds);
    }
}
