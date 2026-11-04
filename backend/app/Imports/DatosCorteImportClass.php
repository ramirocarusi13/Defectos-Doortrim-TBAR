<?php

namespace App\Imports;

use App\Models\MaterialesPiezas;
use App\Models\ModeloDado;
use App\Models\ModeloKanbanPadre;
use App\Models\Modelos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;


class DatosCorteImportClass implements ToModel {
    public function model(array $row) {

        if ($row[0] == '' || !$row[0]) {
            return;
        }

        $modelo = Modelos::where('nombre', $row[3])->first();

        if (!$modelo) {
            return;
        }

        $material = MaterialesPiezas::where('codigo_interno', strval($row[8]))->first();

        if (!$material) {
            $material = MaterialesPiezas::create([
                'codigo_interno'    =>  strval($row[8]),
                'codigo'            => $row[5],
                'nombre'            => $row[6],
            ]);
        }

        if (!$material) {
            return;
        }

        $existente = ModeloKanbanPadre::where('modelo_id', $modelo->id)
            ->where('material_id', $material->id)
            ->where('dado', $row[0])
            ->first();

        if ($existente) {
            ModeloKanbanPadre::where('id', $existente->id)->update([
                'modelo_id'     => $modelo->id,
                'consumo'       => floatval($row[18]),
                'corte'         => intval($row[12]),
                'dado'          => $row[0],
                'material_id'   => $material->id
            ]);
        } else {
            ModeloKanbanPadre::create([
                'modelo_id'     => $modelo->id,
                'consumo'       => floatval($row[18]),
                'corte'         => intval($row[12]),
                'dado'          => $row[0],
                'material_id'   => $material->id
            ]);
        }
    }
}
