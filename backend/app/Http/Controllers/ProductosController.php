<?php

namespace App\Http\Controllers;

use App\Models\Productos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductosController extends Controller {
    public function index(Request $request) {

        $categoriaId = $request->query('categoria');
        $proveedorId = $request->query('proveedor');

        $productos = Productos::with(['proveedor', 'categoria'])
            ->when(!empty($categoriaId), function ($q) use ($categoriaId) {
                $q->where('categoria_id', $categoriaId);
            })
            ->when(!empty($proveedorId), function ($q) use ($proveedorId) {
                $q->where('proveedor_id', $proveedorId);
            })
            ->get();

        if ($productos) {
            return $this->setResponse($productos->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Productos  $productos
     * @return \Illuminate\Http\Response
     */
    public function show(Productos $productos) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Productos  $productos
     * @return \Illuminate\Http\Response
     */
    public function edit(Productos $productos) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Productos  $productos
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Productos $productos) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Productos  $productos
     * @return \Illuminate\Http\Response
     */
    public function destroy(Productos $productos) {
        //
    }
}
