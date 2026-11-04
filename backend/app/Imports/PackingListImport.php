<?php

namespace App\Imports;

use App\Models\Dados;
use App\Models\InterfazProveedor;
use App\Models\Materiales;
use App\Models\MaterialesPiezas;
use App\Models\Modelos;
use App\Models\ModeloSublineTiempo;
use App\Models\RecepcionPackingList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;


class PackingListImport implements ToModel {
    protected InterfazProveedor $interfaz;
    protected $proveedorId = null;
    public $operacionId = null;
    public $remitos = [];
    public $errors = [];

    public function __construct($proveedorId) {
        $this->obtenerInterfazProveedor($proveedorId);
        $this->proveedorId = $proveedorId;

        $this->operacionId = $this->obtenerNumeroOperacion();
    }

    private function agregaRemitos($datos) {
        $encontro = false;

        foreach ($this->remitos as $remito) {
            if ($remito == $datos['remito']) {
                $encontro = true;
                break;
            }
        }

        if (!$encontro) {
            array_push($this->remitos, $datos['remito']);
        }
    }

    private function obtenerNumeroOperacion() {
        $operacion = RecepcionPackingList::selectRaw('MAX(operacion) as operacion')->first();

        if ($operacion) {
            return intval($operacion->operacion) + 1;
        } else {
            return 1;
        }
    }

    private function obtenerInterfazProveedor(int $proveedorId) {
        $this->interfaz = InterfazProveedor::select('interfaz', 'delimitador', 'interfaz_p', 'delimitador_p')
            ->where('proveedor_id', $proveedorId)
            ->first();
    }

    private function obtenerLoteMaterial($codigo) {
        $material = MaterialesPiezas::where('codigo_proveedor', $codigo)->where('proveedor_id', $this->proveedorId)->first();

        if ($material) {
            return $material->lote || 1;
        }

        return 1;
    }

    private function verificaExistenciaMaterial($codigo) {
        // $altaAutomatica = true;
        //Busqueda original por código de proveedor
        $material = MaterialesPiezas::where('codigo_proveedor', $codigo)->where('proveedor_id', $this->proveedorId)->first();

        if (!$material) {
            //Verifico por código genérico, asociado al proveedor
            $material = MaterialesPiezas::where('codigo', $codigo)->where('proveedor_id', $this->proveedorId)->first();
        }

        if (!$material) {
            //Verifico por codigo genérico, con proveedor nulo
            $material = MaterialesPiezas::where('codigo', $codigo)->whereNull('proveedor_id')->first();
        }

        // if (!$material && $altaAutomatica) {
        //     //DOY DE ALTA EL MATERIAL

        //     MaterialesPiezas::create([
        //         'codigo'                => $codigo,
        //         'codigo_proveedor'      => $codigo,
        //         'proveedor_id'          => $this->proveedorId,
        //         'lote'                  => 1
        //     ]);
        // }

        if (!$material) {
            array_push($this->errors, "El código " . $codigo . " no existe o no está asociado al proveedor indicado.");
            return null;
        }

        return $material;
    }

    private function validaMaterialPackingList($row) {
        $codigo = null;

        if ($this->proveedorId == 16) {
            $codigo = $row[7];
        } else if ($this->proveedorId == 6) {
            $codigo = $row[4];
        } else if ($this->proveedorId == 18) {
            $codigo = $row[7];
        }

        $material = $this->verificaExistenciaMaterial($codigo);
        // $material = MaterialesPiezas::selectRaw('isnull(lote,1) as lote')->where('codigo_proveedor', $codigo)->where('proveedor_id', $this->proveedorId)->first();

        if (!$material) {
            // array_push($this->errors, "El código " . $codigo . " no existe o no está asociado al proveedor indicado.");
            return null;
        }

        return $material;
    }

    private function getInterfazPorProveedor($row) {

        $material = $this->validaMaterialPackingList($row);

        // Log::alert($material);

        if (!$material) {
            return null;
        }

        if ($this->proveedorId == 16) {
            //INPLACA
            $steps = intval($material->lote) > 1 ? (floatval($row[8]) / intval($material->lote)) : 1;

            for ($i = 0; $i < $steps; $i++) {
                $datos = [
                    'remito'    => $row[4],
                    'lote'      => $row[5],
                    'codigo'    => $row[7],
                    'cantidad'  => intval($material->lote), // > 1 ? (floatval($row[8]) / intval($material->lote)) : floatval($row[8]),
                ];

                if (floatval($datos['cantidad']) > 0) {

                    $this->agregaRemitos($datos);

                    RecepcionPackingList::create([
                        'codigo'        => $datos['codigo'],
                        'remito'        => $datos['remito'],
                        'lote'          => $datos['lote'],
                        'cantidad'      => floatval($datos['cantidad']),
                        'proveedor_id'  => $this->proveedorId,
                        'operacion'     => $this->operacionId
                    ]);
                }
            }
        } else if ($this->proveedorId == 6) {
            //SANSUY
            $datos = [
                'remito'    => $row[6],
                'lote'      => $row[8],
                'codigo'    => $row[4],
                'cantidad'  => floatval($row[9]), // > 1 ? (floatval($row[8]) / intval($material->lote)) : floatval($row[8]),
            ];

            if (floatval($datos['cantidad']) > 0) {

                $this->agregaRemitos($datos);

                RecepcionPackingList::create([
                    'codigo'        => $datos['codigo'],
                    'remito'        => $datos['remito'],
                    'lote'          => $datos['lote'],
                    'cantidad'      => floatval($datos['cantidad']),
                    'proveedor_id'  => $this->proveedorId,
                    'operacion'     => $this->operacionId
                ]);
            }
        } else if ($this->proveedorId == 18) {
            //CAMPO GRAFICO

            $steps = intval($material->lote) > 1 ? (floatval($row[8]) / intval($material->lote)) : 1;

            for ($i = 0; $i < $steps; $i++) {

                $datos = [
                    'remito'    => $row[4],
                    'lote'      => $row[5], // . $row[0] . str_pad($row[1], 2, "0", STR_PAD_LEFT) . str_pad($row[2], 2, "0", STR_PAD_LEFT),
                    'codigo'    => $row[7],
                    'cantidad'  => intval($material->lote), //floatval($row[8]),
                ];

                if (floatval($datos['cantidad']) > 0) {

                    $this->agregaRemitos($datos);

                    RecepcionPackingList::create([
                        'codigo'        => $datos['codigo'],
                        'remito'        => $datos['remito'],
                        'lote'          => $datos['lote'],
                        'cantidad'      => floatval($datos['cantidad']),
                        'proveedor_id'  => $this->proveedorId,
                        'operacion'     => $this->operacionId
                    ]);
                } else {
                    array_push($this->errors, "El código " . $datos['codigo'] . " no se puede agregar ya que no tiene lote de cantidad asignado.");
                }
            }
        }
    }

    public function model(array $row) {

        $datos = $this->getInterfazPorProveedor($row);
    }
}
