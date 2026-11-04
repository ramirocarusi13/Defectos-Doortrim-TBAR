<?php

namespace App\Imports;

use App\Models\MaterialesPiezas;
use App\Models\Proveedores;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToModel;


class MaterialesPiezasUpdateImportClass implements ToModel {

    //Actualizo los materiales de piezas y doy de alta proveedores inexistentes
    public function model(array $row) {

        $proveedor = null;
        $material = MaterialesPiezas::where('codigo', $row[0])->first();

        if ($row[4] != '' && $row[4] != null) {
            $proveedor = Proveedores::where('nombre', $row[4])->first();

            if (!$proveedor) {
                //Creo el proveedor
                $data = ['nombre' => $row[4]];
                $proveedor = Proveedores::create($data);
            }
        }

        if (!$material) {
            $data = [
                'codigo_interno'    => $row[2],
                'codigo'            => $row[0],
                'nombre'            => $row[1],
                'color'             => '',
                'ancho'             => floatval($row[3]),
                'um'                => $row[5],
                'proveedor_id'      => $proveedor ? $proveedor->id : null,
                'minimo'            => intval($row[9]),
                'maximo'            => intval($row[8]),
                'pto_pedido'        => intval($row[10]),
            ];

            MaterialesPiezas::create($data);
        } else {
            //Si Existe, actualizo los campos necesarios
            $data = [
                'nombre'            => $row[1],
                'ancho'             => floatval($row[3]),
                'um'                => $row[5],
                'proveedor_id'      => $proveedor ? $proveedor->id : null,
                'minimo'            => intval($row[9]),
                'maximo'            => intval($row[8]),
                'pto_pedido'        => intval($row[10]),
            ];

            MaterialesPiezas::where('id', $material->id)->update($data);
        }
    }
}
