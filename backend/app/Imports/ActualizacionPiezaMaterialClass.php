<?php

namespace App\Imports;

use App\Models\MaterialesPiezas;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToModel;


class ActualizacionPiezaMaterialClass implements ToModel {
    public function model(array $row) {

        $data = [
            'ancho'     => floatval($row[8]),
            'densidad'  => floatval($row[6]),
            'orden'     => intval($row[0]),
            'nombre'    => $row[2]
        ];

        $existe = MaterialesPiezas::where('codigo', $row[1])->first();

        if ($existe) {
            MaterialesPiezas::where('codigo', $row[1])->update($data);
        } else {
            $data = [
                'codigo_interno'    => '',
                'codigo'            => $row[1],
                'nombre'            => $row[2],
                'color'             => $row[3],
                'minimo'            => 0,
                'maximo'            => 0,
                'pto_pedido'        => 0,
                'ancho'             => floatval($row[8]),
                'densidad'          => floatval($row[6]),
                'orden'             => intval($row[0]),
            ];

            MaterialesPiezas::create($data);
        }
    }
}
