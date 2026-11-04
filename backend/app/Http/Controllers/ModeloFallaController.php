<?php

namespace App\Http\Controllers;

use App\Models\ModeloFalla;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ModeloFallaController extends Controller {
    public function getImagenesFallas($modelo) {
        $data = ModeloFalla::with(['tipo', 'lado'])
            ->where('modelo_id', $modelo)->get();

        // Log::alert($data);

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function store(Request $request) {
        $rules = [
            'modelo_id' => 'required|exists:modelos,id',
            'tipo_id'   => 'nullable|exists:tipo_partes,id',
            'lado_id'   => 'nullable|exists:lado_partes,id',
            'nombre'    => 'nullable|string|max:255',
            'orientacion' => 'nullable|string|max:50',
        ];

        if ($request->hasFile('imagen')) {
            $rules['imagen'] = 'required|image|mimes:jpg,jpeg,png,webp|max:10240';
        } else {
            $rules['imagen'] = 'required|string|max:255';
        }

        $data = $request->validate($rules);

        try {
            if ($request->hasFile('imagen')) {
                $file = $request->file('imagen');
                $directory = public_path('uploads');

                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                $extension = strtolower($file->getClientOriginalExtension());
                $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $baseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $baseName);
                $baseName = trim($baseName, '_') ?: 'imagen';
                $fileName = $data['modelo_id'] . '_' . time() . '_' . $baseName . '.' . $extension;

                $file->move($directory, $fileName);
                $data['imagen'] = $fileName;
            }

            $modeloFalla = ModeloFalla::create($data);
            $modeloFalla->load(['tipo', 'lado']);

            return $this->setResponse($modeloFalla->toArray());
        } catch (\Throwable $th) {
            Log::error("ModelosController::store : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
            //throw $th;
        }
    }

    public function update(Request $request, ModeloFalla $modeloFalla) {
        if (!$modeloFalla) {
            return $this->setResponse([], "La imagen seleccionada no existe", true);
        }

        $rules = [
            'modelo_id' => 'sometimes|required|exists:modelos,id',
            'tipo_id'   => 'nullable|exists:tipo_partes,id',
            'lado_id'   => 'nullable|exists:lado_partes,id',
            'nombre'    => 'nullable|string|max:255',
            'orientacion' => 'nullable|string|max:50',
        ];

        if ($request->hasFile('imagen')) {
            $rules['imagen'] = 'required|image|mimes:jpg,jpeg,png,webp|max:10240';
        } else if ($request->has('imagen')) {
            $rules['imagen'] = 'nullable|string|max:255';
        }

        $data = $request->validate($rules);

        try {
            if ($request->hasFile('imagen')) {
                $file = $request->file('imagen');
                $directory = public_path('uploads');

                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                $extension = strtolower($file->getClientOriginalExtension());
                $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $baseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $baseName);
                $baseName = trim($baseName, '_') ?: 'imagen';
                $modelId = $data['modelo_id'] ?? $modeloFalla->modelo_id;
                $fileName = $modelId . '_' . time() . '_' . $baseName . '.' . $extension;

                $file->move($directory, $fileName);
                $data['imagen'] = $fileName;
            }

            $modeloFalla->update($data);
            $modeloFalla->load(['tipo', 'lado']);

            return $this->setResponse($modeloFalla->toArray(), "Imagen actualizada correctamente");
        } catch (\Throwable $th) {
            Log::error("ModeloFallaController::update : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
        }
    }

    public function destroy($id) {
        try {

            ModeloFalla::where('id', $id)->delete();
            return $this->setResponse([]);
        } catch (\Throwable $th) {
            Log::error("ModelosController::destroy : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
            //throw $th;
        }
    }
}
