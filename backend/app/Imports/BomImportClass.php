<?php

namespace App\Imports;

use App\Http\LadoPartes;
use App\Http\TipoPartes;
use App\Models\MaterialesPiezas;
use App\Models\Modelos;
use App\Models\Partes;
use App\Models\Piezas;
use App\Models\PiezasMateriales;
use App\Models\Vehiculos;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;

//Antes de esto tengo que desactivar todos los modelos, partyes y piezas, e ir activando aca

class BomImportClass implements ToModel {
    public function model(array $row) {
        //Verifico vehiculo

        if ($row[0] == '') {
            return;
        }

        $vehiculo = Vehiculos::where('codigo', $row[0])->first();
        if (!$vehiculo) {
            $vehiculo = $this->crearVehiculo($row[0]);
        }

        //Verifico modelo
        $modelo = Modelos::where('nombre', $row[1])->first();
        if (!$modelo) {
            $modelo = $this->crearModelo($row[1]);
        } else {
            Modelos::where('id', $modelo->id)->update(['activo' => true]);
        }

        //Verifico parte
        $parte = $this->verificarParte($row[3], $modelo, $vehiculo, $row[2]);

        if (!$parte) {
            return "";
        }

        //Verifico material
        $material = $this->verificarMaterial($row);
        $pieza = $this->verificarPieza($row, $parte);

        $this->verificarPiezasMateriales($pieza, $material, $row);
    }

    private function verificarPiezasMateriales($pieza, $material, $row) {
        $piezaMat = PiezasMateriales::where('pieza_id', $pieza->id)
            ->where('material_pieza_id', $material->id)
            ->first();

        $data = [
            'pieza_id'              => $pieza->id,
            'material_pieza_id'     => $material->id,
            'dado'                  => $row[14],
        ];

        if (!$pieza) {
            $piezaMat = PiezasMateriales::create($data);

            Log::alert("SE CREO PIEZAS MATERIALES");
            Log::alert($data);
            Log::alert("=================================================");
        } else {
            PiezasMateriales::where('pieza_id', $pieza->id)
                ->where('material_pieza_id', $material->id)
                ->update($data);

            Log::alert("SE ACTUALIZO PIEZAS MATERIALES");
            Log::alert($data);
            Log::alert("=================================================");
        }

        return $piezaMat;
    }

    private function verificarPieza($row, $parte) {
        $pieza = Piezas::where('codigo', strval($row[12]))
            ->where('parte_id', $parte->id)
            ->first();

        $data = [
            'codigo'            => strval($row[12]),
            'parte_id'          => $parte->id,
            'dado'              => $row[14],
        ];

        if (!$pieza) {
            $pieza = Piezas::create($data);

            Log::alert("SE CREO PIEZA");
            Log::alert($data);
            Log::alert("=================================================");
        } else {
            Piezas::where('codigo', strval($row[12]))
                ->where('parte_id', $parte->id)
                ->update($data);

            Log::alert("SE ACTUALIZO PIEZAS");
            Log::alert($data);
            Log::alert("=================================================");
        }

        return $pieza;
    }

    private function verificarMaterial($row) {
        $material = MaterialesPiezas::where('codigo', $row[6])
            ->where('codigo_interno', $row[10])
            ->first();

        $data = [
            'codigo'            => $row[6],
            'nombre'            => $row[7],
            'color'             => $row[9],
            'codigo_interno'    => $row[10],
        ];

        if (!$material) {
            $material = MaterialesPiezas::create($data);

            Log::alert("SE CREO MATERIALES PIEZAS");
            Log::alert($data);
            Log::alert("=================================================");
        } else {
            MaterialesPiezas::where('codigo', $row[6])
                ->where('codigo_interno', $row[10])
                ->update($data);

            Log::alert("SE ACTUALIZO MATERIALES PIEZAS");
            Log::alert($data);
            Log::alert("=================================================");
        }

        return $material;
    }

    private function verificarParte($codigo, $modelo, $vehiculo, $ladoTipo = '') {

        $lado = 0;
        $tipo  = 0;

        $parte = Partes::where('codigo', $codigo)
            ->where('modelo_id', $modelo->id)
            ->where('vehiculo_id', $vehiculo->id)
            ->first();

        if (strpos($ladoTipo, "RH")) {
            $lado = LadoPartes::RIGHT_HAND;
        } else if (strpos($ladoTipo, "LH")) {
            $lado = LadoPartes::LEFT_HAND;
        } else if (strpos($ladoTipo, "CTR")) {
            $lado = LadoPartes::CENTRAL;
        } else {
            $lado = LadoPartes::SIN_LADO;
        }

        if (strpos($ladoTipo, "FC")) {
            $tipo = TipoPartes::CUSHION;
        } else if (strpos($ladoTipo, "FB")) {
            $tipo = TipoPartes::BACK;
        } else if (strpos($ladoTipo, "A/R")) {
            $tipo = TipoPartes::AR;
        } else if (strpos($ladoTipo, "T-UP")) {
            $tipo = TipoPartes::T_UP;
        } else if (strpos($ladoTipo, "H/R")) {
            $tipo = TipoPartes::HR;
        } else {
            $tipo = null;
        }

        $data = [
            'codigo'        => $codigo,
            'modelo_id'     => $modelo->id,
            'vehiculo_id'   => $vehiculo->id,
            'lado_id'       => $lado,
            'tipo_id'       => $tipo,
            'activo'        => true
        ];

        if (!$parte) {
            $parte = Partes::create($data);
            Log::alert("SE CREO PARTE");
            Log::alert($data);
            Log::alert("=================================================");
        } else {
            Partes::where('codigo', $codigo)
                ->where('modelo_id', $modelo->id)
                ->where('vehiculo_id', $vehiculo->id)
                ->update($data);

            Log::alert("SE ACTUALIZO PARTE");
            Log::alert($data);
            Log::alert("=================================================");
        }

        return $parte;
    }

    private function crearModelo($nombre) {

        $data = [
            'nombre'    => $nombre,
            'activo'    => true
        ];

        $modelo = Modelos::create($data);

        Log::alert("SE CREO MODELO");
        Log::alert($data);
        Log::alert("=================================================");
        return $modelo;
    }

    private function crearVehiculo($codigo) {

        $data = [
            'codigo'    => $codigo,
        ];
        $vehiculo = Vehiculos::create($data);

        Log::alert("SE CREO VEHICULO");
        Log::alert($data);
        Log::alert("=================================================");
        return $vehiculo;
    }
}
