<?php

namespace App\Services;

use App\Http\Jerarquias;
use App\Http\Roles;
use App\Models\AusentismoDiario;
use App\Models\AusentismoHoraHora;
use App\Models\LineaOperaciones;
use App\Models\User;
use App\Models\UserLinea;
use App\Models\UserOperacionLinea;
use App\Models\VTOperacionUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class UserService {

    static function obtenerMembersPorDepartamento(string $departamento): array {
        $usuarios = User::with('linea')
            ->where('departamento', $departamento)
            // ->orWhere('name', 'like', '%bufferturno%') //Incluyo el buffer de turno
            ->orWhere(function ($q) {
                $q->where('name', 'like', '%bufferturno%'); //Incluyo el buffer de turno
                $q->orWhere('name', 'like', '%defcorte%');
                $q->orWhere('name', 'like', '%defproveedor%');
            })

            ->get();

        return $usuarios ? $usuarios->toArray() : [];
    }

    static function obtenerMembersPorLinea(int $lineaId, string $turno = ''): array {

        $usuarios = User::with(['linea', 'operacionLinea', 'reemplazo.user'])
            ->whereHas('linea', function ($q) use ($lineaId) {
                $q->where('linea_id', $lineaId);
            })
            ->orWhere(function ($q) {
                $q->where('name', 'like', '%bufferturno%'); //Incluyo el buffer de turno
                $q->orWhere('name', 'like', '%defcorte%');
                $q->orWhere('name', 'like', '%defproveedor%');
            })
            ->orWhere('rol', Jerarquias::UTILITY)

            // ->where('id', 20431)
            ->get();

        return $usuarios ? $usuarios->toArray() : [];
    }

    static function obtenerMembersConLinea(): array {

        $usuarios = User::with(['linea', 'operacionLinea', 'reemplazo.user'])
            ->whereHas('linea', function ($q) {
                $q->where('linea_id', '>', 0);
            })
            ->orWhere(function ($q) {
                $q->where('name', 'like', '%bufferturno%'); //Incluyo el buffer de turno
                $q->orWhere('name', 'like', '%defcorte%');
                $q->orWhere('name', 'like', '%defproveedor%');
            })
            ->orWhere('rol', Jerarquias::UTILITY)
            ->get();


        return $usuarios ? $usuarios->toArray() : [];
    }

    static function userExists(int $userId): User|null {
        return User::where('id', $userId)->first();
    }

    static function createUser($data, $lineaData) {
        if (!is_null($data['password']) && $data['password'] != '') {
            $data['password'] = Hash::make($data['password']);
        } else {
            $data['password'] = Hash::make("1");
        }

        if (is_null($data['rol']) || $data['rol'] == 'null') {
            $data['rol'] = null;
        }

        if (is_null($data['turno']) || $data['turno'] == 'null') {
            $data['turno'] = null;
        }

        if (is_null($data['area']) || $data['area'] == 'null') {
            $data['area'] = null;
        }

        try {
            $user = User::create($data);

            if (count($lineaData) > 0 && $user) {
                UserLinea::create([
                    'user_id'   => $user->id,
                    'linea_id'  => intval($lineaData['linea']),
                    'sublinea'  => $lineaData['sublinea'],
                    'titular'   => $lineaData['titular'],
                ]);

                if ($lineaData['operacion'] > 0) {
                    UserOperacionLinea::updateOrCreate(
                        [
                            'user_id'   => $user->id
                        ],
                        [
                            'user_id'       => $user->id,
                            'operacion_id'  => $lineaData['operacion']
                        ]
                    );
                }
            }
        } catch (\Throwable $th) {
            Log::error("UserService::createUser : " . $th->getMessage());
            return null;
        }

        return $user;
    }

    static function updateData(int $userId, array $data, array $lineaData = []): bool {
        if (!UserService::userExists($userId)) {
            return false;
        }
        if (!is_null($data['password']) && $data['password'] != '') {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (is_null($data['rol']) || $data['rol'] == 'null') {
            $data['rol'] = null;
        }

        if (is_null($data['turno']) || $data['turno'] == 'null') {
            $data['turno'] = null;
        }

        if (is_null($data['area']) || $data['area'] == 'null') {
            $data['area'] = null;
        }

        try {
            User::where('id', $userId)->update($data);

            if (count($lineaData) > 0) {
                // UserLinea::where('user_id', intval($userId))->delete();

                UserLinea::updateOrCreate(
                    [
                        'user_id'   => intval($userId)
                    ],
                    [
                        'user_id'           => $userId,
                        'linea_id'          => intval($lineaData['linea']),
                        'sublinea'          => $lineaData['sublinea'],
                        'titular'           => $lineaData['titular'],
                        'operacion_id'      => $lineaData['operacion'] > 0 ? $lineaData['operacion'] : null,
                    ]
                );

                // UserLinea::create([
                //     'user_id'   => $userId,
                //     'linea_id'  => intval($lineaData['linea']),
                //     'sublinea'  => $lineaData['sublinea'],
                //     'titular'   => $lineaData['titular'],
                // ]);

                if ($lineaData['operacion'] > 0) {
                    UserOperacionLinea::updateOrCreate(
                        [
                            'user_id'   => $userId
                        ],
                        [
                            'user_id'       => $userId,
                            'operacion_id'  => $lineaData['operacion'],
                            'turno'         => $data['turno']
                        ]
                    );
                } else {
                    UserOperacionLinea::where('user_id', intval($userId))->delete();
                }
            } else {
                UserLinea::where('user_id', intval($userId))->delete();
            }
        } catch (\Throwable $th) {
            Log::error("UserService::updateData : " . $th->getMessage());
            return false;
        }

        return true;
    }

    static function changePassword(int $userId, string $password): bool {

        //Verifico existencia usuario
        if (!UserService::userExists($userId)) {
            return false;
        }
        $newPassword = Hash::make($password);

        try {
            User::where('id', $userId)->update(['password' => $newPassword]);
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("UserService::changePassword : " . $th->getMessage());
            return false;
        }

        return true;
    }

    static function getUsersAusentismoHoraHora(string $fecha, string $turno) {

        $usersLineas = VTOperacionUser::selectRaw("VT_OPERACION_USER.id,VT_OPERACION_USER.nombre as operacion,VT_OPERACION_USER.user_id,isnull(VT_OPERACION_USER.email,'') as email,VT_OPERACION_USER.turno,ausentismo_hora_horas.fecha,VT_OPERACION_USER.titular,VT_OPERACION_USER.sublinea,VT_OPERACION_USER.linea as linea_id,ausentismo_hora_horas.estado,ausentismo_hora_horas.comentario,isnull(VT_OPERACION_USER.rol,'') as rol,VT_OPERACION_USER.area,VT_OPERACION_USER.departamento")
            ->leftJoin('ausentismo_hora_horas', 'ausentismo_hora_horas.user_id', '=', 'VT_OPERACION_USER.user_id')
            ->where(function ($q) use ($fecha) {
                $q->where('ausentismo_hora_horas.fecha', $fecha);
                $q->orWhere('ausentismo_hora_horas.fecha', null);
            })
            ->where(function ($q) use ($turno) {
                $q->where('VT_OPERACION_USER.turno', $turno);
                $q->orWhere('VT_OPERACION_USER.turno', null);
            })
            ->where(function ($q) {
                $q->where('VT_OPERACION_USER.departamento', 'PRODUCCION');
                $q->orWhere('VT_OPERACION_USER.departamento', null);
            })
            ->where(function ($q) {
                $q->where('VT_OPERACION_USER.area', 'COSTURA');
                $q->orWhere('VT_OPERACION_USER.area', 'CORTE');
                $q->orWhere('VT_OPERACION_USER.area', 'MH');
                $q->orWhere('VT_OPERACION_USER.area', null);
            })
            ->get();

        $users = User::selectRaw("'' as operacion,users.id,users.email,ausentismo_hora_horas.turno,ausentismo_hora_horas.fecha,1 as titular,0 as sublinea,'' as linea_id,ausentismo_hora_horas.estado,ausentismo_hora_horas.comentario,users.rol,users.area,users.departamento")
            // ->leftJoin('user_lineas', 'users.id', '=', 'user_lineas.user_id')
            ->leftJoin('ausentismo_hora_horas', 'users.id', '=', 'ausentismo_hora_horas.user_id')
            // ->whereHas('linea', function ($q) {
            //     $q->where('id', null);
            // })
            ->where(function ($q) use ($fecha) {
                $q->where('ausentismo_hora_horas.fecha', $fecha);
                $q->orWhere('ausentismo_hora_horas.fecha', null);
            })
            ->where(function ($q) use ($turno) {
                $q->where('ausentismo_hora_horas.turno', $turno);
                $q->orWhere('users.turno', $turno);
            })
            ->where('departamento', 'PRODUCCION')
            ->where(function ($q) {
                $q->where('area', 'COSTURA');
                $q->orWhere('area', 'CORTE');
                $q->orWhere('area', 'MH');
                $q->orWhere('area', 'DOJO');
            })
            ->get();


        if (count($users) == 0) {
            // SI no hay usuarios es porque no hay ausencias en esa fecha,
            $users = User::selectRaw("users.id,users.email,users.turno,'' as fecha,user_lineas.titular,user_lineas.sublinea,user_lineas.linea_id,'' as estado,'' as comentario,users.rol,users.area,users.departamento")
                ->leftJoin('user_lineas', 'users.id', '=', 'user_lineas.user_id')
                ->where(function ($q) use ($turno) {
                    $q->orWhere('users.turno', $turno);
                })
                ->where('departamento', 'PRODUCCION')
                ->where(function ($q) {
                    $q->where('area', 'COSTURA');
                    $q->orWhere('area', 'CORTE');
                    $q->orWhere('area', 'MH');
                    $q->orWhere('area', 'DOJO');
                })
                ->get();
        }

        // $users = AusentismoHoraHora::where('fecha', $fecha)->where('turno', $turno)->get();
        return [
            'users' => $users ? $users->toArray() : [],
            'users_lineas' => $usersLineas ? $usersLineas->toArray() : [],
        ];
    }
}
