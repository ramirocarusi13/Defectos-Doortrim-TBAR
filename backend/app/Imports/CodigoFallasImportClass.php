<?php

namespace App\Imports;

use App\Models\CodigoFalla;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;


class CodigoFallasImportClass implements ToModel {
    public function model(array $row) {

        $existe = CodigoFalla::where('codigo', $row[0])->first();

        // Log::alert($existe);

        if (!$existe) {
            CodigoFalla::create([
                'codigo'    => $row[0],
                'nombre'    => $row[1],
            ]);
        }
    }
}
