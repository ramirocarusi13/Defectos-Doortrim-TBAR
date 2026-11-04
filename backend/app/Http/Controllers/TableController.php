<?php

namespace App\Http\Controllers;

use App\Models\Colores;
use App\Models\Filas;
use App\Models\InventarioMaterialesPiezas;
use App\Models\Materiales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TableController extends Controller {


    public function getNameField($tableName) {
        if ($tableName == "colores") {
            return "color";
        } else if ($tableName == "materiales") {
            return "material";
        } else if ($tableName == "filas") {
            return "fila";
        }
    }

    public function getTable($tableName) {

        $response = [];

        if ($tableName == "colores") {
            $data = Colores::get();
        } else if ($tableName == "materiales") {
            $data = Materiales::get();
        } else if ($tableName == "filas") {
            $data = Filas::get();
        }

        foreach ($data as $item) {

            array_push($response, [
                'id'        => $item->id,
                'nombre'    => $item[$this->getNameField($tableName)],
            ]);
        }

        return $this->setResponse($response);
    }

    public function storeUpdateTable(Request $request) {
        $table = $request->table;
        $id = $request->id;
        $nombre = $request->nombre;

        if ($table == "colores") {
            $model = new Colores();
        } else if ($table == "materiales") {
            $model = new Materiales();
        } else if ($table == "filas") {
            $model = new Filas();
        }

        if ($id) {
            $res = $model::where('id', $id)->update([$this->getNameField($table) => $nombre]);
        } else {
            $res = $model::create([
                $this->getNameField($table) => $nombre
            ]);
        }

        return $this->setResponse([], $res);
    }

    public function deleteTable(Request $request) {
        $table = $request->table;
        $id = $request->id;

        // Log::alert($table);

        if ($table == "colores") {
            $model = new Colores();
        } else if ($table == "materiales") {
            $model = new Materiales();
        } else if ($table == "filas") {
            $model = new Filas();
        } else if ($table == "inventario_materiales_piezas") {
            $model = new InventarioMaterialesPiezas();
        }


        $res = $model::where('id', $id)->delete();

        return $this->setResponse([], $res);
    }
}
