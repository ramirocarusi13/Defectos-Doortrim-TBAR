<?php

namespace App\Http\Controllers;

use App\Http\Estados;
use App\Models\EstadoKanban;
use App\Models\Kanbans;
use Illuminate\Support\Facades\Log;

class ConfigController extends Controller {

    public function vaciarBuffer($linea) {

        try {

            EstadoKanban::where('linea_id', intval($linea))
                ->where('estado_id', Estados::EN_BUFFER)
                ->update(['estado_id' => Estados::GENERADO]);

            return $this->setResponse([]);
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('ConfigController::vaciarBuffer : ' . $th->getMessage());
            return $this->setResponse([], 'Ocurrió un error : ' . $th->getMessage(), true);
        }
    }

    public function quitarKanbanDeBuffer($kanban) {

        try {
            $k = Kanbans::where('codigo', $kanban)->first();

            EstadoKanban::where('kanban_id', $k->id)
                ->where('estado_id', Estados::EN_BUFFER)
                ->update(['estado_id' => Estados::GENERADO]);

            return $this->setResponse([]);
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('ConfigController::quitarKanbanDeBuffer : ' . $th->getMessage());
            return $this->setResponse([], 'Ocurrió un error : ' . $th->getMessage(), true);
        }
    }

    public function getBuffer($linea) {
        try {

            $data = EstadoKanban::with(['kanban.modelo'])->where('linea_id', intval($linea))
                ->where('estado_id', Estados::EN_BUFFER)
                ->get();

            return $this->setResponse($data->toArray());
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('ConfigController::getBuffer : ' . $th->getMessage());
            return $this->setResponse([], 'Ocurrió un error : ' . $th->getMessage(), true);
        }
    }
}
