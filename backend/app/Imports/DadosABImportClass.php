<?php

namespace App\Imports;

use App\Models\Dados;
use App\Models\MaterialesPiezas;
use App\Models\ModeloDado;
use App\Models\ModeloKanbanPadre;
use App\Models\Modelos;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;


class DadosABImportClass implements ToModel {
    public function model(array $row) {


        $modelo = Modelos::where('nombre', $row[0])->first();
        $material = MaterialesPiezas::where('codigo_interno', strval($row[1]))->first();

        if (!$modelo || !$material) {
            return;
        }

        $dado = ModeloKanbanPadre::where('modelo_id', $modelo->id)
            ->where('material_id', $material->id)->first();

        if (!$dado) {
            return;
        }

        $tipo = $row[2] == '1' ? 'A' : 'B';

        $data = [
            'dado_id'       => $dado->id,
            'modelo_id'     => $modelo->id,
            'tipo'          => $tipo,
        ];

        #Log::alert($dado);

        ModeloDado::create($data);
    }
}
