<?php

namespace App\Imports;

use App\Models\Modelos;
use App\Models\TiendaPosiciones;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;

class TiendaLayoutImportClass implements ToModel {

    public function model(array $row) {

        $modelo = Modelos::where('nombre', $row[0])->first();

        if (!$modelo) {
            return null;
        }

        TiendaPosiciones::create([
            'modelo_id' => $modelo->id,
            'pasillo'   => $row[1],
            'posicion'  => $row[2],
            'detalle'   => $row[3],
        ]);
    }
}
