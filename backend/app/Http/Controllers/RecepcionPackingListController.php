<?php

namespace App\Http\Controllers;

use App\Imports\PackingListImport;
use App\Imports\PackingListPdfImport;
use App\Models\MaterialesPiezas;
use App\Models\Proveedores;
use App\Models\Recepcion;
use App\Models\RecepcionPackingList;
use DateInterval;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class RecepcionPackingListController extends Controller {

    public function autoPackingList(Request $request) {
        $sender = $request->sender;
        $emailId = $request->emailid;

        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        set_time_limit(120);

        $proveedor = Proveedores::where('email', $sender)
            ->orWhere('email2', $sender)
            ->orWhere('email3', $sender)
            ->first();

        if (!$proveedor) {
            return $this->setResponse([], 'Proveedor no encontrado para el email: ' . $sender);
        }

        //Verifico existencia de packing.

        $file = $request->file('file');
        $packingListImport = new PackingListImport($proveedor->id);

        Excel::import($packingListImport, $file);

        $dataPacking = $this->obtenerDataPacking($packingListImport->operacionId, $proveedor->id);

        $existe = $this->existeRemitoProveedor($proveedor->id, $packingListImport->remitos);

        //Elimino y doy error de existencia
        if ($existe) {
            RecepcionPackingList::where('operacion', $packingListImport->operacionId)->delete();
            return $this->setResponse([
                'errors'    => ['Ya existen el remito ' . $existe->remito . ' (' . $existe->fecha . ') para el proveedor actual.'],
            ]);
        }

        if ($dataPacking) {
            Recepcion::create([
                'proveedor_id'  => $proveedor->id,
                'fecha'         => date('Y-m-d'),
                'remito'        => implode(', ', $packingListImport->remitos),
                'packing_list'  => true,
                'pendiente'     => true,
                'en_darsena'    => false
            ]);
        }

        return $this->setResponse(['sender' => $sender], 'Funcionalidad deshabilitada temporalmente');
    }


    public function subirPackingList(Request $request) {
        $proveedorId = $request->proveedor;
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,pdf',
        ]);

        set_time_limit(120);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $packingListImport = $extension === 'pdf'
            ? new PackingListPdfImport($proveedorId)
            : new PackingListImport($proveedorId);

        if ($extension === 'pdf') {
            $packingListImport->import($file);
        } else {
            Excel::import($packingListImport, $file);
        }

        $dataPacking = $this->obtenerDataPacking($packingListImport->operacionId, $proveedorId);

        $existe = $this->existeRemitoProveedor($proveedorId, $packingListImport->remitos);

        if ($existe) {
            //Elimino y doy error de existencia
            RecepcionPackingList::where('operacion', $packingListImport->operacionId)->delete();
            return $this->setResponse([
                'errors'    => ['Ya existen el remito ' . $existe->remito . ' (' . $existe->fecha . ') para el proveedor actual.'],
                'datos'     => [],
                'remitos'   => [],
                'operacion' => null
            ]);
        }

        return $this->setResponse([
            'errors'    => $packingListImport->errors,
            'datos'     => $dataPacking ? $dataPacking->toArray() : [],
            'remitos'   => $packingListImport->remitos,
            'operacion' => $packingListImport->operacionId
        ]);
    }

    private function existeRemitoProveedor($proveedorId, $remitos = []) {
        //Verifico existencia por proveedor y nro de remito
        $existe = false;
        $fecha = new DateTime();
        $fecha->sub(new DateInterval('P90D')); //Ultimos 90 días

        foreach ($remitos as $remito) {
            $existe = Recepcion::where('proveedor_id', $proveedorId)
                ->where('remito', $remito)->where('created_at', '>', $fecha)->first();
        }

        return $existe;
    }

    private function obtenerDataPacking($operacion, $proveedorId = null) {
        $dataPacking = RecepcionPackingList::where('operacion', $operacion)->get();
        $codigos = $dataPacking->pluck('codigo')->filter()->unique()->values();

        if ($codigos->count() === 0) {
            return $dataPacking;
        }

        $materiales = MaterialesPiezas::where(function ($q) use ($codigos) {
            $q->whereIn('codigo_proveedor', $codigos);
            $q->orWhereIn('codigo', $codigos);
        })
            ->when(!empty($proveedorId), function ($q) use ($proveedorId) {
                $q->where(function ($w) use ($proveedorId) {
                    $w->where('proveedor_id', $proveedorId);
                    $w->orWhereNull('proveedor_id');
                });
            })
            ->get();

        foreach ($dataPacking as $item) {
            $material = $materiales->first(function ($material) use ($item) {
                $mismoProveedor = is_null($material->proveedor_id) || intval($material->proveedor_id) === intval($item->proveedor_id);

                return $mismoProveedor && ($material->codigo_proveedor === $item->codigo || $material->codigo === $item->codigo);
            });

            $item->setRelation('material', $material);
        }

        return $dataPacking;
    }
}
