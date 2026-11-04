<?php

namespace App\Http\Controllers;

use App\Models\Recepcion;
use App\Models\RecepcionPackingList;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecepcionController extends Controller {

    public function index() {
        return $this->setResponse(Recepcion::with('proveedor')->get()->toArray());
    }

    public function filtrar(Request $request) {
        $fecha = $request?->fecha;
        $proveedor = $request?->proveedor;
        $enDarsena = $request?->enDarsena;

        $data = Recepcion::with('proveedor')
            ->when(!empty($fecha), function ($q) use ($fecha) {
                $q->where('fecha', $fecha);
            })
            ->when(!empty($proveedor), function ($q) use ($proveedor) {
                $q->where('proveedor_id', $proveedor);
            })
            ->when(!empty($enDarsena), function ($q) use ($enDarsena) {
                $q->where('en_darsena', $enDarsena);
            })
            ->get();

        return $this->setResponse($data ? $data->toArray() : []);
    }

    public function store(Request $request) {

        $id = $request?->id;

        DB::transaction(function () use ($request, $id) {
            $recepcion = $id ? Recepcion::findOrFail($id) : null;
            $proveedorId = $this->obtenerProveedorId($request, $recepcion);
            $fecha = $request->fecha ?? $recepcion?->fecha;
            $remito = $request->remito ?? $recepcion?->remito;
            $packing = $this->obtenerOperacionPacking($request, $recepcion);

            if ($recepcion) {
                $recepcion->update([
                    'proveedor_id'  => $proveedorId,
                    'fecha'         => $fecha,
                    'remito'        => $remito,
                    'packing_list'  => $packing,
                    'pendiente'     => true,
                    'en_darsena'    => false
                ]);
            } else {
                Recepcion::create([
                    'proveedor_id'  => $proveedorId,
                    'fecha'         => $fecha,
                    'remito'        => $remito,
                    'packing_list'  => $packing,
                    'pendiente'     => true,
                    'en_darsena'    => false
                ]);
            }

            if ($request->has('packing')) {
                $this->sincronizarPackingList($request->input('packing', []), $proveedorId, $packing, $remito);
            }
        });

        return $this->setResponse([]);
    }

    private function obtenerProveedorId(Request $request, ?Recepcion $recepcion = null) {
        $proveedor = $request->input('proveedor', $request->input('proveedor_id'));

        if (is_array($proveedor)) {
            return $proveedor['id'] ?? $recepcion?->proveedor_id;
        }

        if (is_object($proveedor)) {
            return $proveedor->id ?? $recepcion?->proveedor_id;
        }

        return $proveedor ?: $recepcion?->proveedor_id;
    }

    private function obtenerOperacionPacking(Request $request, ?Recepcion $recepcion = null) {
        if (!$request->has('packing_list')) {
            return $recepcion?->packing_list;
        }

        if ($request->packing_list === 'manual') {
            $operacionActual = $recepcion?->packing_list;

            return ($operacionActual && $operacionActual !== 'manual')
                ? $operacionActual
                : $this->obtenerNumeroOperacion();
        }

        return $request->packing_list;
    }

    private function obtenerNumeroOperacion() {
        $operacion = RecepcionPackingList::selectRaw('MAX(operacion) as operacion')->first();

        if ($operacion) {
            return intval($operacion->operacion) + 1;
        }

        return 1;
    }

    private function sincronizarPackingList($packing, $proveedorId, $operacion, $remito) {
        if ($operacion === null || $operacion === '') {
            return;
        }

        if (!is_array($packing)) {
            $packing = [];
        }

        $ids = [];

        foreach ($packing as $item) {
            if (!empty(data_get($item, 'id'))) {
                $ids[] = intval(data_get($item, 'id'));
            }
        }

        $itemsEliminados = RecepcionPackingList::where('operacion', $operacion)
            ->whereNull('ingreso_id');

        if (count($ids) > 0) {
            $itemsEliminados->whereNotIn('id', $ids);
        }

        $itemsEliminados->delete();

        foreach ($packing as $item) {
            $data = [
                'codigo'        => data_get($item, 'codigo'),
                'remito'        => data_get($item, 'remito', $remito),
                'lote'          => data_get($item, 'lote'),
                'cantidad'      => floatval(data_get($item, 'cantidad')),
                'proveedor_id'  => $proveedorId,
                'operacion'     => $operacion
            ];

            if (!empty(data_get($item, 'id'))) {
                RecepcionPackingList::where('id', data_get($item, 'id'))
                    ->where('operacion', $operacion)
                    ->update($data);
            } else {
                RecepcionPackingList::create($data);
            }
        }
    }

    public function edit(Recepcion $recepcion) {

        $data = Recepcion::with(['packing.material', 'proveedor'])
            ->where('id', $recepcion->id)
            ->first();

        return $this->setResponse($data ? $data->toArray() : []);
    }

    public function getRecepcionActiva() {
        $today = new DateTime();

        $data = Recepcion::with(['packing_pendiente.material', 'proveedor'])
            ->where('fecha', $today->format('Y-m-d'))
            ->where('en_darsena', true)
            ->where('pendiente', true)
            ->first();

        // Log::alert($data);

        return $this->setResponse($data ? $data->toArray() : []);
    }

    public function update(Request $request, Recepcion $recepcion) {

        DB::transaction(function () use ($request, $recepcion) {
            $data = $request->except('packing', 'proveedor');

            if ($request->has('proveedor') || $request->has('proveedor_id')) {
                $data['proveedor_id'] = $this->obtenerProveedorId($request, $recepcion);
            }

            if ($request->has('packing_list')) {
                $data['packing_list'] = $this->obtenerOperacionPacking($request, $recepcion);
            }

            $recepcion->update($data);

            if ($request->has('packing')) {
                $this->sincronizarPackingList(
                    $request->input('packing', []),
                    $data['proveedor_id'] ?? $recepcion->proveedor_id,
                    $data['packing_list'] ?? $recepcion->packing_list,
                    $data['remito'] ?? $recepcion->remito
                );
            }
        });

        return $this->setResponse([]);
    }

    public function destroy(Recepcion $recepcion) {

        RecepcionPackingList::where('operacion', $recepcion->packing_list)->delete();
        $recepcion->delete();

        return $this->setResponse([]);
    }
}
