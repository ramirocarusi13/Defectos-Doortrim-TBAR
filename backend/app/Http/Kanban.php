<?php

namespace App\Http;

use App\Models\EstadoKanban;
use App\Models\Kanbans;
use App\Models\KanbansReemplazo;
use App\Models\ModeloLinea;
use App\Models\Modelos;
use App\Models\PcPendienteImpresion;
use App\Models\PcPlanProduccion;
use App\Models\Sar\TKanban;
use App\Models\StockProductoFinal;
use Illuminate\Support\Facades\Log;

class Kanban {

    /**
     * Verifica si el kanban existe en base. Si no existe, lo busca en SAR y lo da de alta.
     */
    static function registrarKanbanSiNoExiste($kanbanCode) {

        $existe = Kanbans::with('modelo')->where('codigo', $kanbanCode)->first();
        if ($existe) {
            return $existe;
        }

        $data = Kanban::getKanbanSAR($kanbanCode);

        if (!$data) {
            // Log::alert("Kanban::registrarKanbanSiNoExiste : " . $kanbanCode . " - El kanban ingresado no existe");
            throw new \Exception("El kanban ingresado no existe", 1);
        }

        $modelo = Modelos::where('nombre', trim($data->modelo->NOMBRE))->first();
        if (!$modelo) {
            throw new \Exception("El modelo del kanban no existe", 1);
        }

        $ano = substr($kanbanCode, 1, 2);
        $mes = substr($kanbanCode, 3, 2);
        $dia = substr($kanbanCode, 5, 2);

        $fecha = date('Y-m-d', strtotime($ano . '-' . $mes . '-' . $dia));

        $payload = [
            'codigo'    => strtoupper($kanbanCode),
            'modelo_id' => $modelo->id,
            'fecha'     => $fecha, //date("Y-m-d"),
            'mes'       => substr($kanbanCode, 3, 2)
        ];

        // Log::alert($payload);

        $newKanban = Kanbans::create($payload);

        $existe = Kanbans::with('modelo')->where('codigo', $kanbanCode)->first();

        return $existe;
    }

    static function agregarDatosLoteKanban(string $codigoKanban, $lote, $secuencia, $cantidad, $linea) {
        Kanbans::where('codigo', $codigoKanban)
            ->update([
                'linea'     => $linea,
                'secuencia' => $secuencia,
                'cantidad'  => $cantidad,
                'lote'      => $lote
            ]);
    }

    static function create($tipo = 'P', $payload = []) {

        if ($tipo == '' || !$tipo) {
            $tipo = "P";
        }

        $now = \DateTime::createFromFormat('U.u', microtime(true));
        $local = $now->setTimezone(new \DateTimeZone('America/Argentina/Buenos_Aires'));

        $anioPayload = $payload['ano'] ?? $payload['anio'] ?? $payload['año'] ?? $payload['year'] ?? null;
        $mesPayload = $payload['mes'] ?? $payload['month'] ?? null;

        $anioCodigo = $local->format('y');
        if (!is_null($anioPayload) && $anioPayload !== '') {
            $anioCodigo = str_pad(substr((string) intval($anioPayload), -2), 2, '0', STR_PAD_LEFT);
        }

        $mesCodigo = $local->format('m');
        if (!is_null($mesPayload) && $mesPayload !== '') {
            $mes = intval($mesPayload);
            if ($mes >= 1 && $mes <= 12) {
                $mesCodigo = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
            }
        }

        $codigo = strtoupper($tipo) . $anioCodigo . $mesCodigo . $local->format("dHisv");

        $data = [
            'codigo'    => strtoupper($codigo),
            'modelo_id' => $payload['modelo'],
            'fecha'     => date("Y-m-d"),
            'mes'       => $mesPayload,
        ];

        // Log::alert($data);

        $kanban = Kanbans::create($data);

        if ($tipo == 'R') {
            //Reemplazo
            $data = [
                'kanban_id' => $kanban->id,
                'pieza_id'  => $payload['pieza'],
                'capas'     => $payload['capas'],
            ];

            KanbansReemplazo::create($data);
        }

        return $kanban;
    }

    static function exists($codigo) {

        $codigo = str_replace("'", "-", $codigo);
        $kanban = Kanbans::with(['modelo.partes.piezas', 'estado', 'reemplazo.pieza' => function ($q) {
            $q->withSum('stockTienda', 'cantidad');
        }])
            ->where('codigo', $codigo)
            ->first();

        return $kanban;
    }

    static function getKanbanSAR($codigoKanban) {
        $data = TKanban::with(['modelo'])->where('N_KANBAN', $codigoKanban)->first();
        return $data;
    }

