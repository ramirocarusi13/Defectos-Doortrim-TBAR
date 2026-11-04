<?php

namespace App\Imports;

use App\Models\Dados;
use App\Models\MaterialesPiezas;
use App\Models\Modelos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;


class DadosImportClass implements ToModel {
    public function model(array $row) {

        $modeloId = null;
        $materialId = null;

        $modelo = Modelos::where('nombre', $row[1])->first();
        $material = MaterialesPiezas::where('codigo', $row[2])->first();

        $modeloId = $modelo ? $modelo->id : null;
        $materialId = $material ? $material->id : null;

        $data = [
            'codigo'        => $row[0],
            'modelo_id'     => $modeloId,
            'material_id'   => $materialId,
            'tiempo_corte'  => $row[3],
            't_pos'         => $row[4],
            'habilitado'    => true,
        ];

        Dados::create($data);
    }
}
