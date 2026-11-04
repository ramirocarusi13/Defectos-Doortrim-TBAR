<?php

namespace App\Imports;

use App\Models\Categorias;
use App\Models\Modelos;
use App\Models\Productos;
use App\Models\Proveedores;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;

class ProductsImportClass implements ToModel {

    public function model(array $row) {

        $codigo = strval($row[2]);

        // Log::alert($row);
        if ($codigo == '') {
            return;
        }

        $proveedorNombre = $row[0];
        $categoriaNombre = $row[1];
        $unidadesXCaja = $row[3];
        $cajasXUbicacion = $row[4];


        //Verifico existencia proveedor, si no, lo creo
        $existeProveedor = Proveedores::where('nombre', $proveedorNombre)->first();
        if (!$existeProveedor) {
            //Lo Creo
            $existeProveedor = Proveedores::create([
                'nombre' => $proveedorNombre
            ]);
        }

        //Verifico existencia de categoria, si no, la creo
        $existeCategoria = Categorias::where('descripcion', $categoriaNombre)->first();
        if (!$existeCategoria) {
            //La Creo
            $existeCategoria = Categorias::create([
                'descripcion' => $categoriaNombre
            ]);
        }

        Productos::updateOrCreate(
            [
                'codigo'    => $codigo
            ],
            [
                'codigo'        => $codigo,
                'categoria_id'  => $existeCategoria ? $existeCategoria->id : null,
                'proveedor_id'  => $existeProveedor ? $existeProveedor->id : null,
                'ud_caja'       => intval($unidadesXCaja),
                'cajas_ub'      => intval($cajasXUbicacion)
            ]
        );
    }
}
