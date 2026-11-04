<?php

namespace App\Imports;

use App\Models\CodigoFalla;
use App\Models\ModeloFalla;
use App\Models\Modelos;
use App\Models\TipoPartes;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToModel;

class CodigoFallaModeloImportClass implements ToModel {
    public function model(array $row) {

        $modelo = Modelos::where('nombre', $row[1])->first();

        if (!$modelo) {
            return;
        }

        $falla = CodigoFalla::where('codigo', $row[0])->first();

        if (!$falla) {
            return;
        }

        $tipo = TipoPartes::where('tipo', $row[2])->first();

        if (!$tipo) {
            return;
        }

        $existe = ModeloFalla::where('modelo_id', $modelo->id)
            ->where('falla_id', $falla->id)
            ->where('tipo_id', $tipo->id)
            ->first();

        if (!$existe) {
            ModeloFalla::create([
                'modelo_id'     => $modelo->id,
                'falla_id'      => $falla->id,
                'tipo_id'       => $tipo->id,
            ]);
        }
    }
}
