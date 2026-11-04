<?php

namespace App\Models\Sar;

use App\Http\Kanban;
use App\Models\Kanbans;
use App\Models\MaterialesPiezas;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TKanban extends Model {
    use HasFactory;

    protected $connection = 'sqlsrvsar';
    protected $table = 't_kanban';

    // public function modelo(): HasOne {
    //     return $this->hasOne(PKanbanProceso::class, 'ID_CODIGO', 'ID_CODIGO');
    // }

    public function modelo(): HasOne {
        return $this->hasOne(SARModelo::class, 'ID', 'ID_CODIGO');
    }

    public function crear($modeloCodigo) {

        try {
            $now = \DateTime::createFromFormat('U.u', microtime(true));
            $local = $now->setTimezone(new \DateTimeZone('America/Argentina/Buenos_Aires'));
            $codigo = "P" . $local->format("ymdHisv");

            $modelo = SARModelo::where('NOMBRE', $modeloCodigo)->first();

            DB::connection('sqlsrvsar')->update("EXEC [StartKanban] '" . $codigo . "','" . $modelo->ID . "',1,'PC'");

            $existe = Kanban::getKanbanSAR($codigo);
            if ($existe) {
                // Log::alert("EXISTE");
                DB::connection('sqlsrvsar')->update("EXEC [StartKanbanProceso] '" . $codigo . "','" . substr($codigo, 3, 2) . "'");

                return $codigo;
            }

            return "";
        } catch (\Throwable $th) {
            Log::error("TKanban::create - " . $th->getMessage());
            return null;
        }
    }

    public function crearKanbanMaterial($materialCodigo, $metros) {

        try {
            $now = \DateTime::createFromFormat('U.u', microtime(true));
            $local = $now->setTimezone(new \DateTimeZone('America/Argentina/Buenos_Aires'));
            $codigo = "M" . $local->format("ymdHisv");

            // $modelo = SARModelo::where('NOMBRE', $materialCodigo)->first();
            $material = MaterialesPiezas::where('codigo', $materialCodigo)->first();

            DB::connection('sqlsrvsar')->update("EXEC [StartKanban] '" . $codigo . "','" . $materialCodigo . "',1,'PC'");

            $existe = Kanban::getKanbanSAR($codigo);
            // $existe = Kanban::registrarKanbanSiNoExiste($codigo);
            // $existe = Kanbans::where('codigo', $codigo)->first();
            if ($existe) {
                // Log::alert("EXISTE");
                // Log::alert($existe);
                DB::connection('sqlsrvsar')->update("EXEC [StartKanbanMaterial] '" . $codigo . "','" . $material->nombre . "','" . $metros . "'");
                return ['kanban' => $existe->N_KANBAN, 'material' => $material];
            }

            return "";
        } catch (\Throwable $th) {
            Log::error("TKanban::createKanbanMaterial - " . $th->getMessage());
            return null;
        }
    }

    public function cargarMM_SAP($codigoKanban, $metros, $ud, $depositoOrigen = "MP00", $depositoDestino = "BF00") {
        DB::connection('sqlsrvsar')->update("EXEC [CargarMM_SAP] '" . $codigoKanban . "','" . $metros . "','" . $ud . "','" . $depositoOrigen . "','" . $depositoDestino . "',1");
    }
}
