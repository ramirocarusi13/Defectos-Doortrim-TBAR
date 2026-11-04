<?php

namespace App\Http\Controllers;

use App\Services\RollosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RegistroIngresoRolloController extends Controller {
    protected $validations = [
        'codigo_escaneado'      => 'required',
        // 'codigo_escaneado'      => 'required|unique:registro_ingreso_rollos',
        'proveedorId'           => 'exists:proveedores,id',
    ];

    protected $messages = [
        'codigo_escaneado.required'     => 'El código escaneado es obligatorio.',
        'codigo_escaneado.unique'       => 'El código ya fue escaneado.',
        'proveedorId.exists'            => 'El proveedor indicado no existe',
    ];

    public function obtenerCodigoOperacion() {
        $codigo = RollosService::crearCodigoOperacion();

        return $this->setResponse(['codigo' => $codigo]);
    }

    public function store(Request $request) {

        $request->validate($this->validations, $this->messages);

        $proveedorId = $request->proveedorId;
        $qr = $request->codigo_escaneado;
        $operacion = $request->operacion;

        $rolloService = new RollosService($proveedorId, $operacion);

        $proceso = $rolloService->procesarIngreso($qr);
        // Log::alert($proceso);

        if (!$proceso) {
            return $this->setResponse([], $rolloService->mensajeError, true);
        } else {
            return $this->setResponse([
                'data'                  => $rolloService->getRegistrosOperacion(),
                'aprobacion_calidad'    => $proceso['aprobacion_calidad']
            ]);
        }
    }
}
