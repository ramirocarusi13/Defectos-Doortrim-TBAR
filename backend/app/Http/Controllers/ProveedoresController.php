<?php

namespace App\Http\Controllers;

use App\Models\InterfazProveedor;
use App\Models\Proveedores;
use Illuminate\Http\Request;

class ProveedoresController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $data = Proveedores::with('interface')->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getInterfaces() {
        $data = Proveedores::with('interface')->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function getInterfazById(InterfazProveedor $interfaz) {
        // $data = $proveedor->interface;

        if ($interfaz) {
            return $this->setResponse($interfaz->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function guardarInterfaz(Request $request, InterfazProveedor $interfaz) {

        $interfaz->interfaz = $request->interfaz;
        $interfaz->delimitador = $request->delimitador;
        $interfaz->interfaz_p = $request->interfaz_p;
        $interfaz->delimitador_p = $request->delimitador_p;
        $interfaz->proveedor_id = $request->proveedor_id;
        $interfaz->save();

        return $this->setResponse($interfaz->toArray());
    }

    public function create() {
        //
    }

    public function store(Request $request) {
        $proveedor = new Proveedores();
        $proveedor->nombre = $request->nombre;
        $proveedor->interfaz_barra = $request->interfaz_barra;
        $proveedor->email = $request->email;
        $proveedor->email2 = $request->email2;
        $proveedor->email3 = $request->email3;
        $proveedor->save();

        if ($request->has('interface') && is_array($request->input('interface'))) {
            $this->guardarInterfaceProveedor($proveedor, $request->input('interface'));
        }

        $proveedor = Proveedores::with('interface')->where('id', $proveedor->id)->first();

        return $this->setResponse($proveedor->toArray());
    }

    public function show(Proveedores $proveedor) {
        $proveedor = Proveedores::with('interface')->where('id', $proveedor->id)->first();
        return $this->setResponse($proveedor->toArray());
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Proveedores  $proveedores
     * @return \Illuminate\Http\Response
     */
    public function edit(Proveedores $proveedores) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Proveedores  $proveedores
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Proveedores $proveedor) {

        $proveedor->nombre = $request->nombre;
        $proveedor->interfaz_barra = $request->interfaz_barra;
        $proveedor->email = $request->email;
        $proveedor->email2 = $request->email2;
        $proveedor->email3 = $request->email3;
        $proveedor->save();

        if ($request->has('interface') && is_array($request->input('interface'))) {
            $this->guardarInterfaceProveedor($proveedor, $request->input('interface'));
        }

        $proveedor = Proveedores::with('interface')->where('id', $proveedor->id)->first();

        return $this->setResponse($proveedor->toArray());
    }

    private function guardarInterfaceProveedor(Proveedores $proveedor, array $data) {
        $interfaz = InterfazProveedor::firstOrNew(['proveedor_id' => $proveedor->id]);

        $interfaz->proveedor_id = $proveedor->id;
        $interfaz->interfaz = $data['interfaz'] ?? null;
        $interfaz->delimitador = $data['delimitador'] ?? null;
        $interfaz->interfaz_p = $data['interfaz_p'] ?? null;
        $interfaz->delimitador_p = $data['delimitador_p'] ?? null;
        $interfaz->save();

        return $interfaz;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Proveedores  $proveedores
     * @return \Illuminate\Http\Response
     */
    public function destroy(Proveedores $proveedores) {
        //
    }
}
