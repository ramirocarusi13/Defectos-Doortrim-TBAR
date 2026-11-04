<?php

namespace App\Http\Controllers;

use App\Http\EventosStrap;
use App\Http\Jerarquias;
use App\Http\Kanban;
use App\Http\TipoEventoStrap;
use App\Models\ControlStrap;
use App\Models\Kanbans;
use App\Models\Sar\TKanban;
use App\Models\StrapEvento;
use App\Models\StrapPartNumber;
use App\Models\UserLog;
use App\Models\User;
use App\Models\VTStrap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ControlStrapController extends Controller {

    public function index() {

        $data = ControlStrap::orderBy('fecha_entrega', 'DESC')->get();
        // Log::alert("PASO");

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function filter(Request $request) {

        // Log::alert($request);

        $data = ControlStrap::when(!empty($request->posicion), function ($q) use ($request) {
            $q->where('posicion', $request->posicion);
        })
            ->when(!empty($request->lote), function ($q) use ($request) {
                $q->where('lote', $request->lote);
            })
            ->when(!empty($request->part_number), function ($q) use ($request) {
                $q->where('part_number', $request->part_number);
            })
            ->orderBy('fecha_entrega', 'DESC')->get()->take(80);

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function filterMovimientos(Request $request) {
        if ($request?->fecha_entrega_desde) {
            $fechaDesde = $request->fecha_entrega_desde;
            $fechaHasta = $request->fecha_entrega_hasta;
        }

        $fechaDesde = empty($fechaDesde) ? "2024-01-01" : date("Y-m-d", strtotime($fechaDesde)) . ' 06:00:00';
        $fechaHasta = empty($fechaHasta) ? "2200-01-01" : date("Y-m-d", strtotime($fechaHasta)) . ' 23:59:59';

        $data = VTStrap::when(!empty($request->posicion), function ($q) use ($request) {
            $q->where('posicion', $request->posicion);
        })
            ->when(!empty($request->lote), function ($q) use ($request) {
                $q->where('lote', $request->lote);
            })
            ->when(!empty($request->remanente), function ($q) use ($request) {
                $q->where('remanente', $request->remanente);
            })
            ->when(!empty($request->part_number), function ($q) use ($request) {
                $q->where('part_number', $request->part_number);
            })
            ->when(!empty($request->kanban), function ($q) use ($request) {
                $q->where('kanban', $request->kanban);
            })
            ->whereBetween('fecha_entrega', [$fechaDesde, $fechaHasta])

            ->orderBy('fecha_entrega', 'DESC')->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function anularStrap($id, $userId) {

        $strap = ControlStrap::where('id', $id)->first();

        if (!$strap) {
            return $this->setResponse([], "El Strap indicado no existe", true);
        }

        try {
            $this->registrarEventoError(EventosStrap::ANULACION, $strap->codigo_barra, $strap->lote, -1, $strap->posicion, null, null, $userId, null, $userId);
            ControlStrap::where('id', $id)->update(['anulado' => true]);
            return $this->setResponse([], "Anulado correctamente");
        } catch (\Throwable $th) {
            Log::error("ControlStrapController::anularStrap: " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
        }
    }

    public function getStrapEntregado() {
        $data = ControlStrap::with(['userIn', 'userOut'])->where('entregado', true)->where('anulado', false)->orderBy('fecha_entrega', 'DESC')->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function update(Request $request, $id) {

        $strap = ControlStrap::where('id', $id)->first();

        if (!$strap) {
            return $this->setResponse([], "El Strap indicado no existe", true);
        }

        $cantidad = 0;
        $evento = null;

        try {

            $data = $request->toArray();
            unset($data['user']);

            if (array_key_exists('anulado', $data)) {

                if ($data['anulado']) {
                    $evento = EventosStrap::ANULACION;
                } else {
                    $evento = EventosStrap::REACTIVACION;
                }
            } else if (array_key_exists('cantidad', $data)) {
                $evento = EventosStrap::MODIFICA_STRAP;
                $cantidad = intval($data['cantidad']);
            }


            $this->registrarEventoNormal($evento, $strap->codigo_barra, $strap->lote, $cantidad, $strap->posicion, null, null, $request->user, null, $request->user);
            ControlStrap::where('id', $id)->update($data);

            return $this->setResponse([], "Modificado correctamente");
        } catch (\Throwable $th) {
            Log::error("ControlStrapController::update: " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
        }
    }

    public function verificaModeloStrap($modelo) {

        $data = [
            'posiciones' => null
        ];

        $posiciones = StrapPartNumber::where('modelo', 'like', '%' . $modelo . '%')->get();

        if ($posiciones && count($posiciones) > 0) {
            //Por cada posición, busco el lote a egresar
            foreach ($posiciones as $p) {
                $posicion = ControlStrap::where('entregado', false)->where('anulado', false)->where('remanente', true)->where('cantidad', '>', 0)
                    ->where('posicion', $p->posicion)
                    ->orderBy('lote')->first();
                if ($posicion) {
                    $p->lote = $posicion->lote;
                } else {
                    $p->lote = "SIN STOCK";
                }
            }

            $data['posiciones'] = $posiciones;
        } else {
            return $this->setResponse([], "El modelo ingresado no existe", true);
        }
        return $this->setResponse($data);
    }

    public function verificaPlanillaReposicionStrap($partNumber) {

        $partNumber = substr($partNumber, 2);
        $partNumber = str_replace("'", "-", $partNumber);

        $data = ['posiciones' => ''];
        //Obtengo la posicion segun el modelo
        $posiciones = StrapPartNumber::where('part_number', $partNumber)->get();
        if ($posiciones) {
            //Por cada posición, busco el lote a egresar
            foreach ($posiciones as $p) {
                //Verifico si no egreso esta posicion

                $posicion = ControlStrap::where('entregado', false)
                    ->where('anulado', false)
                    ->where('remanente', true)
                    ->where('posicion', $p->posicion)
                    ->where('cantidad', '>', 0)
                    ->orderBy('lote')->first();

                if ($posicion) {
                    $p->lote = $posicion->lote;
                } else {
                    $p->lote = "SIN STOCK";
                }
            }

            $data['posiciones'] = $posiciones;
        }
        return $this->setResponse($data);
    }

    public function verificaKanbanRemoto($kanbanCode) {

        //PRIMERO VERIFICO SI EXISTE LOCAL
        $data = Kanban::registrarKanbanSiNoExiste($kanbanCode);

        if (!$data) {
            return $this->setResponse([], "EL KANBAN INGRESADO NO EXISTE", true);
        }

        $modelo = $data->modelo->nombre;
        // $data = Kanbans::with('modelo')->where('codigo', $kanbanCode)->first();
        // if (!$data) {

        //     $data = TKanban::with(['modelo'])->where('N_KANBAN', $kanbanCode)->first();

        //     //VERIFICO SI EXISTE EL KANBAN
        //     if (!$data) {
        //         // Log::alert("ControlStrapController::verificaKanbanRemoto : " . $kanbanCode . " - El kanban ingresado no existe");
        //         return $this->setResponse([], "EL KANBAN INGRESADO NO EXISTE", true);
        //     }

        //     //VERIFICO SI EL KANBAN NO FUE EGRESADO POR DESCARGA NORMAL
        //     //POR KANBAN DEBERIAN PODER RETIRAR 2 VECES, 1 DE CADA LADO
        //     $egreso = StrapEvento::where('kanban', $kanbanCode)->where('evento', EventosStrap::DESCARGA_NORMAL)->get();
        //     if ($egreso) {
        //         if (count($egreso->toArray()) >= 2) {
        //             return $this->setResponse([], "EL KANBAN INGRESADO YA RETIRO STRAP", true);
        //         }
        //     }

        //     $modelo = $data->modelo->MODELO;
        // } else {
        //     $modelo = $data->modelo->nombre;
        // }

        //Obtengo la posicion segun el modelo
        $posiciones = StrapPartNumber::where('modelo', 'like', '%' . $modelo . '%')->get();

        if ($posiciones) {
            //Por cada posición, busco el lote a egresar
            foreach ($posiciones as $p) {
                //Verifico si no egreso esta posicion
                $posicion = ControlStrap::where('entregado', false)
                    ->where('anulado', false)
                    ->where('remanente', false)
                    ->where('posicion', $p->posicion)
                    // ->orderBy('created_at', 'ASC')
                    ->orderByRaw('year(created_at) asc, created_at, CONVERT(int, lote) asc')
                    // ->orderBy('lote', 'DESC')
                    ->first();

                if ($posicion) {
                    $egresos = StrapEvento::where('kanban', $kanbanCode)->where('evento', EventosStrap::DESCARGA_NORMAL)->where('posicion', $posicion->posicion)->get();

                    $egresado = false;

                    foreach ($egresos as $egreso) {
                        if ($egreso->posicion == $posicion->posicion) {
                            $p->lote = 'EGRESADO PARA KANBAN';
                            $egresado = true;
                            break;
                        }
                    }

                    if (!$egresado) {
                        $p->lote = $posicion->lote;
                    }
                } else {
                    $p->lote = "SIN STOCK";
                }
            }

            $data->posiciones = $posiciones;
        }

        return $this->setResponse($data->toArray());
    }

    public function verificaKanbanModeloRemoto(Request $request) {

        //FORZAR ES PARA CUANDO EL TL o GL FUERZA LA SALIDA AUNQUE NO CORRESPONDA EL LOTE
        $forzar = boolval($request->forzar);

        // Log::alert($request);

        try {
            $kanbanCode = $request->kanban;

            //OBTENGO EL STRAP SEGUN EL CODIGO DE BARRA ESCANEADO
            $strap = ControlStrap::with(['parts'])->where('codigo_barra', $request->barcode)
                ->where('entregado', false)->where('anulado', false)->where('cantidad', '>', 0)
                ->first();

            //SI NO HAY STRAP CON EL CODIGO DE BARRA ESCANEADO, REGISTRO ERROR
            if (!$strap) {
                $evento = $this->registrarEventoError(EventosStrap::STRAP_INEXISTENTE, $request->barcode, null, 0, null, null, $kanbanCode);
                return $this->setResponse(['id_evento' => $evento->id], "El strap indicado no existe en la base de datos o ya fue egresado", true);
            }

            //VERIFICO SI NO SALIO STRAP PARA ESE KANBAN EN DESCARGA NORMAL
            if (!$strap->remanente) {
                $egreso = ControlStrap::where('kanban', $kanbanCode)->where('posicion', $strap->posicion)
                    ->where('anulado', false)->where('entregado', true)
                    ->first();

                if ($egreso) {
                    return $this->setResponse([], "EL KANBAN INGRESADO YA RETIRO STRAP PARA EL PART NUMBER SELECCIONADO", true);
                }
            }

            //OBTENGO EL LOTE QUE DEBERíA SALIR, SEGUN EL PART NUMBER
            $strapFifo = ControlStrap::where('part_number', $strap->part_number)
                ->where('entregado', false)
                ->where('anulado', false)
                ->where('remanente', false)
                // ->orderBy('created_at', 'ASC')
                // ->orderByRaw('CONVERT(int, lote) asc')
                ->orderByRaw('year(created_at) asc, created_at, CONVERT(int, lote) asc')

                // ->orderBy('lote', 'DESC')
                ->first();

            $modelos = explode("|", $strap->parts->modelo);

            if (!$strap->remanente) {

                //SI NO ES REPOSICION, OBTENGO LOS DATOS SEGUN EL KANBAN ESCANEADO. FILTRANDO LOS MODELOS PERMITIDOS

                $data = Kanbans::with('modelo')->whereHas('modelo', function ($q) use ($modelos) {
                    $q->whereIn('nombre', $modelos);
                })
                    ->where('codigo', $kanbanCode)
                    ->first();

                if (!$data) {
                    $data = Kanban::registrarKanbanSiNoExiste($kanbanCode);
                }

                if ($data) {
                    if (is_null($data->modelo)) {
                        Kanbans::fixKanbanModelo($data);
                    }

                    $data = Kanbans::with('modelo')->whereHas('modelo', function ($q) use ($modelos) {
                        $q->whereIn('nombre', $modelos);
                    })
                        ->where('codigo', $kanbanCode)
                        ->first();
                }
            } else {
                $data = null;
            }

            if ($data || $strap->remanente) {
                $modelo = $data ? $data->modelo->nombre : null;

                if (!$forzar) {
                    //SI ES REPOSICION, NO VALIDO FIFO, YA QUE LO HICE ANTES
                    if (!$strap->remanente && intval($strapFifo->lote) < intval($strap->lote)) {
                        $evento = $this->registrarEventoError(EventosStrap::FIFO_INCORRECTO_LOTE, $request->barcode,  $strap->lote, 0, $strap->posicion, $modelo, $kanbanCode);
                        return $this->setResponse(['id_evento' => $evento->id], "Error en FIFO. Debe retirar el strap con lote " . $strapFifo->lote, true);
                    }
                }

                //SI ES REPOSICION, DESCUENTO LA CANTIDAD
                if ($strap->remanente) {

                    foreach ($request->reposicion['items'] as $r) {
                        //OBTENGO EL STRAP SEGUN EL CÓDIGO ESCANEADO Y EL LOTE
                        $strap = ControlStrap::with(['parts'])->where('codigo_barra', $request->barcode)
                            ->where('entregado', false)->where('anulado', false)->where('cantidad', '>', 0)->where('lote', $r['lote'])
                            ->first();

                        //DESCUENTO LA CANTIDAD
                        ControlStrap::where('codigo_barra', $request->barcode)->where('lote', $r['lote'])->update([
                            'entregado' => false,
                            'cantidad'  =>  intval($strap->cantidad) - intval($r['cantidad'])
                        ]);

                        //REGISTRO EL DESCUENTO
                        $this->registrarEventoNormal(EventosStrap::DESCARGA_PARCIAL_REPOSICION, $request->barcode, $r['lote'], intval($r['cantidad']) * -1, $r['posicion'], $modelo, $kanbanCode, $request->usuarioSolicitante, $request->linea);
                    }
                } else {
                    //SI ES RETIRO STRAP NORMAL, LO MARCO COMO SALIDA
                    ControlStrap::where('codigo_barra', $request->barcode)->update([
                        'entregado' => true,
                        'user_id'   => auth()->guard('api')->user()->id,
                        'modelo'    => $data->modelo->nombre,
                        'kanban'    => $kanbanCode,
                        'fecha_entrega' => date('Y-m-d H:i:s')
                    ]);

                    $this->registrarEventoNormal(EventosStrap::DESCARGA_NORMAL, $request->barcode, $strap->lote, -1, $strap->posicion, $data->modelo->nombre, $kanbanCode);
                }

                return $this->setResponse(['exists' => true]);
            } else {
                //SI NO ENCONTRE DATA, ES PORQUE EL CODIGO ESCANEADO NO CORRESPONDE CON EL MODELO DE KANBAN
                $evento = $this->registrarEventoError(EventosStrap::MODELO_INCORRECTO, $request->barcode, $strap->lote, 1, $strap->posicion, null, $kanbanCode);
                return $this->setResponse(['id_evento' => $evento->id], 'El Strap no pertenece al modelo indicado', true);
            }
        } catch (\Throwable $th) {
            Log::error("ControlStrapController::verificaKanbanModeloRemoto : " . $th->getMessage());
            return $this->setResponse([], 'Ocurrió un error. Comuniquese con el encargado de sistemas.', true);
        }
    }

    public function store(Request $request) {

        //Obtengo el numero de fifo por lote

        $lote = intval($request->lote);

        $strapFifo = ControlStrap::where('part_number', $request->part_number)
            ->where('lote', $lote)
            ->where('anulado', false)
            ->orderBy('fifo', 'DESC')
            ->first();

        $fifo = 1;
        if ($strapFifo) {
            // Log::alert($strapFifo);
            $fifo = intval($strapFifo->fifo) + 1;
        } else {
            $fifo = 1;
        }

        $remanente = boolval($request->remanente);

        if ($remanente) {
            //Si es remanente, imprimo sin lote ni fifo
            $codigoBarra = $request->part_number  . "|" . $request->posicion;
        } else {
            $codigoBarra = $request->part_number . "|" . strval($fifo) . "|" . $lote  . "|" . $request->posicion;
        }

        try {
            $existente = null;

            if ($remanente) {

                $existente = ControlStrap::where('codigo_barra', $codigoBarra)->where('anulado', false)->where('remanente', true)->where('lote', $lote)->first();

                if ($existente) {
                    $data = ControlStrap::where('id', $existente->id)->update([
                        'cantidad' => intval($existente->cantidad) + intval($request->cantidad)
                    ]);

                    $this->registrarEventoNormal(EventosStrap::INGRESO_STRAP, $codigoBarra, $lote, 1, $request->posicion);
                    return $this->setResponse([]);
                }
            }

            if (!$existente) {
                $data = [
                    'modelo'        => null,
                    'part_number'   => $request->part_number,
                    'codigo_barra'  => $codigoBarra,
                    'entregado'     => false,
                    'posicion'      => $request->posicion,
                    'user_id_in'    => $request->user, //auth()->guard('api')->user()->id,
                    'fifo'          => $fifo, //intval($request->fifo),
                    'lote'          => $lote,
                    'remanente'     => $remanente,
                    'cantidad'      => intval($request->cantidad) == 0 ? 1 : intval($request->cantidad),
                    'anulado'       => false
                ];
                ControlStrap::create($data);
            }
            $this->registrarEventoNormal(EventosStrap::INGRESO_STRAP, $codigoBarra, $lote, 1, $request->posicion);

            // Log::alert($data);
            return $this->setResponse($data);
        } catch (\Throwable $th) {
            Log::error("ControlStrapController::store : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas.", true);
        }
    }

    public function validaEventoStrap(Request $request) {
        //Busco el usuario asociado al codigo de autorizacion
        $user = User::where('cod_autorizacion', $request->codAutorizacion)
            ->where('rol', '>=', Jerarquias::TEAM_LEADER)
            ->first();

        if (!$user) {
            return $this->setResponse([], 'Código de autorización inválido', true);
        }

        //Solo deberia autorizar uno con los permisos suficientes, ahora solo tendran codigo de autorizacion los autorizados
        try {
            StrapEvento::where('id', $request->eventoId)->update(['autoriza_user_id'  => $user->id]);

            return $this->setResponse([]);
        } catch (\Throwable $th) {
            Log::error("ControlStrapController::validaEventoStrap : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas.", true);
        }
    }

    public function verificarCantidadReposicion(Request $request) {

        $response = [];
        // Log::alert($request);
        $data = ControlStrap::where('codigo_barra', $request->barcode)
            ->where('remanente', true)
            ->where('entregado', false)
            ->where('anulado', false)
            ->where('cantidad', '>', 0)
            ->orderBy('lote')
            ->get();

        $cantidadRestante = intval($request->cantidad);

        foreach ($data as $d) {

            if ($cantidadRestante <= 0) {
                break;
            }

            if ($d->cantidad > 0) {
                if ($d->cantidad >= $cantidadRestante) {
                    array_push($response, [
                        'cantidad'  => $cantidadRestante,
                        'posicion'  => $d->posicion,
                        'lote'      => $d->lote
                    ]);
                    $cantidadRestante = 0;
                } else if ($d->cantidad < $cantidadRestante) {
                    $cantidadRestante = $cantidadRestante - intval($d->cantidad);
                    array_push($response, [
                        'cantidad'  => $d->cantidad,
                        'posicion'  => $d->posicion,
                        'lote'      => $d->lote
                    ]);
                }
            }
        }

        if ($cantidadRestante > 0) {
            // Log::alert("Cantidad Restante " . $cantidadRestante);
            return $this->setResponse([], "Stock insuficiente", true);
        }

        return $this->setResponse($response);
    }

    public function userValidoStrapByCodAutorizacion(Request $request) {
        $user = User::where('cod_autorizacion', $request->codigo)
            ->when(!empty($request->tl), function ($q) use ($request) {
                if ($request->tl == 1 || $request->tl == "1") {
                    $q->where('rol', '>=', Jerarquias::TEAM_LEADER);
                }
                // $q->where('tl', $request->tl);
            })
            ->first();

        if (!$user) {
            return $this->setResponse([], 'Código de autorización inválido', true);
        } else {
            return $this->setResponse($user->toArray());
        }
    }

    public function validarUsuarioStrap(Request $request) {
        // Log::alert($request);
        // $user = User::with(['rol'])
        //     ->whereRaw("UPPER(cod_autorizacion)='?'", strtoupper(trim($request->codigo)))
        //     ->first();
        $user = User::with(['rol', 'linea'])->where('cod_autorizacion', trim($request->codigo))->first();

        if ($user) {
            $token = $user->createToken('Laravel Password Grant Client')->accessToken;

            UserLog::create([
                'user_id'   => $user->id,
                'tipo'      => 'INGRESO'
            ]);

            return $this->setResponse([
                'id'            => $user->id,
                'token'         => $token,
                'name'          => $user->name,
                'email'         => $user->email,
                'rol'           => $user->rol,
                'tl'            => $user->tl,
                'gl'            => $user->gl,
                'rol'           => $user->rol,
                'departamento'  => $user->departamento,
                'area'          => $user->area,
                'turno'         => $user->turno,
                'linea'         => $user->linea
            ]);
        } else {
            return $this->setResponse([], 'Código de autorización inválido', true);
        }
    }

    public function logoutStrap(Request $request) {
        $user = auth()->guard('api')->user();

        if (!$user) {
            return $this->setResponse([], 'Usuario no autenticado', true);
        }

        try {
            UserLog::create([
                'user_id'   => $user->id,
                'tipo'      => 'EGRESO'
            ]);

            // $user->token()?->revoke();

            return $this->setResponse([]);
        } catch (\Throwable $th) {
            Log::error("ControlStrapController::logoutStrap: " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
        }
    }

    private function registrarEventoError($evento, $codigoBarra, $lote = null, $cantidad = 1, $posicion = null, $modelo = null, $kanban = null, $userSolicitante = null, $linea = null, $userId = null) {
        $res = StrapEvento::create([
            'evento'            => $evento,
            'user_id'           => $userId ? $userId : auth()->guard('api')->user()->id,
            'codigo_escaneado'  => $codigoBarra,
            'kanban'            => $kanban,
            'cantidad'          => intval($cantidad),
            'posicion'          => $posicion,
            'modelo'            => $modelo ? strtoupper($modelo) : null,
            'lote'              => $lote,
            'tipo'              => TipoEventoStrap::ERROR,
            'user_solicitante'  => $userSolicitante,
            'linea'             => $linea,
            'autoriza_user_id'  => $userId ? $userId : null
        ]);

        return $res;
    }

    private function registrarEventoNormal($evento, $codigoBarra, $lote, $cantidad = 1, $posicion = null, $modelo = null, $kanban = null, $userSolicitante = null, $linea = null, $userId = null) {
        StrapEvento::create([
            'evento'            => $evento,
            'user_id'           => $userId ? $userId : auth()->guard('api')->user()->id,
            'codigo_escaneado'  => $codigoBarra,
            'kanban'            => $kanban,
            'cantidad'          => intval($cantidad),
            'posicion'          => $posicion,
            'modelo'            => $modelo ? strtoupper($modelo) : null,
            'lote'              => $lote,
            'tipo'              => TipoEventoStrap::NORMAL,
            'user_solicitante'  => $userSolicitante,
            'linea'             => $linea,
            'autoriza_user_id'  => $userId ? $userId : null
        ]);
    }
}
