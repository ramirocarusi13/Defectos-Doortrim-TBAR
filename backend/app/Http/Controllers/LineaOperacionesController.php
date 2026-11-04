<?php

namespace App\Http\Controllers;

use App\Http\Departamentos;
use App\Models\LineaOperaciones;
use App\Models\Lineas;
use App\Models\User;
use App\Models\UserLinea;
use App\Models\UserOperacionLinea;
use App\Models\UserPolivalencias;
use App\Services\LineaOperacionesService;
use App\Services\TableroService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineaOperacionesController extends Controller {

    public function updateTablero(Request $request) {
        $tableroServie = new TableroService;

        $tableroServie->actualizarTablero($request);

        return $this->setResponse([]);
    }

    public function getOperacionesLinea($linea) {
        $operacionService = new LineaOperacionesService;

        return $this->setResponse($operacionService->obtenerOperacionesPorLinea(intval($linea)));
    }

    public function index(Request $request) {

        $response = [];

        $ordenes = [
            'M1' => 1,
            'S1' => 6,
            'M2' => 2,
            'S2' => 7,
            'M3' => 3,
            'S3' => 8,
            'M4' => 4,
            'S4' => 9,
            'M5' => 5,
            'S5' => 10,
            'M6' => 11,
            'S6' => 16,
            'M7' => 12,
            'M8' => 13,
            'S8' => 18,
            'M9' => 14,
            'M10' => 15,
            'S10' => 20,
            'M11' => 17,
            'M13' => 19,
            'F'   => 20,
            'NP'  => 21
        ];

        $turno = $request->input('turno');

        $lineas = Lineas::get()->toArray();

        $orden = 1;
        foreach ($lineas as $linea) {

            $esSublinea = str_contains($linea['codigo'], "S");
            if (empty($esSublinea)) {
                $esSublinea = 0;
            }

            $lineaId = str_replace("S", "", str_replace("M", "", $linea['codigo']));

            $data = LineaOperaciones::selectRaw("id,nombre,linea,nivel,orden,habilitado,sublinea,'' as operario")
                ->where('linea', $lineaId)
                ->where('sublinea', $esSublinea)
                ->orderBy('orden')
                ->get();

            foreach ($data as $item) {
                $item["operario"] = UserOperacionLinea::with('user')
                    ->where('operacion_id', $item['id'])
                    ->where('turno', $turno)
                    ->first();
            }

            array_push($response, [
                'linea'             => $linea['codigo'],
                'operaciones'       => $data,
                'orden'             => $ordenes[$linea['codigo']],
                'turno_blanco'      => $linea['turno_blanco'],
                'turno_amarillo'    => $linea['turno_amarillo']
            ]);

            // if ($linea['id'] != '7' && $linea['id'] != '9' && $linea['id'] != '11' && $linea['id'] != '13') {
            //     $data = LineaOperaciones::selectRaw("id,nombre,linea,nivel,orden,habilitado,sublinea,'' as operario")->where('linea', $linea['id'])
            //         ->where('sublinea', 1)
            //         ->orderBy('orden')
            //         ->get();

            //     foreach ($data as $item) {
            //         $item["operario"] = UserOperacionLinea::with('user')
            //             ->where('operacion_id', $item['id'])
            //             ->where('turno', $turno)
            //             ->first();
            //     }

            //     array_push($response, [
            //         'linea'         => 'S' . $linea['id'],
            //         'operaciones'   => $data,
            //         'orden'         => $ordenes['S' . $linea['id']]
            //     ]);
            // }

            $orden++;
        }

        // Log::alert($response);

        return $this->setResponse($response);
    }

    public function get($id) {
        $data = LineaOperaciones::where('id', $id)->first()->toArray();
        return $this->setResponse($data);
    }

    public function getUsersDisponibles() {
        $response = [];
        $users = User::with(['polivalencias'])->withCount('operacionTrabajoLinea')->where('departamento', Departamentos::PRODUCCION)->get();

        foreach ($users as $user) {
            if ($user->operacion_trabajo_linea_count == 0) {
                array_push($response, $user);
            }
        }

        return $this->setResponse($response);
    }

    public function reasignar(Request $request) {
        $linea = $request->linea;
        $disponibles = $request->disponibles;
        $userUsados = [];

        //Obtengo las operaciones de la linea
        $operaciones = LineaOperaciones::where('linea', $linea)->orderBy('nivel', 'DESC')->orderBy('orden', 'ASC')->get();

        foreach ($operaciones as $operacion) {
            //busco el usuario más capaz para la misma
            $user = UserPolivalencias::where('operacion_id', $operacion->id)
                ->whereNotIn('user_id', $userUsados)
                ->whereHas('user', function ($q) {
                    $q->where('departamento', Departamentos::PRODUCCION);
                })
                // ->where('departamento', Departamentos::PRODUCCION)
                ->orderBy('polivalencia', 'DESC')->first();
            //Elimino la asignación actual
            UserOperacionLinea::where('operacion_id', intval($operacion->id))->delete();

            if ($user && $operacion->habilitado) {
                array_push($userUsados, $user->user_id);
                UserOperacionLinea::create([
                    'user_id'       => intval($user->user_id),
                    'operacion_id'  => intval($operacion->id),
                    'autorizante'   => auth()->guard('api')->user()->id
                ]);
            }
        }

        //Retorno la información
        $response = [];

        $lineas = LineaOperaciones::select('linea')->groupBy('linea')->get()->toArray();

        foreach ($lineas as $linea) {
            $data = LineaOperaciones::with(['operario.polivalencia', 'operario.user.polivalencias'])->where('linea', $linea)->get();

            array_push($response, [
                'linea'         => $linea['linea'],
                'operaciones'   => $data
            ]);
        }

        return $this->setResponse($response);
    }


    public function moverOperador(Request $request) {
        $tableroServie = new TableroService;

        $tableroServie->moverOperador($request->operario, $request->operacion);

        return $this->setResponse([]);
    }

    public function saveOperacion(Request $request) {

        LineaOperaciones::create([
            'nombre'        => $request->nombre,
            'nivel'         => $request->nivel,
            'orden'         => $request->orden,
            'habilitado'    => boolval($request->habilitado),
            'sublinea'      => strpos($request->linea, "M") >= -1 ? 0 : 1,
            'linea'         => intval(str_replace("S", "", str_replace("M", "", $request->linea)))
        ]);

        return $this->setResponse([]);
    }

    public function store(Request $request) {
        // Log::alert($request);
        // UserOperacionLinea::where('user_id', '<>', null)->delete();

        foreach ($request->items as $req) {
            if ($req['linea'] != '0') {
                foreach ($req['operaciones'] as $op) {
                    if (array_key_exists('operario', $op)) {
                        if ($op['operario']) {
                            if ($op['operario']['user']['turno'] != '' && !is_null($op['operario']['user']['turno'])) {
                                UserOperacionLinea::updateOrCreate([
                                    'operacion_id'  => intval($op['id']),
                                    'turno'         => $op['operario']['user']['turno']
                                ], [
                                    'user_id'       => intval($op['operario']['user']['id']),
                                    'operacion_id'  => intval($op['id']),
                                    'autorizante'   => auth()->guard('api')->user()->id,
                                    'turno'         => $op['operario']['user']['turno']
                                ]);

                                UserLinea::updateOrCreate(
                                    [
                                        'user_id'   => intval($op['operario']['user']['id'])
                                    ],
                                    [
                                        'user_id'   => intval($op['operario']['user']['id']),
                                        'linea_id'  => intval($op['linea']),
                                        'sublinea'  => intval($op['sublinea'])
                                    ]
                                );
                            }
                        }
                    }
                }
            }
        }

        return $this->setResponse([]);
    }


    public function show(LineaOperaciones $lineaOperaciones) {
        //
    }


    public function update(Request $request, LineaOperaciones $lineaOperaciones) {

        try {
            LineaOperaciones::where('id', $lineaOperaciones->id)->update([
                'nombre'        => $request->nombre,
                'habilitado'    => boolval($request->habilitado),
                'nivel'         => $request->nivel,
                // 'sublinea'      => $request->sublinea
            ]);

            return $this->setResponse([]);
        } catch (\Throwable $th) {
            return $this->setResponse([], $th->getMessage(), true);
        }
    }


    public function destroy($userId) {
        UserLinea::where('user_id', $userId)->delete();
        UserOperacionLinea::where('user_id', $userId)->delete();

        return $this->setResponse([]);
    }
}