    static function changeStatus($kanban, $status = null, $lineaR = null, $lectra = null): bool {

        $posicion = 1;
        $estadoKanban = EstadoKanban::with(['estado'])->where('kanban_id', $kanban->id)->first();
        $linea = null;

        if ($estadoKanban) {
            if (!$estadoKanban->linea_id) {
                $linea = intval($lineaR) > 0 ? intval($lineaR) : null;
            } else {
                $linea = $estadoKanban->linea_id;
            }
        }

        //VERIFICO SI TIENE MODELO, SI NO LO BUSCO
        $kan = Kanbans::with('modelo')->where('id', $kanban->id)->first();
        if ($kan) {
            if (is_null($kan->modelo)) {
                $kanbanSar = Kanban::getKanbanSAR($kan->codigo);
                $modelo = Modelos::where('nombre', trim($kanbanSar->modelo->NOMBRE))->first();

                if ($modelo) {
                    Kanbans::where('id', $kan->id)->update(['modelo_id' => $modelo->id]);
                    $kan = Kanbans::with('modelo')->where('id', $kanban->id)->first();
                }
            }
        }

        if ($linea == null) {
            //Busco primero la linea del buffer
            if ($kan) {
                if (is_null($kan->modelo)) {
                    $modelo = Modelos::where('nombre', trim($kanbanSar->modelo->NOMBRE))->first();
                    if ($modelo) {
                        $linea = $modelo->linea_buffer;
                    }
                }
            }

            //Busco la linea en el modelo
            // $k = Kanbans::with('modelo')->where('id', $kanban->id)->first();
            if (!$linea) {
                if ($kan) {
                    $l = ModeloLinea::where('modelo_id', $kan->modelo->id)->first();
                    if ($l) {
                        $linea = $l->linea_id;
                    }
                }
            }
        }

        // $pos = EstadoKanban::where('linea_id', $linea)->where('estado_id', Estados::EN_BUFFER)->orderBy('posicion', 'DESC')->first();
        // if ($pos && $status != Estados::SUB_ASSY) {
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

        $data = [
            'kanban_id'         => $kanban->id,
            'estado_id'         => intval($status),
            'user_id'           => 1, //auth()->guard('api')->user()->id,//TODO
            'linea_id'          => $linea,
            'posicion'          => $posicion,
            'estado_previo_id'  => $estadoKanban ? $estadoKanban->estado_id : null,
            'lectra'            => $lectra
        ];

        try {
            $res = EstadoKanban::updateOrCreate(
                ['kanban_id' => $kanban->id],
                $data
            );

            if ($res) {
                return true;
            } else {
                return false;
            }
            // EstadoKanban::where('kanban_id', $kanban->id)->delete();
            // if (EstadoKanban::create($data)) {
            //     return true;
            // } else {
            //     return false;
            // }
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return false;
        }
    }

    static function getKanbanPendienteCorte($modelo) {

        $data = Kanbans::where('modelo_id', $modelo->id)
            ->whereHas('estado', function ($q) {
                $q->where('estado_id', Estados::EN_CORTE);
                $q->orWhere('estado_id', Estados::EN_BUFFER_CORTE);
            })
            ->get();

        if ($data) {
            return $data->toArray();
        } else {
            return [];
        }
    }

    static function getKanbanPendienteImpresion($modelo) {
        return PcPendienteImpresion::whereHas('kanban.modelo', function ($q) use ($modelo) {
            $q->where('id', $modelo->id);
        })->where('pendiente', 1)->get()->toArray();
    }

    static function getKanbanEnProceso($modelo) {
        return PcPendienteImpresion::whereHas('kanban.modelo', function ($q) use ($modelo) {
            $q->where('id', $modelo->id);
        })
            ->whereHas('kanban.estado', function ($q) {
                $q->where('estado_id', Estados::GENERADO);
                $q->orWhere('estado_id', Estados::EN_CORTE);
                $q->orWhere('estado_id', Estados::PLANIFICADO);
            })->where('pendiente', 0)->get()->toArray();
    }

    static function getKanbanManuales($modelo) {
        return Kanbans::whereHas('modelo', function ($q) use ($modelo) {
            $q->where('id', $modelo->id);
        })
            ->whereHas('estado', function ($q) {
                $q->where('estado_id', Estados::GENERADO);
                $q->orWhere('estado_id', Estados::EN_CORTE);
                $q->orWhere('estado_id', Estados::PLANIFICADO);
            })->get()->toArray();
    }

    static function getKanbanEnviados($modelo) {
        return StockProductoFinal::where('fecha_egreso', date('Y-m-d'))->where('egresado', 1)
            ->where('modelo', $modelo->nombre)->get()->toArray();
    }

    static function getStockRealFinalizado($modelo) {
        return StockProductoFinal::where('egresado', 0)
            ->where('modelo', $modelo->nombre)->get()->toArray();
    }

