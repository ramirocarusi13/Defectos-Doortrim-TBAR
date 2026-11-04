<?php

namespace App\Http\Controllers;

use App\Http\Jerarquias;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UsersControllers extends Controller {

    private function esDepartamentoCalidad($departamento): bool {
        return strtoupper(trim((string) $departamento)) === 'CALIDAD';
    }

    private function puedeAutorizarImpresionQr($user): bool {
        return (int) $user->rol >= Jerarquias::GROUP_LEADER || $this->esDepartamentoCalidad($user->departamento);
    }

    public function index() {
        $data = User::with(['linea', 'operacion'])->get()->toArray();
        return $this->setResponse($data);
    }

    public function get($id) {
        $data = User::with(['polivalencias.operacion'])->where('id', $id)->first()->toArray();
        return $this->setResponse($data);
    }

    public function userValidoByCodAutorizacion(Request $request) {
        $user = User::select('id', 'name', 'email', 'rol', 'turno', 'area', 'departamento')->with(['rol'])->where('cod_autorizacion', $request->codigo)->first();

        if ($user) {
            $token = $user->createToken('Laravel Password Grant Client')->accessToken;

            return $this->setResponse([
                'id'            => $user->id,
                'token'         => $token,
                'name'          => $user->name,
                'email'         => $user->email,
                'rol'           => $user->rol,
                'turno'         => $user->turno,
                'area'          => $user->area,
                'departamento'  => $user->departamento,
            ]);
        } else {
            return $this->setResponse([], 'Código de autorización inválido', true);
        }
    }

    public function updateUserData(Request $request) {
        // Log::alert($request);
        $usuario = UserService::updateData($request->userId, $request->data, $request?->lineaData);
        return $this->setResponse([]);
    }

    public function store(Request $request) {

        $usuario = UserService::createUser($request->data, $request?->lineaData);
        // Log::alert($usuario);
        return $this->setResponse($usuario ? $usuario->toArray() : []);
    }

    public function getUsersPorLinea($linea) {

        if ($linea == 0) {
            $linea = 10;
        }

        $usuarios = UserService::obtenerMembersPorLinea($linea);
        return $this->setResponse($usuarios);
    }

    public function getUsersPorDepartamento($departamento) {

        $usuarios = UserService::obtenerMembersPorDepartamento($departamento);
        return $this->setResponse($usuarios);
    }
}
