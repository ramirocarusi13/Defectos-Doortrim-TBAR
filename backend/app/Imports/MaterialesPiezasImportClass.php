<?php

namespace App\Imports;

use App\Models\MaterialesPiezas;
use App\Models\Partes;
use App\Models\Piezas;
use App\Models\PiezasMateriales;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;

class MaterialesPiezasImportClass implements ToModel {
    public function model(array $row) {
        //Esto es porque lee algunas filas duplicadas sin el ultimo registro
        if (count($row) < 10) {
            return;
        }

        $codigoPieza = strval($row[4]);
        $codigoMaterial = $row[0];

        // if ($codigoPieza == '' && $row[5] == '' && $row[3] == '') {
        //     //Este caso es un insumo que no es tela, o no tiene codigo al menos
        //     $pieza = Piezas::where('codigo', $row[1])->first();

        //     //Creo la pieza especial
        //     if (!$pieza) {
        //         $parte = Partes::with(['vehiculo'])->where('codigo', $row[8])
        //             ->whereHas('vehiculo', function ($q) use ($row) {
        //                 $q->where('codigo', $row[6]);
        //             })
        //             ->first();

        //         if ($parte) {
        //             $pieza = Piezas::create([
        //                 'codigo'    => $row[1],
        //                 'parte_id'  => $parte->id,
        //                 'dado'      => $row[5],
        //             ]);
        //         }
        //     }
        // } else {
        $pieza = Piezas::with(['parte', 'parte.vehiculo'])
            ->where('codigo', $codigoPieza)
            ->where('dado', $row[5])
            ->whereHas('parte', function ($q) use ($row) {
                $q->where('codigo', $row[8]); //Nro Funda
            })
            ->whereHas('parte.vehiculo', function ($q) use ($row) {
                $q->where('codigo', $row[6]); //Nombre vehiculo
            })
            ->first();


        $material = MaterialesPiezas::where('codigo', $codigoMaterial)->first();

        if (!$material) {
            $material = MaterialesPiezas::create([
                'codigo_interno'    => $row[3],
                'codigo'            => $codigoMaterial,
                'nombre'            => $row[1],
                'color'             => $row[2],
            ]);
        }

        if ($pieza && $material) {
            $secuencia = 1;
            $existe = PiezasMateriales::where('pieza_id', $pieza->id)
                ->where('material_pieza_id', $material->id)
                ->where('dado', $row[5])
                ->orderBy('secuencia', 'desc')
                ->first();

            if ($existe) {
                $secuencia = $existe->secuencia + 1;
            }

            PiezasMateriales::create([
                'pieza_id'          => $pieza->id,
                'material_pieza_id' => $material->id,
                'secuencia'         => $secuencia,
                'dado'              => $row[5]
            ]);
        }

        if (!$pieza && $row[3] == '') {
            //Es un material que no es tela seguramente

        }
    }
}