    static function verificaReposicion($kanban, $linea = null) {
        //Verifico segun el punto de pedido del modelo si corresponde o no enviar a corte
        $modelo = Modelos::withCount('enBuffer')->where('id', $kanban->modelo_id)->first();
        $enBuffer = $modelo->enBuffer;
        $ptoPedidoBuffer = intval($modelo->ptopedido_buffer);
        $cantidadSetsModelo = intval($modelo->cantidad);

        if ($ptoPedidoBuffer <= 0 || $cantidadSetsModelo <= 0) {
            return;
        }

        //Verifico si el stock es menor al minimo
        $stockBufffer = count($enBuffer) * $cantidadSetsModelo;
        $ptoPedidoBuffer = $ptoPedidoBuffer * $cantidadSetsModelo;
        $pendienteStock = Kanban::getKanbanPendienteImpresion($modelo);

        //Si tengo pendientes de impresión, no vuelvo a generar
        if (count($pendienteStock) > 0) {
            return;
        }

        //SI el stock de buffer es mayor o igual al pto pedido, no hago nada
        if ($stockBufffer > $ptoPedidoBuffer) {
            return;
        }

        //Si no, generó tantos kanbans como sean necesarios para generar el corte por el volumen
        if (intval($modelo->volumen) <= 0) {
            return;
        }

        //Verifico si no hay cortes en proceso


        $volumenCorte = intval($modelo->volumen);

        for ($i = 1; $i <= ($volumenCorte / $cantidadSetsModelo); $i++) {
            //Lo creo en SAR
            $tkanban = new TKanban();
            $newKanban = $tkanban->crear($modelo->nombre);

            if ($newKanban) {
                $newKanban = Kanbans::where('codigo', $newKanban)->first();

                if ($newKanban) {
                    if ($linea) {
                        EstadoKanban::where('kanban_id', $newKanban->id)->update(['linea_id' => $linea]);
                    }

                    PcPendienteImpresion::create([
                        'kanban_id' => $newKanban->id,
                        'motivo'    => MotivosReimpresion::ABASTECIMIENTO_BUFFER,
                        'tipo'      => 'PRODUCCIÓN',
                        'pendiente' => true
                    ]);
                }
            }
        }
    }

    static function verificaReposicion2($kanban, $linea = null) {

        // $consumoDiario = 227;

        $today = date('Y-m-d');
        $plan = PcPlanProduccion::where(function ($q) use ($today) {
            $q->where('vigencia_desde', '<=', $today)
                ->where('vigencia_hasta', '>=', $today);
        })->where('modelo_id', $kanban->modelo_id)->first();

        if (!$plan) {
            return;
        }

        if ($plan->consumo == 0) {
            return;
        }

        $consumoDiario = intval($plan->consumo) / 5; //Suponiendo que el plan es semanal

        $cantidadCortes = 0;
        $minimo = $consumoDiario * 3;
        $maximo = $consumoDiario * 6;
        $stockActual = 0;
        $stockIdeal = ceil(($minimo + $maximo) / 2);
        $requeridoCorte = 0;

        // $modelo = Modelos::withCount('enBuffer')->where('nombre', 'SFLE')->first();
        $modelo = Modelos::withCount('enBuffer')->where('id', $kanban->modelo_id)->first();

        if (!$modelo) {
            return;
        }

        $cantidadSetsModelo = intval($modelo->cantidad);
        $volumenCorte = intval($modelo->volumen); //Volumen de sets por corte

        //Genero los kanban a imprimir por PC para reposición
        $enviados = Kanban::getKanbanEnviados($modelo);
        $enBuffer = $modelo->enBuffer;

        //Kanbans generados pero que quedaron pendientes de planificacion de PC
        $pendiente = Kanban::getKanbanPendienteImpresion($modelo);

        //Cantidad en proceso (Corte)
        $enProceso = Kanban::getKanbanEnProceso($modelo);

        //Kanbans generados no pendientes de impresion (generación manual)
        $kanbansManuales = Kanban::getKanbanManuales($modelo);

        $productosTerminadosStock = Kanban::getStockRealFinalizado($modelo);

        $stockActual = (count($productosTerminadosStock) + count($enBuffer) + count($pendiente) + count($kanbansManuales) + count($enProceso) - count($enviados)) * $cantidadSetsModelo; // Cantidad en sets

        if ($stockIdeal < 0) {
            $requeridoCorte = 0;
        } else {
            $requeridoCorte = $stockIdeal - $stockActual;
        }

        if ($requeridoCorte > 0 && $volumenCorte > 0) {
            $cantidadCortes = ceil($requeridoCorte / $volumenCorte);
        }

        $stockAlCortar = $stockActual + ($cantidadCortes * $volumenCorte);

        //Divido el stock a cortar en la cantidad de sets por kanban para saber cuantos kanbans tengo que generar
        for ($i = 0; $i < ($stockAlCortar / $cantidadSetsModelo); $i++) {
            //Lo creo en SAR
            $tkanban = new TKanban();
            $newKanban = $tkanban->crear($modelo->nombre);

            //Si lo creo, lo registro en base
            if ($newKanban != '') {
                $payload = [
                    'codigo'    => strtoupper($newKanban),
                    'modelo_id' => $modelo->id,
                    'fecha'     => date("Y-m-d"),
                    'mes'       => substr($newKanban, 3, 2)
                ];

                $newKanban = Kanbans::create($payload);
            }

            if ($newKanban) {
                if ($linea) {
                    EstadoKanban::where('kanban_id', $newKanban->id)->update(['linea_id' => $linea]);
                }

                PcPendienteImpresion::create([
                    'kanban_id' => $newKanban->id,
                    'motivo'    => MotivosReimpresion::ABASTECIMIENTO_BUFFER,
                    'tipo'      => 'PRODUCCIÓN',
                    'pendiente' => true
                ]);
            }
        }
    }
}
