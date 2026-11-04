<?php

namespace App\Services;

use App\Models\LineaOperaciones;
use App\Models\MaterialesAprobacionCalidad;

class MaterialesRetenerService {

    public $message = null;
    public $hayError = false;

    static function getMateriales() {
        $data = MaterialesAprobacionCalidad::with('material')->get();

        if ($data) {
            return $data->toArray();
        } else {
            return [];
        }
    }

    private function existeMaterialInformado($materialId): bool {
        $existe = MaterialesAprobacionCalidad::select('id')
            ->where('material_id', $materialId)
            ->first();

        return $existe ? true : false;
    }

    public function agregarMaterialARetener($data) {

        $existe = $this->existeMaterialInformado($data->material_id);

        if ($existe) {
            $this->hayError = true;
            $this->message = "El material ya se encuentra informado a retener";
            return null;
        }

        try {
            MaterialesAprobacionCalidad::create([
                'material_id'   => $data->material_id
            ]);

            $this->hayError = false;
            $this->message = "Agregado correctamente!";
        } catch (\Throwable $th) {
            //throw $th;
            $this->hayError = true;
            $this->message = $th->getMessage();
        }
    }

    static function eliminarMaterialARetener($materialId) {
        MaterialesAprobacionCalidad::where('material_id', $materialId)->delete();
    }
}
