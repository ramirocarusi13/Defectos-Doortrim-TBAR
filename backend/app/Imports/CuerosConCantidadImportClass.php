<?php

namespace App\Imports;

use App\Models\CodigoFalla;
use App\Models\InventarioMaterialesPiezas;
use App\Models\MaterialesPiezas;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;


class CuerosConCantidadImportClass implements ToModel {
    public function model(array $row) {

        $modelo = $row[0]; // explode("/", $row[0]);
        $pn = $row[2];
        $cantidad = intval($row[3]);

        $fechaInventario = DateTime::createFromFormat('Y-m-d H:i:s', '2025-03-29 06:00:00');
        $userInventario = 10170;
        $sectorInventario = 'desbalanceod';

        // foreach ($modelos as $modelo) {

        // Log::alert($modelo);
        $modelos = '';
        try {
            $materialExistente = MaterialesPiezas::where('codigo', $pn)->first();
            if ($materialExistente) {
                if (!is_null($materialExistente->modelo)) {
                    $modelos = $materialExistente->modelo . '/' . $modelo;
                }
            } else {
                $modelos = $modelo;
            }

            if ($modelos == '' || is_null($modelos)) {
                $modelos = $modelo;
            }


            // $material = MaterialesPiezas::updateOrCreate([
            //     'codigo'    => $pn,
            // ], [
            //     'codigo'    => $pn,
            //     'modelo'    => $modelos,
            //     'tipo'      => 'CUERO',
            //     'nombre'    => $pn . ' - ' . $row[1]
            // ]);

            $material = MaterialesPiezas::where('codigo', $pn)->first();
        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th->getMessage());
        }

        if ($material && $cantidad > 0) {

            $inventario = InventarioMaterialesPiezas::create([
                'material_id'   => $material->id,
                'user_id'       => $userInventario,
                'cantidad'      => $cantidad,
                'confirmado'    => 1,
                'sector'        => $sectorInventario,
                'created_at'    => $fechaInventario->format('Y-m-d H:i:s')
            ]);
        }
    }
}
