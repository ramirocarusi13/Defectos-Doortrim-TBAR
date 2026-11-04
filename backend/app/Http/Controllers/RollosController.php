<?php

namespace App\Http\Controllers;

use App\Models\MaterialesPiezas;
use App\Models\RolesUsuarios;
use App\Models\Sar\TKanban;
use App\Models\Sar\TKanbanMaterial;
use App\Services\RollosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RollosController extends Controller {

    public function generarKanbanMaterial(Request $request) {

        $materialId = $request->material;
        $metros = $request->metros;

        return $this->setResponse(RollosService::generarKanbanMaterial($materialId, $metros));

        // $material = MaterialesPiezas::where('id', $materialId)->first();

        // $tKanban = new TKanban();
        // $response = $tKanban->crearKanbanMaterial($material->codigo, $metros);

        // if ($response) {

        //     $tKanban->cargarMM_SAP($response['kanban'], $metros, "M2");

        //     $kMaterial = TKanbanMaterial::where('ID', $response['kanban'])->first();

        //     $res = [
        //         'kanban'            => $response['kanban'],
        //         'material'          => $response['material'],
        //         'kanban_material'   => $kMaterial
        //     ];

        //     return $this->setResponse($res);
        // }
    }

    public function reimprimirKanbanMaterial(Request $request) {

        $kanbanCodigo = $request->kanban;

        $kMaterial = TKanbanMaterial::where('ID', $kanbanCodigo)->first();
        $material = MaterialesPiezas::where('codigo', $kMaterial->ID_MATERIAL)->first();

        $res = [
            'codigo'            => $kanbanCodigo,
            'material'          => $material,
            'kanban_material'   => $kMaterial
        ];

        return $this->setResponse($res);
    }


    public function search(Request $request) {
        $materialId = $request->material;
        // $fechaDesde = "";
        // $fechaHasta = "";

        $material = MaterialesPiezas::where('id', $materialId)->first();


        $data = TKanbanMaterial::selectRaw('t_kanban_material.ID,t_kanban_material.DESCRIPCION,t_registros_kanban.FECHA,t_kanban_material.CANTIDAD,t_kanban_material.ID_MATERIAL,t_kanban_material.CODIGO_INT')
            ->leftJoin('t_registros_kanban', 'N_KANBAN', 't_kanban_material.ID')
            // ->when(!empty($fechaDesde), function ($w) use ($fechaDesde, $fechaHasta) {
            //     $w->where(function ($q) use ($fechaDesde, $fechaHasta) {
            //         $q->where('t_registros_kanban.FECHA', '>=', $fechaDesde . ' 00:00:01');
            //         $q->where('t_registros_kanban.FECHA', '<=', $fechaHasta . ' 23:59:59');
            //     });
            // })
            ->when(!empty($material), function ($q) use ($material) {
                $q->where('ID_MATERIAL', $material->codigo);
            })
            ->orderBy('t_registros_kanban.FECHA', 'DESC')
            ->get()->take(30);

        return $this->setResponse($data ? $data->toArray() : []);
    }

    public function averiguarInterfazProveedor(Request $request) {
        $qr = $request->qr;

        $res = [];
        $interfaz = RollosService::obtieneInterfazProveedorPorQR($qr);

        // Log::alert($interfaz);

        if ($interfaz) {
            //Genero el kanban e imprimo
            $res = RollosService::generarKanbanMaterial($interfaz['material']->id, $interfaz['cantidad']);
            return $this->setResponse($res);
        } else {
            return $this->setResponse([], 'No se encontro información del rollo escaneado. Ingrese la información de forma manual.', true);
        }
    }
}
