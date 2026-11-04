<?php

namespace App\Http\Controllers;

use App\Models\Modelos;
use App\Models\Partes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PartesController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }

    public function activos() {
        $partes = Partes::with(['tipo', 'lado', 'modelo'])
            ->where('activo', 1)
            ->orderBy('codigo')
            ->get();

        Partes::attachModeloFallas($partes);

        return $this->setResponse($partes->toArray());
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
        $data = $request->validate([
            'modelo_id' => 'required|exists:modelos,id',
            'vehiculo_id' => 'nullable|exists:vehiculos,id',
            'codigo' => 'required|string|max:255',
            'tipo_id' => 'nullable|exists:tipo_partes,id',
            'lado_id' => 'nullable|exists:lado_partes,id',
            'imagen' => 'nullable|string|max:255',
        ]);

        $data['activo'] = $request->has('activo') ? boolval($request->activo) : true;

        try {
            $parte = Partes::create($data);
            $parte->load(['vehiculo', 'tipo', 'lado', 'piezas.material_pieza']);

            return $this->setResponse($parte->toArray(), "Parte creada correctamente");
        } catch (\Throwable $th) {
            Log::error("PartesController::store : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Partes  $partes
     * @return \Illuminate\Http\Response
     */
    public function show(Partes $partes) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Partes  $partes
     * @return \Illuminate\Http\Response
     */
    public function edit(Partes $partes) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Partes  $partes
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Partes $parte) {
        if (!$parte || !$parte->activo) {
            return $this->setResponse([], "La parte seleccionada no existe", true);
        }

        $data = $request->validate([
            'modelo_id' => 'sometimes|required|exists:modelos,id',
            'vehiculo_id' => 'nullable|exists:vehiculos,id',
            'codigo' => 'sometimes|required|string|max:255',
            'tipo_id' => 'nullable|exists:tipo_partes,id',
            'lado_id' => 'nullable|exists:lado_partes,id',
            'imagen' => 'nullable|string|max:255',
        ]);

        if ($request->has('activo')) {
            $data['activo'] = boolval($request->activo);
        }

        try {
            $parte->update($data);
            $parte->load(['vehiculo', 'tipo', 'lado', 'piezas.material_pieza']);

            return $this->setResponse($parte->toArray(), "Parte actualizada correctamente");
        } catch (\Throwable $th) {
            Log::error("PartesController::update : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Partes  $partes
     * @return \Illuminate\Http\Response
     */
    public function destroy(Partes $partes) {
        //
    }

    public function getPartesByModel($modeloId) {

        $modelo = Modelos::where('id', $modeloId)->where('activo', 1)->first();

        if (!$modelo) {
            return $this->setResponse([], "El modelo no existe", true);
        }
        $partes = Partes::with(['vehiculo', 'tipo', 'lado'])->where('activo', 1)->where('modelo_id', $modelo->id)->get();

        if ($partes) {
            return $this->setResponse($partes->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function updateImagen(Request $request, Partes $parte) {
        if (!$parte || !$parte->activo) {
            return $this->setResponse([], "La parte seleccionada no existe", true);
        }

        $request->validate([
            'imagen' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        try {
            $file = $request->file('imagen');
            $directory = public_path('despiece');

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $baseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $baseName);
            $baseName = trim($baseName, '_') ?: 'imagen';
            $fileName = $parte->id . '_' . time() . '_' . $baseName . '.' . $extension;

            $file->move($directory, $fileName);
            $parte->update(['imagen' => $fileName]);

            $parte->load(['vehiculo', 'tipo', 'lado', 'piezas.material_pieza']);

            return $this->setResponse($parte->toArray(), "Imagen actualizada correctamente");
        } catch (\Throwable $th) {
            Log::error("PartesController::updateImagen : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
        }
    }
}
