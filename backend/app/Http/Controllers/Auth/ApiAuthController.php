<?php

namespace App\Http\Controllers\Auth;

use App\Http\ApiCode;
use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\Postulante;
use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

class ApiAuthController extends Controller {

    protected $rules = [
        'required'  => 'El campo :attribute es requerido',
        'unique'    => 'Ya existe un usuario registrado con el email ingresado',
        'confirmed' => 'Las contraseñas no coinciden',
        'min'       => 'El campo :attribute debe ser minimo de :min caracteres',
        'max'       => 'El campo :attribute no puede tener más de :max caracteres',
    ];

    public function login(Request $request) {


        $validator = Validator::make($request->all(), [
            'user'      => 'required|string|max:255',
            'password'  => 'required|string|min:1',
        ], $this->rules);

        if ($validator->fails()) {
            return $this->setResponse($validator->errors()->all(), "", true);
        }

        $user = User::with(['rol', 'menu', 'linea'])->where('name', $request->user)->first();


        if ($user) {
            if (Hash::check($request->password, $user->password)) {

                $token = $user->createToken('Laravel Password Grant Client')->accessToken;

                return $this->setResponse([
                    'id'                => $user->id,
                    'token'             => $token,
                    'name'              => $user->name,
                    'email'             => $user->email,
                    'rol'               => $user->rol,
                    'menu'              => $user->menu,
                    'linea'             => $user?->linea,
                    'turno'             => $user->turno,
                    'departamento'      => $user?->departamento,
                    'area'              => $user?->area,
                    'turno'             => $user?->turno,
                    'proceso_critico'   => $user?->proceso_critico,
                    'activo'            => $user?->activo,
                    'imagen'            => $user?->imagen,
                ]);
            } else {
                return $this->setResponse([], "Usuario o contraseña incorrectos", true);
            }
        } else {
            return $this->setResponse([], "Usuario o contraseña incorrectos", true);
        }
    }
}
