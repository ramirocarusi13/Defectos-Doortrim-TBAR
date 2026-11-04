<?php

namespace App\Services;

use App\Http\Kanban;
use App\Models\Kanbans;
use App\Models\Modelos;
use Illuminate\Support\Facades\Log;

class KanbanService {

    static function getKanban(string $codigoKanban): Kanbans {
        $data = Kanbans::with(['modelo.partes.vehiculo', 'modelo.fila', 'estado'])
            ->where('codigo', $codigoKanban)
            ->first();

        return $data;
    }


    static function fixKanbanSinModelos() {
        $kanbans = Kanbans::where('modelo_id', null)->get();

        foreach ($kanbans as $k) {
            if (substr($k->codigo, 0, 1) == 'P') {
                $kSar = Kanban::getKanbanSAR(ltrim(rtrim($k->codigo)));
                if ($kSar) {
                    if (!is_null($kSar->modelo)) {
                        $modelo = Modelos::where('nombre', ltrim(rtrim($kSar->modelo->NOMBRE)))->first();
                        if ($modelo) {
                            Kanbans::where('id', $k->id)
                                ->update([
                                    'modelo_id' => $modelo->id
                                ]);
                        } else {
                            // Log::alert("NO EXISTE EL MODELO : " . ltrim(rtrim($kSar->modelo->NOMBRE)));
                        }
                    } else {
                        // Log::alert("NO TIENE MODELO EL KANBAN : " . $k->codigo);
                    }
                } else {
                    // Log::alert($kSar);
                    // Log::alert("NO EXISTE EL KANBAN : " . $k->codigo);
                }
            }
        }
    }
}
