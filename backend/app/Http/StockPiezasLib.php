<?php

namespace App\Http;

use App\Models\Kanbans;
use App\Models\KanbansReemplazo;
use App\Models\Piezas;
use App\Models\StockPiezas;
use Illuminate\Support\Facades\Log;
use App\Http\PdfKanban;
use App\Models\FilePrint;
use App\Models\PcPendienteImpresion;

class StockPiezasLib {

    static function existeKanbanReemplazoPendienteIngreso(int $piezaId): bool {
        return KanbansReemplazo::where('pieza_id', $piezaId)
            ->whereNull('fecha_ingreso')
            ->exists();
    }

    static function crearStockEnCorte($kanbanId) {

        try {
            $kanban = Kanbans::with(['modelo.partes'])->where('id', $kanbanId)->first();

            foreach ($kanban->modelo->partes as $parte) {
                $piezas = Piezas::where('parte_id', $parte->id)->get();
                // Log::alert($piezas);
                foreach ($piezas as $pieza) {
                    $data = [
                        'pieza_id'      => $pieza->id,
                        'cantidad'      => floatval($kanban->modelo->cantidad),
                        'fecha'         => date("Y-m-d"),
                        'kanban_id'     => $kanban->id,
                        'user_id'       => auth()->guard('api')->user()->id,
                        'deposito_id'   => Depositos::CORTE,
                        "motivo"        => MotivosStock::INGRESO_CORTE
                    ];

                    StockPiezas::create($data);
                }
            }
        } catch (\Throwable $th) {
            Log::error("StockPiezasLib::crearStockEnCorte : " . $th->getMessage());
            throw $th;
        }
    }

    static function generarMovimiento($piezaId, $cantidad, $kanban, $depositoId, $motivo = null, $userid = null, bool $controlarMinimoTienda = true) {

        // $tipoKanban = substr($kanban->codigo, 0, 1);
        // $kanbanId = $kanban->id;

        try {
            $data = [
                'pieza_id'              => $piezaId,
                'cantidad'              => $cantidad,
                'fecha'                 => date("Y-m-d"),
                'kanban_id'             => $kanban ?  $kanban->id : null,
                // 'kanban_reemplazo_id'   => $tipoKanban == 'R' ? $kanbanId : null,
                'user_id'               => $userid,
                'deposito_id'           => $depositoId,
                'motivo'                => $motivo
            ];

            $creo = StockPiezas::create($data);

            if ($creo && $controlarMinimoTienda && $depositoId == Depositos::TIENDA && $cantidad < 0) {
                StockPiezasLib::controlaMinimoPiezaEnTienda($piezaId);
            }
        } catch (\Throwable $th) {
            Log::error("StockPiezasLib::generarMovimiento : " . $th->getMessage());
            // Log::error($data);
            throw $th;
        }
    }

    static function transferenciaEntreDepositos($kanbanId, $depositoInId, $depositoOutId) {

        try {
            $kanban = Kanbans::with(['modelo.partes'])->where('id', $kanbanId)->first();

            //Tomo las piezas del deposito cargado
            //Filtrar solo piezas positivas
            $stockPiezas = StockPiezas::where('kanban_id', $kanbanId)
                ->where('deposito_id', $depositoOutId)
                ->where('cantidad', '>', 0)
                ->get();

            foreach ($stockPiezas as $stock) {
                StockPiezasLib::generarMovimiento($stock->pieza_id, $stock->cantidad * -1, $kanban, $depositoOutId, MotivosStock::EGRESO_POR_KANBAN);
                StockPiezasLib::generarMovimiento($stock->pieza_id, $stock->cantidad, $kanban, $depositoInId, MotivosStock::INGRESO_POR_KANBAN);
            }
        } catch (\Throwable $th) {
            Log::error("StockPiezasLib::transferenciaEntreDepositos : " . $th->getMessage());
            throw $th;
        }
    }

    static function transferenciaPiezasEntreDepositos($piezas, $depositoInId, $depositoOutId, $kanban, $motivo = null) {
        // $piezas = Array de Id de piezas
        try {
            foreach ($piezas as $pieza) {
                // Log::alert("Deposito Out: " . $depositoOutId . " - Deposito In: " . $depositoInId . " - Pieza ID: " . $pieza['id']);
                StockPiezasLib::generarMovimiento($pieza['id'], intval($pieza['cantidad']) * -1, $kanban, $depositoOutId, $motivo);
                StockPiezasLib::generarMovimiento($pieza['id'], intval($pieza['cantidad']), $kanban, $depositoInId, $motivo);
            }
        } catch (\Throwable $th) {
            Log::error("StockPiezasLib::transferenciaPiezasEntreDepositos : " . $th->getMessage());
            throw $th;
        }
    }

    static function ingresoPiezasDepositoPorKanban($piezas, $deposito, $kanban, $motivo = null) {
        try {
            foreach ($piezas as $pieza) {
                StockPiezasLib::generarMovimiento($pieza['id'], 1, $kanban, $deposito, $motivo);
            }
        } catch (\Throwable $th) {
            Log::error("StockPiezasLib::ingresoPiezasDepositoPorKanban : " . $th->getMessage());
            throw $th;
        }
    }

    static function ingresoPiezasDeposito($piezas, $deposito, $motivo = null) {
        try {
            foreach ($piezas as $pieza) {
                StockPiezasLib::generarMovimiento($pieza['id'],  intval($pieza['cantidad']), null, $deposito, $motivo);
            }
        } catch (\Throwable $th) {
            Log::error("StockPiezasLib::ingresoPiezasDepositoPorKanban : " . $th->getMessage());
            throw $th;
        }
    }

