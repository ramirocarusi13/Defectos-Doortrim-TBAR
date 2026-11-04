<?php

namespace App\Http\Controllers;

use App\Http\Depositos;
use App\Http\Estados;
use App\Http\EstadosQr;
use App\Http\Kanban;
use App\Http\MotivosReimpresion;
use App\Http\WmsUnidades;
use App\Models\Configuracion;
use App\Models\Despachos;
use App\Models\DespachosItems;
use App\Models\EpEtiqueta;
use App\Models\EpEtiquetaMid;
use App\Models\EstadoKanban;
use App\Models\Kanbans;
use App\Models\LogFg;
use App\Models\ModeloLinea;
use App\Models\Modelos;
use App\Models\Movimientos;
use App\Models\MovimientosContenido;
use App\Models\Partes;
use App\Models\PcPendienteImpresion;
use App\Models\Sar\TKanban;
use App\Models\Sar\TRegistrosKanban;
use App\Models\Ubicaciones;
use App\Models\VTLogFG;
use App\Services\EtiquetaService;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class KanbansController extends Controller {

    public function index() {
        $kanbans = Kanbans::with(['modelo.material', 'modelo.color', 'modelo.fila', 'estado.estado'])
            ->get()->toArray();

        return $this->setResponse($kanbans);
    }

    public function fgMovimientos(Request $request) {

        $desde = $request->desde;
        $hasta = $request->hasta;

        // Log::alert($desde);

        $query = VTLogFG::query();

        if ($desde) {
            $query->where('fecha', '>=', $desde);
        } else {
            //si no informa fecha traigo lo del dia
            $hoy = date('Y-m-d');
            $query->where('fecha', '>=', $hoy . ' 06:00:00');
        }

        if ($hasta) {
            $query->whereDate('fecha', '<=', $hasta);
        } else {
            //si no informa fecha traigo lo del dia
            $hoy = new DateTime();
            $hoy->add(new \DateInterval('P1D'));
            $hoy = $hoy->format('Y-m-d');
            $query->where('fecha', '<=', $hoy . ' 00:50:00');
        }

        $data = $query->orderBy('fecha', 'ASC')->get();

        return $this->setResponse($data->toArray());
    }

    public function filter(Request $request) {

        $filters = $request->toArray();

        $kanbans = Kanbans::with(['modelo.material', 'modelo.color', 'modelo.fila', 'estado.estado', 'estado.linea'])
            ->when(!empty($filters['status']), function ($q) use ($filters) {
                $q->whereHas('estado', function ($query) use ($filters) {
                    $query->where('estado_id', $filters['status']);
                });
            })
            ->get();

        if ($kanbans) {
            $kanbans = $kanbans->toArray();
        } else {
            $kanbans = [];
        }

        return $this->setResponse($kanbans);
    }

    private function bloqueaEstado($estadoActual, $nuevoEstado) {

        //GENERADO -> BUFFER
        //BUFFER -> SUB ASSY
        if (($estadoActual == Estados::GENERADO || $estadoActual == Estados::EN_BUFFER_CORTE) && $nuevoEstado != Estados::EN_BUFFER) {
            return true;
        } else if ($estadoActual == Estados::EN_BUFFER && ($nuevoEstado != Estados::SUB_ASSY && $nuevoEstado != Estados::COSTURA)) {
            return true;
        }

        return false;


        //Bloque el cambio de estados de acuerdo al estado previo.
        // if ($estadoActual == Estados::GENERADO && $nuevoEstado != Estados::EN_CORTE) {
        //     return true;
        // } else if ($estadoActual == Estados::EN_CORTE && $nuevoEstado != Estados::EN_BUFFER) {
        //     return true;
        // } else if ($estadoActual == Estados::EN_BUFFER && ($nuevoEstado != Estados::SUB_ASSY && $nuevoEstado != Estados::COSTURA)) {
        //     return true;
        // } else if ($estadoActual == Estados::SUB_ASSY && $nuevoEstado != Estados::COSTURA) {
        //     return true;
        // } else if ($estadoActual == Estados::COSTURA && $nuevoEstado != Estados::CALIDAD) {
        //     return true;
        // } else if ($nuevoEstado == Estados::CALIDAD && ($estadoActual == Estados::APROBADO || $estadoActual == Estados::RECHAZADO)) {
        //     return true;
        // }

        // return false;
    }

    public function changeStatus(Request $request) {

        $fuerzaCambio = $request->fuerza;

        try {
            // $kanban = Kanbans::where('codigo', $request->kanban)->first();
            // if (!$kanban) {
            $kanban = Kanban::registrarKanbanSiNoExiste($request->kanban);
            // }
        } catch (\Throwable $th) {
            Log::error('KanbansController::changeStatus - Error al registrar Kanban : ' . $th->getMessage());
            return $this->setResponse([], 'El kanban ingresado no existe. Reintente.', true);
        }

        //Verifico existencia
        if (!$kanban) {
            return $this->setResponse([], "El kanban ingresado no existe", true);
        }

        //Verifico estado actual

        //obtengo el estado
        $estadoKanban = EstadoKanban::with(['estado'])->where('kanban_id', $kanban->id)->first();

        if (!$fuerzaCambio || is_null($fuerzaCambio)) {
            if ($estadoKanban) {
                if ($estadoKanban->estado_id == $request->status) {
                    return $this->setResponse([], "El kanban ingresado ya se encuentra en " . $estadoKanban->estado->descripcion, true);
                }

                if ($this->bloqueaEstado($estadoKanban->estado_id, $request->status)) {
                    if ($estadoKanban->estado_id == Estados::GENERADO && $request->status == Estados::SUB_ASSY) {
                        return $this->setResponse([], "El kanban no se encuentra en Buffer", true);
                    } else {
                        return $this->setResponse([], "No está permitido el cambio de estado. Estado actual : " . $estadoKanban->estado->descripcion, true);
                    }
                }
            }
        }

        //TODO REVISAR
        //POR AHORA, SACO UN KANBAN PARA QUE NO ME QUEDE EN CORTE
        //VERIFICO PRIMERO SI EL KANBAN QUE SOLICITO, NO EXISTE YA CON ESE ESTADO
        if ($estadoKanban) {
            if ($estadoKanban->estado_id != Estados::EN_BUFFER_CORTE) {
                //BUSCO UN KANBAN DEL MISMO MODELO QUE ESTE EN BUFFER DE CORTE
                $estadoKanbanACambiar = EstadoKanban::whereHas('kanban', function ($q) use ($kanban) {
                    $q->where('modelo_id', $kanban->modelo_id);
                })
                    ->where('estado_id', Estados::EN_BUFFER_CORTE)
                    ->first();

                if ($estadoKanbanACambiar) {
                    $kanbanACambiar = Kanbans::where('id', $estadoKanbanACambiar->kanban_id)->first();
                    //FINALIZO EL KANBAN, PARA QUE QUEDE REEMPLAZADO POR EL NUEVO
                    Kanban::changeStatus($kanbanACambiar, Estados::FINALIZADO, $request->linea);
                }
            }
        }

        $grabo = Kanban::changeStatus($kanban, $request->status, $request->linea);

        if ($grabo) {
            return $this->setResponse([], "Actualizado correctamente!");
        } else {
            return $this->setResponse([], "No se pudo actualizar el estado del kanban", true);
        }


        // $posicion = 1;

        // if (!$estadoKanban) {
        //     $linea = intval($request->linea);
        // } else {
        //     // $posicion = intval($estadoKanban->posicion);
        //     if (!$estadoKanban->linea_id) {
        //         $linea = intval($request->linea) > 0 ? intval($request->linea) : null;
        //     } else {
        //         $linea = $estadoKanban->linea_id;
        //     }
        // }

        // //Posicion en buffer por linea
        // $pos = EstadoKanban::where('linea_id', $linea)->where('estado_id', Estados::EN_BUFFER)->orderBy('posicion', 'DESC')->first();
        // if ($pos && $request->status != Estados::SUB_ASSY) {
        //     //busco espacios en el buffer. Por ej:
        //     //Si tengo carro en 1 y en 3, deberia ingresarlo en 2
        //     $posicion = intval($pos->posicion) + 1;
        //     for ($i = 1; $i <= $posicion; $i++) {
        //         $existe = EstadoKanban::where('linea_id', $linea)->where('posicion', intval($i))->where('estado_id', Estados::EN_BUFFER)->orderBy('posicion', 'DESC')->first();
        //         if (!$existe) {
        //             $posicion = $i;
        //             break;
        //         }
        //     }
        // }

        // $data = [
        //     'kanban_id'         => $kanban->id,
        //     'estado_id'         => intval($request->status),
        //     'user_id'           => null, //auth()->guard('api')->user()->id,
        //     'linea_id'          => $linea,
        //     'posicion'          => $posicion,
        //     'estado_previo_id'  => $estadoKanban ? $estadoKanban->estado_id : null
        // ];

        // try {

        //     EstadoKanban::where('kanban_id', $kanban->id)->delete();
        //     if (EstadoKanban::create($data)) {
        //         return $this->setResponse([], "Actualizado correctamente!");
        //     } else {
        //         return $this->setResponse([], "No se pudo actualizar el estado del kanban", true);
        //     }
        // } catch (\Throwable $th) {
        //     Log::error($th->getMessage());
        //     return $this->setResponse([], "Error al actualizar estado de kanban. Comuniquese con el encargado de sistemas.", true);
        // }
    }

    public function storeInSar(Request $request) {
        //CREO EL KANBAN EN LA BASE SAR
        $cantidad = intval($request->hojas) * 4;
        $kanbans = [];

        // Log::alert($request);

        //Por el modelo, averiguo el volumen para saber cuantos debo imprimir
        $modelo = Modelos::where('id', $request['modelo'])->first();

        if (!$modelo) {
            return $this->setResponse([], 'El modelo inidicado no existe', true);
        }

        $cantidad = $modelo->volumen;
        $sets = $modelo->cantidad;
        if (!$sets || $sets == 0) {
            $sets = 10;
        }

        if (!$cantidad || $cantidad == 0) {
            $cantidad = 40;
        }

        $cantidad = $cantidad / $sets;

        for ($i = 0; $i < $cantidad; $i++) {
            $tkanban = new TKanban();
            $newKanban = $tkanban->crear($modelo->nombre);

            // Log::alert($newKanban);

            // if ($newKanban != '') {
            //     $payload = [
            //         'codigo'    => $newKanban,
            //         'modelo_id' => $modelo->id,
            //         'fecha'     => date("Y-m-d"),
            //         'mes'       => substr($newKanban, 3, 2)
            //     ];

            //     $newKanban = Kanbans::create($payload);
            // }

            if ($newKanban) {
                $kanban = Kanbans::with(['modelo'])->where('codigo', $newKanban)->first();
                PcPendienteImpresion::create([
                    'kanban_id'         => $kanban->id,
                    'motivo'            => MotivosReimpresion::PRODUCCION,
                    'tipo'              => 'PRODUCCIÓN',
                    'pendiente'         => false,
                    'fecha_impresion'   => date('Y-m-d')
                ]);

                array_push($kanbans, $kanban);
            }
        }



        // for ($i = 0; $i < $cantidad; $i++) {
        //     try {
        //         $kanban = Kanban::create("P", $request->toArray());

        //         if ($kanban) {
        //             $kanban = Kanbans::with(['modelo'])->where('id', $kanban->id)->first()->toArray();
        //             array_push($kanbans, $kanban);
        //         }
        //     } catch (\Throwable $th) {
        //         Log::error($th->getMessage());
        //         return $this->setResponse([], "Error al crear el kanban. Comuniquese con el encargado de sistemas.", true);
        //     }
        // }

        return $this->setResponse($kanbans);
    }

    public function getReport($kanbanCode) {

        if (!$kanbanCode) {
            return $this->setResponse([], 'Debe informar el kanban', true);
        }

        $response = Kanbans::with(['modelo', 'history.estado', 'estado.estado', 'etiquetasQrGeneradas.userImpresion', 'etiquetasQrValidadas.userValidacion', 'etiquetasQrValidadas.userValidacionCaraB'])->where('codigo', $kanbanCode)->first();


        if ($response) {
            return $this->setResponse($response->toArray());
        } else {
            return $this->setResponse([], 'No hay información del kanban', true);
        }
    }

    public function store(Request $request) {
        $cantidad = intval($request->hojas);
        if ($request->fuerzaCantidad) {
            $cantidad = $request->fuerzaCantidad;
        }

        $kanbans = [];

        for ($i = 0; $i < $cantidad; $i++) {
            try {
                $kanban = Kanban::create("P", $request->toArray());

                if ($kanban) {
                    $kanban = Kanbans::with(['modelo'])->where('id', $kanban->id)->first()->toArray();
                    array_push($kanbans, $kanban);
                }
            } catch (\Throwable $th) {
                Log::error($th->getMessage());
                return $this->setResponse([], "Error al crear el kanban. Comuniquese con el encargado de sistemas.", true);
            }
        }

        return $this->setResponse($kanbans);
    }

    public function show($kanban) {
        // $kanban = str_replace("'", "-", $kanban);
        $existe = Kanbans::with(['estado', 'modelo.lineas'])->where('codigo', $kanban)->first();

        if ($existe) {
            return $this->setResponse($existe->toArray());
        } else {
            return $this->setResponse([], 'El kanban ingresado no existe', true);
        }
    }

    /**
     * Utilizada para verificar si puedo mover un kanban de estado
     */
    public function verificaExistenciaCambioEstado(Request $request) {

        $kanban = $request->kanban;
        $estado = $request->estado;

        $d = Kanban::registrarKanbanSiNoExiste($kanban);

        $existe = Kanbans::with(['estado.estado', 'modelo.lineas'])->where('codigo', $kanban)->first();

        //Corte es el paso inicial. Aca verifico si existe en base remota, y si no, lo tomo
        //De esta manera sigo manteniendo el sistema anterior de generación de kanban

        if (!$existe && $estado == Estados::EN_CORTE) {
            $data = Kanban::getKanbanSAR($kanban);

            if (!$data) {
                return $this->setResponse([], 'El kanban ingresado no existe', true);
            }

            $modelo = Modelos::where('nombre', $data->modelo->MODELO)->first();
            if (!$modelo) {
                return $this->setResponse([], 'El modelo del kanban no existe', true);
            }

            $payload = [
                'codigo'    => $kanban,
                'modelo_id' => $modelo->id,
                'fecha'     => date("Y-m-d"),
                'mes'       => substr($kanban, 3, 2)
            ];

            $newKanban = Kanbans::create($payload);

            if ($newKanban) {
                $existe = Kanbans::with(['estado.estado', 'modelo.lineas'])->where('codigo', $kanban)->first();
            }
        }

        //TODO terminar de agregar estados
        if ($existe) {
            //Verifico estado actual
            if ($existe->estado->estado_id == Estados::SUB_ASSY && $estado == Estados::EN_BUFFER) {
                return $this->setResponse([], 'El kanban solicitado no puede ingresar a BUFFER. Su estado actal es ' . $existe->estado->estado->descripcion, true);
            } else if ($existe->estado->estado_id == Estados::COSTURA && $estado == Estados::EN_BUFFER) {
                return $this->setResponse([], 'El kanban solicitado no puede ingresar a BUFFER. Su estado actal es ' . $existe->estado->estado->descripcion, true);
            } else if ($existe->estado->estado_id == Estados::SUB_ASSY && $estado == Estados::EN_BUFFER) {
                return $this->setResponse([], 'El kanban solicitado no puede ingresar a BUFFER. Su estado actal es ' . $existe->estado->estado->descripcion, true);
            } else if ($existe->estado->estado_id == Estados::GENERADO && $estado != Estados::EN_CORTE) {
                // return $this->setResponse([], 'El kanban solicitado debe ingresar a Corte', true);
            }

            return $this->setResponse($existe->toArray());
        } else {
            return $this->setResponse([], 'El kanban ingresado no existe', true);
        }
    }

    public function getKanbanEndOfLine($kanbanCode) {
        // Log::alert("PASO22");
        //Llamado al escanear kanban en fin de linea para inspección de fallas

        //Verifico existencia Kanban
        $kanban = Kanbans::with(['modelo.fallas.tipo', 'modelo.fallas.lado'])->where('codigo', $kanbanCode)->first();

        if (!$kanban) {
            return $this->setResponse([], "El kanban ingresado no existe", true);
        }

        //Verifico que el estado sea Costura
        // $estadoKanban = EstadoKanban::with(['estado'])->where('kanban_id', $kanban->id)->first();

        // if ($estadoKanban) {
        //     // Log::alert($estadoKanban);
        //     if ($this->bloqueaEstado($estadoKanban->estado_id, Estados::CALIDAD)) {
        //         return $this->setResponse([], "El kanban se encuentra en estado : " . $estadoKanban->estado->descripcion . ". No puede ser analizado en este momento.", true);
        //     } else {
        //         $grabo = Kanban::changeStatus($kanban, Estados::CALIDAD);
        //     }
        // }

        return $this->setResponse($kanban->toArray());
    }

    public function getFallasEtiqueta(Request $request) {

        $etiqueta = EpEtiqueta::with(['reparaciones.imagen', 'reparaciones.tipo', 'reparaciones.falla', 'reparaciones.lado', 'reparaciones.etiqueta'])->where('qr', $request->qr)->first();

        if ($etiqueta) {
            return $this->setResponse($etiqueta->toArray());
        } else {
            return [];
        }
    }

    public function getQrDataEndOfLine(Request $request) {
        $qr = $request->qr;

        $etiqueta = EpEtiqueta::with(['reparaciones.imagen', 'reparaciones.tipo', 'reparaciones.falla', 'reparaciones.lado', 'reparaciones.etiqueta', 'modelod.fallas.tipo', 'modelod.fallas.lado'])->where('qr', $qr)->first();

        // Si NO esta cacheada, resuelvo el QR del kanban contra JITMS (front seat
        // de TBAR). El kanban es dinamico: se resuelve al escanear y queda guardado
        // en EOL_TBAR. De ahi en mas todo es local.
        if (!$etiqueta) {
            $creada = EtiquetaService::creaEtiquetaFrontSeatDesdeJitms($qr);
            if ($creada) {
                $etiqueta = EpEtiqueta::with(['reparaciones.imagen', 'reparaciones.tipo', 'reparaciones.falla', 'reparaciones.lado', 'reparaciones.etiqueta', 'modelod.fallas.tipo', 'modelod.fallas.lado'])->where('qr', $qr)->first();
            }
        } elseif (trim((string) $etiqueta->lado) === '') {
            // Fila cache resuelta antes sin lado (JITMS no lo tenia en ese momento):
            // se completa desde el QR para que la pantalla muestre solo ese lado.
            $ladoQr = EtiquetaService::ladoDesdeQr($qr);
            if ($ladoQr !== '') {
                $etiqueta->lado = $ladoQr;
                $etiqueta->save();
            }
        }

        if (!$etiqueta) {

            if (strlen($qr) < 15) {
                $etiqueta = EtiquetaService::creaEtiquetaAirbagDesdeBaseSAR($qr, null);

                if ($etiqueta) {
                    $etiqueta = EpEtiqueta::with(['reparaciones.imagen', 'reparaciones.tipo', 'reparaciones.falla', 'reparaciones.lado', 'reparaciones.etiqueta', 'modelod.fallas.tipo', 'modelod.fallas.lado'])->where('qr', $qr)->first();
                }
            } else {
                //SI NO EXISTE, LA INTERPRETO DE ACUERDO A LOS DIGITOS
                $codigo = substr($qr, 13, 14);
                $secuencia = substr($qr, 9, 4);
                $ubicacion = substr($qr, 27, 2);
                $lado = substr($qr, 29, 2);
                $tipo = substr($qr, 31, 2);
                $linea = substr($qr, 33, 1);

                $parte = Partes::with('modelo')->where('codigo', $codigo)->first();

                if ($parte) {
                    try {
                        $modelo = $parte->modelo[0]->nombre;
                        // Log::alert($parte->modelo[0]);

                        EpEtiqueta::create([
                            'id_etiqueta'   => 0,
                            'modelo'        => $modelo,
                            'lado'          => $lado,
                            'secuencia'     => $secuencia,
                            'estado'        => 'RETRABAJO',
                            'qr'            => $qr,
                            'ubicacion'     => $ubicacion,
                            'tipo'          => $tipo,
                            'vehiculo'      => '',
                            'codigo'        => $codigo,
                            'linea'         => $linea,
                            'linea_real'    => $linea
                        ]);

                        $mod = Modelos::where('nombre', $modelo)->first();

                        EpEtiquetaMid::create([
                            'qr'            => $qr,
                            'modelo_id'     => $mod?->id,
                            'linea_real'    => $linea
                        ]);

                        $etiqueta = EpEtiqueta::with(['reparaciones.imagen', 'reparaciones.tipo', 'reparaciones.falla', 'reparaciones.lado', 'reparaciones.etiqueta', 'modelod.fallas.tipo', 'modelod.fallas.lado'])->where('qr', $qr)->first();
                    } catch (\Throwable $th) {
                        //throw $th;
                        Log::alert("KanbansController::getQrDataEndOfLine : " . $th->getMessage());
                    }
                }
            }
        }

        if (!$etiqueta) {
            return $this->setResponse([], "La etiqueta escaneada no existe", true);
        }

        //VERIFICO SI NO SE VALIDO YA
        if ($etiqueta->estado == EstadosQr::QCP_OK) {
            return $this->setResponse([], "La etiqueta escaneada ya fue validada por QC", true);
        }

        //VERIFICO SI LA FUNDA SE ESCRAPEO
        if ($etiqueta->estado == EstadosQr::SCRAP) {
            return $this->setResponse([], "La etiqueta escaneada se informo como scrap", true);
        }

        return $this->setResponse($etiqueta->toArray());
    }

    public function getHistory($kanbanCode) {

        $data = Kanbans::with(['modelo', 'history.estado',])
            ->where('codigo', $kanbanCode)->first();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function corrijeKanbanBuffer(Request $request) {

        $kanbans = $request->items;
        $linea = $request->linea;

        //Borro el buffer de la linea
        EstadoKanban::where('linea_id', $linea)->where('estado_id', Estados::EN_BUFFER)->update(['estado_id' => Estados::GENERADO]);

        foreach ($kanbans as $k) {
            $kanban = Kanbans::where('codigo', $k)->first();

            if (!$kanban) {
                $kanban = Kanban::registrarKanbanSiNoExiste($k);
            }
            if ($kanban) {

                $linea = ModeloLinea::where('modelo_id', $kanban->modelo_id)->first();

                $existeEstado = EstadoKanban::where('kanban_id', $kanban->id)->first();
                if ($existeEstado) {
                    EstadoKanban::where('kanban_id', $kanban->id)->update([
                        'estado_id' => Estados::EN_BUFFER,
                        'linea_id'  => $existeEstado->linea_id ? $existeEstado->linea_id : $linea->linea_id
                    ]);
                } else {
                    EstadoKanban::create([
                        'kanban_id' => $kanban->id,
                        'linea_id'  => $linea->linea_id,
                        'estado_id' => Estados::EN_BUFFER,
                        'user_id'   => null,
                    ]);
                }
            }
        }

        return $this->setResponse([], 'Cargado correctamente');
    }

    public function getKanbanLastIn() {
        $modelos = [];
        try {

            $data = EstadoKanban::with('kanban.modelo')
                ->where(function ($q) {
                    $date = date('Y-m-d H:i:s');

                    $dateFrom = strtotime('-100 minute', strtotime($date));
                    $dateFrom = date('Y-m-d H:i:s', $dateFrom);

                    $q->where('updated_at', '<=', $date);
                    $q->where('updated_at', '>=', $dateFrom);
                })
                ->where('estado_id', Estados::EN_BUFFER)
                // ->where('linea_id', '<>', 11)
                ->orderBy('updated_at', 'DESC')
                ->get();

            // Log::alert(json_encode($data, JSON_PRETTY_PRINT));
            if ($data) {
                foreach ($data as $d) {
                    $existe = false;
                    foreach ($modelos as &$m) {
                        if ($m['modelo'] == $d->kanban->modelo->nombre) {
                            // Log::alert("PASO");
                            $m['cantidad'] = intval($m['cantidad']) + 1;
                            $existe = true;
                            break;
                        }
                    }

                    if (!$existe) {
                        array_push($modelos, ['modelo' => $d->kanban->modelo->nombre, 'cantidad' => 1]);
                    }
                }

                return $this->setResponse($modelos);
            }
        } catch (\Throwable $th) {
            Log::error("KanbansController::getKanbanLasOut " . $th->getMessage());
        }

        return $this->setResponse([]);
    }

    public function getKanbanLastOut() {
        $modelos = [];
        try {

            $data = EstadoKanban::with('kanban.modelo')
                // ->where(function ($q) {
                //     $date = date('Y-m-d H:i:s');
                //     $dateFrom = strtotime('-1 hour', strtotime($date));
                //     $dateFrom = date('Y-m-d H:i:s', $dateFrom);
                //     $q->where('created_at', '>=', $dateFrom);
                //     $q->where('created_at', '<=', $date);
                // })
                ->where('estado_previo_id', Estados::EN_BUFFER)
                ->where(function ($q) {
                    $q->where('estado_id', Estados::COSTURA);
                    $q->orWhere('estado_id', Estados::SUB_ASSY);
                })
                ->orderBy('updated_at', 'DESC')->get()->take(2);

            // Log::alert($data);

            if ($data) {
                foreach ($data as $d) {
                    $existe = false;
                    foreach ($modelos as &$m) {
                        if ($m['modelo'] == $d->kanban->modelo->nombre) {

                            $m['cantidad'] = intval($m['cantidad']) + 1;
                            $existe = true;
                            break;
                        }
                    }

                    if (!$existe) {
                        array_push($modelos, ['modelo' => $d->kanban->modelo->nombre, 'cantidad' => 1]);
                    }
                }

                return $this->setResponse($modelos);
            }
        } catch (\Throwable $th) {
            Log::error("KanbansController::getKanbanLasOut " . $th->getMessage());
        }

        return $this->setResponse([]);
    }

    static function fixKanbanModelo($k) {
        if (substr($k->codigo, 0, 1) == 'P') {
            $kSar = Kanban::getKanbanSAR(ltrim(rtrim($k->codigo)));
            if ($kSar) {
                if (!is_null($kSar->modelo)) {
                    $modelo = Modelos::where('nombre', ltrim(rtrim($kSar->modelo->NOMBRE)))->first();
                    if ($modelo) {
                        Kanbans::where('id', $k->id)->update([
                            'modelo_id' => $modelo->id
                        ]);
                    } else {
                        Log::alert("NO EXISTE EL MODELO : " . ltrim(rtrim($kSar->modelo->NOMBRE)));
                    }
                } else {
                    Log::alert("NO TIENE MODELO EL KANBAN : " . $k->codigo);
                }
            } else {
                Log::alert($kSar);
                Log::alert("NO EXISTE EL KANBAN : " . $k->codigo);
            }
        }
    }

    public function fixKanbanSinModelo() {
        $kanbans = Kanbans::where('modelo_id', null)->get();

        foreach ($kanbans as $k) {
            // $this->fixKanbanModelo($k);
            Kanbans::fixKanbanModelo($k);
        }

        return 'OK';
    }

    public function altaFinishGood() {
        $idFG = Configuracion::where('clave', 'id_actualizacion_fg')->first();
        $lastId = $idFG->valor;

        //RECORRO LOS KANBAN DE FG PENDIENTES
        $kanbanPendientes = TRegistrosKanban::select('N_KANBAN', 'ID', 'FECHA', 'HORA')->where('ACCION', 'FINISH GOOD SEAT')
            ->where('ID', '>=', intval($idFG->valor))
            ->take(500)
            ->get();

        try {
            foreach ($kanbanPendientes as $kanbanPendiente) {

                $movimiento = null;
                $kanban = Kanban::registrarKanbanSiNoExiste($kanbanPendiente->N_KANBAN);

                if (is_null($kanban->modelo)) {
                    $this->fixKanbanModelo($kanban);
                    $kanban = Kanbans::with('modelo')->where('codigo', $kanban->codigo)->first();
                }

                $existe = MovimientosContenido::where('ref', $kanbanPendiente->N_KANBAN)->first();

                $ingresaADolly = false;
                $depositoDestino = null;
                $ubicacionDestino = null;

                //IDENTIFICO EL MODELO, SI ES DE M1, M2 o M11, entonces ingresa a dolly, si no a temporal
                $lineasModelo = ModeloLinea::where('modelo_id', $kanban->modelo_id)
                    ->where(function ($q) {
                        $q->where('linea_id', 1);
                        $q->orWhere('linea_id', 2);
                        $q->orWhere('linea_id', 11);
                    })->first();

                $ingresaADolly = $lineasModelo ? true : false;
                // $ingresaADolly = true; // $lineasModelo ? true : false;

                if ($ingresaADolly) {
                    $depositoDestino = Depositos::DOLLYS;
                } else {
                    $depositoDestino = Depositos::TEMPORAL_A;
                }
                $ubicacionDestino = Ubicaciones::select('id')->where('deposito_id', $depositoDestino)->first();


                if (!$existe) {

                    //CREO MOVIMIENTO
                    $movimiento = Movimientos::create([
                        'unidad_id'     => WmsUnidades::KANBAN,
                        'ubicacion_id'  => $ubicacionDestino->id,
                        'finalizado'    => 0,
                    ]);

                    if ($movimiento) {
                        MovimientosContenido::create([
                            'movimiento_id'     => $movimiento->id,
                            'ref'               => $kanbanPendiente->N_KANBAN,
                            'cantidad'          => 1,
                            'ubicacion_id'      => $ubicacionDestino->id,
                            'unidad_id'         => WmsUnidades::KANBAN
                        ]);
                    }
                }

                $fecha = DateTime::createFromFormat('Y-m-d H:i:s.u', $kanbanPendiente->FECHA);
                $hora = DateTime::createFromFormat('Y-m-d H:i:s.u', $kanbanPendiente->HORA);
                $fechaKanban = DateTime::createFromFormat('Y-m-d H:i:s', $fecha->format('Y-m-d') . ' ' . $hora->format('H:i:s'));
                // Log::alert($fechaKanban->format('Y-m-d H:i:s'));

                // LogFg::create([
                //     'kanban'    => $kanban->codigo,
                //     'fecha'     => $fechaKanban->format('Y-m-d H:i:s') // date('Y-m-d H:i:s')
                // ]);

                LogFg::updateOrCreate(
                    [
                        'kanban'    => $kanban->codigo
                    ],
                    [
                        'kanban'    => $kanban->codigo,
                        'fecha'     => $fechaKanban->format('Y-m-d H:i:s') // date('Y-m-d H:i:s')
                    ]
                );

                $estado = EstadoKanban::where('kanban_id', $kanban->id)->first();

                if (!$estado) {
                    EstadoKanban::create([
                        'kanban_id' => $kanban->id,
                        'estado_id' => 6,
                        'user_id'   => 1,
                    ]);
                } else {
                    EstadoKanban::where('id', $estado->id)->update([
                        'estado_id'         => 6,
                        'estado_previo_id'  => $estado->estado_id
                    ]);
                }

                //VERIFICO PENDIENTE CAJA PRODUCCION
                if ($movimiento) {

                    //Obtengo el despacho abierto
                    $despacho = Despachos::select('id')
                        ->where('pendiente', true)
                        ->whereHas('items', function ($q) use ($kanban) {
                            $q->where('produccion', true);
                            $q->where('deposito_id', null);
                            $q->where('modelo', $kanban->modelo->nombre);
                            $q->where(function ($r) {
                                $r->where('pickeado', 0);
                                $r->orWhere('pickeado', null);
                                $r->orWhere('pickeado', false);
                            });
                        })->orderBy('id', 'ASC')
                        ->first();


                    if ($despacho) {
                        $pendiente = DespachosItems::select('id')->where('pickeado', false)->where('deposito_id', null)->where('despacho_id', $despacho->id)->where('produccion', true)->where('kanban', null)->where('modelo', $kanban->modelo->nombre)->orderBy('id', 'DESC')->first();
                        if ($pendiente) {
                            DespachosItems::where('id', $pendiente->id)->update(['deposito_id' => $depositoDestino]);
                        }
                    }
                }
                $lastId = $kanbanPendiente->ID;
            }

            Configuracion::where('clave', 'id_actualizacion_fg')->update(['valor' => $lastId]);
        } catch (\Throwable $th) {
            Log::error("AltaFInishGood : " . $th->getMessage());
            return null;
        }
    }
}
