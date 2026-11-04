<?php

namespace App\Http\Controllers;

use App\Models\MaterialesPiezas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MaterialesPiezasController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $data = MaterialesPiezas::where('orden', '>', 0)->orderBy('nombre', 'ASC')->get()->toArray();

        return $this->setResponse($data);
    }

    public function filter(Request $request) {
        // Log::alert($request);

        $codeName = $request->query('code_name', null);
        $proveedorId = $request->query('proveedor_id', null);
        $tipo = $request->query('tipo', null);
        $sort = $request->query('sort', null);

        if (!empty($sort)) {
            $sortParts = explode(':', $sort);
            if (count($sortParts) == 2) {
                $sortField = $sortParts[0];
                $sortDirection = strtolower($sortParts[1]) === 'desc' ? 'DESC' : 'ASC';
            } else {
                $sortField = 'nombre';
                $sortDirection = 'ASC';
            }
        } else {
            $sortField = 'nombre';
            $sortDirection = 'ASC';
        }

        $data = MaterialesPiezas::with(['proveedor'])
            ->withSum(['stock'], 'cantidad')
            ->when(!empty($codeName), function ($q) use ($codeName) {
                $q->where(function ($query) use ($codeName) {
                    $query->where('codigo_proveedor', 'LIKE', '%' . $codeName . '%')
                        ->orWhere('nombre', 'LIKE', '%' . $codeName . '%')
                        ->orWhere('codigo', 'LIKE', '%' . $codeName . '%')
                        ->orWhere('codigo_interno', 'LIKE', '%' . $codeName . '%');
                });
            })
            ->when(!empty($proveedorId), function ($q) use ($proveedorId) {
                $q->where('proveedor_id', $proveedorId);
            })
            ->when(!empty($tipo), function ($q) use ($tipo) {
                $q->where('tipo', $tipo);
            })
            // ->orderBy('nombre', 'ASC')
            ->orderBy($sortField, $sortDirection)
            ->get()
            ->toArray();

        return $this->setResponse($data);
    }


    public function getMaterialesPiezasPorTipo($tipoMaterial = '') {

        if ($tipoMaterial == '@') {
            $tipoMaterial = '';
        }

        $data = MaterialesPiezas::with('proveedor')->when(!empty($tipoMaterial), function ($q) use ($tipoMaterial) {
            $q->where('tipo', $tipoMaterial);
        })
            // ->where('orden', '>', 0)
            ->orderBy('nombre', 'ASC')
            ->get()->toArray();

        // Log::alert($data);

        return $this->setResponse($data);
    }

    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\MaterialesPiezas  $materialesPiezas
     * @return \Illuminate\Http\Response
     */
    public function show(MaterialesPiezas $materialesPiezas) {
        //

        $data = MaterialesPiezas::withSum(['stock'], 'cantidad')->where('id', $materialesPiezas->id)->first();
        return $this->setResponse($data?->toArray());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\MaterialesPiezas  $materialesPiezas
     * @return \Illuminate\Http\Response
     */
    public function edit(MaterialesPiezas $materialesPiezas) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\MaterialesPiezas  $materialesPiezas
     * @return \Illuminate\Http\Response
     */

    public function ciclicos(MaterialesPiezas $material) {
        // $data = $material->ciclicos()->orderBy('fecha', 'DESC')->get()->toArray();

        $data = [
            [
                'id'        => 1,
                'fecha'     => '2025-10-01',
                'cantidad'  => 150,
                'ubicacion' => 'Planta',
                'user'      => [
                    'email' => 'Cristian Torres'
                ]
            ],
            [
                'id'        => 2,
                'fecha'     => '2025-09-01',
                'cantidad'  => 20,
                'ubicacion' => 'Deposito',
                'user'      => [
                    'email' => 'Cristian Torres'
                ]
            ]
        ];

        return $this->setResponse($data);
    }

    public function update(Request $request, MaterialesPiezas $material) {
        $data = $request->all();

        $material->fill($data);
        $material->save();

        return $this->setResponse($material->toArray());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\MaterialesPiezas  $materialesPiezas
     * @return \Illuminate\Http\Response
     */
    public function destroy(MaterialesPiezas $materialesPiezas) {
        //
    }
}