    static function controlaMinimoPiezaEnTienda(int $piezaId) {
        //Esta función se encarga de revisar el minimo en tienda para verificar si necesita reposición
        //En caso de necesitarlo, envia a PC la orden para imprimir el kanban de reemplazo

        $kanban = StockPiezasLib::verificarPiezasReemplazoTienda($piezaId);

        if (!$kanban) {
            return;
        }

        PcPendienteImpresion::create([
            'kanban_id' => $kanban->id,
            'motivo'    => MotivosReimpresion::ABASTECIMIENTO_TIENDA_MINIMO,
            'tipo'      => 'REEMPLAZO',
            'pendiente' => true
        ]);
    }

    static function controlaPuntoOptimoPiezasEgresadasTienda(array $piezaIds) {
        $piezaIds = array_values(array_unique(array_filter(array_map('intval', $piezaIds))));

        foreach ($piezaIds as $piezaId) {
            $kanban = StockPiezasLib::verificarPiezasCortePorPuntoOptimoTienda($piezaId);

            if (!$kanban) {
                continue;
            }

            PcPendienteImpresion::create([
                'kanban_id' => $kanban->id,
                'motivo'    => MotivosReimpresion::ABASTECIMIENTO_TIENDA,
                'tipo'      => 'REEMPLAZO',
                'pendiente' => true
            ]);
        }
    }

    static function verificarPiezasCortePorPuntoOptimoTienda(int $piezaId) {
        $pieza = Piezas::with(['modelo'])
            ->withSum('stockTienda', 'cantidad')
            ->where('id', $piezaId)
            ->first();

        if (!$pieza) {
            return null;
        }

        $ptoOptimo = intval($pieza->pto_optimo ?? 0);
        $minimo = intval($pieza->minimo ?? 0);
        $maximo = intval($pieza->maximo ?? 0);
        $stockActual = intval($pieza->stock_tienda_sum_cantidad ?? 0);
        $reponer = $maximo - $stockActual;

        if ($ptoOptimo <= 0 || !$pieza->kanban_reposicion || $stockActual > $minimo || $reponer <= 0) {
            return null;
        }

        if (StockPiezasLib::existeKanbanReemplazoPendienteIngreso($pieza->id)) {
            return null;
        }

        $capas = intval(ceil($reponer / $ptoOptimo));

        if ($capas > 20) {
            $capas = 20;
        }

        $pdfKanban = new PdfKanban($pieza->id, $pieza->modelo->nombre, $pieza->kanban_reposicion, $capas);
        $kanban = Kanban::create("R", ['modelo' => $pdfKanban->modelo->id, 'pieza' => $pdfKanban->pieza->id, 'capas' => $capas, 'mes' => null]);

        if (!$kanban) {
            return null;
        }

        $pdfKanban->setKanban($kanban);
        $pdfKanban->generate(true, $pieza->id . ".pdf");

        FilePrint::create([
            'file_name' => $pieza->id . ".pdf",
            'file_path' => public_path('pdf/' . $pieza->id . ".pdf"),
            'printed'   => false
        ]);

        return $kanban;
    }

    static function verificarPiezasReemplazoTienda($piezaId = null) {

        //Recorro las piezas que esten con stock debajo o igual al minimo y hayan llegado a su punto optimo de corte.
        $files = [];

        if ($piezaId) {
            $piezas = Piezas::with(['modelo'])->withSum('stockTienda', 'cantidad')->where('id', $piezaId)->get();
        } else {
            $piezas = Piezas::with(['modelo'])->withSum('stockTienda', 'cantidad')->take(10)->get();
        }

        foreach ($piezas as $pieza) {
            if (intval($pieza->stock_tienda_sum_cantidad || 0) <= $pieza->minimo) {
                $reponer = intval($pieza->maximo) - intval($pieza->stock_tienda_sum_cantidad);

                if ($reponer >= $pieza->pto_optimo && intval($pieza->pto_optimo || 0) > 0 && $pieza->kanban_reposicion) {
                    if (StockPiezasLib::existeKanbanReemplazoPendienteIngreso($pieza->id)) {
                        continue;
                    }

                    //Averiguo las capas
                    $capas = ceil($reponer / $pieza->pto_optimo);

                    if ($capas > 20) {
                        $capas = 20; //Limito a 20 capas por kanban
                    }

                    $pieza->capas = $capas;
                    $pieza->reponer = $reponer;
                    $pieza->pto_optimo = intval($pieza->pto_optimo);
                    $pieza->minimo = intval($pieza->minimo);
                    $pieza->maximo = intval($pieza->maximo);
                    $pieza->stock_tienda_sum_cantidad = intval($pieza->stock_tienda_sum_cantidad);
                    $pieza->file = $pieza->modelo->nombre . '/' . $pieza->kanban_reposicion;

                    $pdfKanban = new PdfKanban($pieza->id, $pieza->modelo->nombre, $pieza->kanban_reposicion, $pieza->capas);
                    $kanban = Kanban::create("R", ['modelo' => $pdfKanban->modelo->id, 'pieza' => $pdfKanban->pieza->id, 'capas' => $capas, 'mes' => null]);

                    if (!$kanban) {
                        return;
                    }

                    $pdfKanban->setKanban($kanban);
                    $pdfKanban->generate(true, $pieza->id . ".pdf");

                    array_push($files, public_path('pdf/' . $pieza->id . ".pdf"));

                    FilePrint::create([
                        'file_name' => $pieza->id . ".pdf",
                        'file_path' => public_path('pdf/' . $pieza->id . ".pdf"),
                        'printed'   => false
                    ]);

                    if ($piezaId) {
                        return $kanban;
                    }
                }
            }
        }

        if ($piezaId) {
            return null;
        }

        PdfKanban::merge_files($files, true);
        // return $files;
        // return response($response);
    }
}
