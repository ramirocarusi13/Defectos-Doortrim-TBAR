<?php

namespace App\Imports;

use App\Models\ModeloLinea;
use App\Models\Modelos;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;

class UpdateModelsImportClass implements ToModel {
    /**
     * @param Collection $collection
     */
    public function model(array $row) {


        $modelo = Modelos::where('nombre', $row[0])->first();
        if (!$modelo) {
            return;
        }

        Modelos::where('id', $modelo->id)->update([
            'revision'  => intval($row[1]),
            'volumen'   => intval($row[2]),
        ]);

        for ($i = 0; $i < 5; $i++) {
            try {
                if ($row[2 + $i] != '') {
                    $linea = $row[2 + $i];
                    $existeLinea = ModeloLinea::where('modelo_id', $modelo->id)
                        ->where('linea_id', intval($linea))->first();

                    if (!$existeLinea) {
                        ModeloLinea::create([
                            'modelo_id' => $modelo->id,
                            'linea_id'  => intval($linea)
                        ]);
                    }
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
        }
    }
}
