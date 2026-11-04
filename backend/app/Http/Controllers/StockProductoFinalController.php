<?php

namespace App\Http\Controllers;

use App\Http\Kanban;
use App\Models\Kanbans;
use App\Models\Modelos;
use App\Models\StockProductoFinal;
use App\Models\UbicacionesMovimientos;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StockProductoFinalController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($modelo = null) {
        if ($modelo == 'null') {
            $modelo = null;
        }

        $modelos = Modelos::when(!empty($modelo), function ($q) use ($modelo) {
            $q->where('id', $modelo);
        })
            ->where('activo', 1)
            ->orderBy('nombre')
            ->get();

        $now = Date('Y-m-d');

        $res = [];
        $fromDate = Date('Y-m-d', strtotime('-1 days'));
        $toDate = Date('Y-m-d', strtotime('+3 days'));

        foreach ($modelos as $mod) {

            $stock = StockProductoFinal::selectRaw('month(fecha) as mes,year(fecha) as ano, count(*) as cantidad ')
                ->where('modelo', $mod->nombre)
                ->where('egresado', false)
                ->where('cuarentena', false)
                ->where('rechazado', false)
                ->groupByRaw('YEAR(fecha)')
                ->groupByRaw('MONTH(fecha)')
                ->orderByRaw('YEAR(fecha)')
                ->orderByRaw('MONTH(fecha)')
                ->get();

            // $stock = UbicacionesMovimientos::selectRaw('month(kanbans.fecha) as mes,year(kanbans.fecha) as ano, count(*) as cantidad ')
            //     ->leftJoin('ubicacion_contenidos', 'ubicacion_contenidos.movimiento_id', '=', 'ubicaciones_movimientos.id')
            //     ->leftJoin('kanbans', 'ubicacion_contenidos.contenido', '=', 'kanbans.codigo')
            //     ->leftJoin('modelos', 'kanbans.modelo_id', '=', 'modelos.id')
            //     ->where('modelos.nombre', $mod->nombre)
            //     ->groupByRaw('YEAR(fecha)')
            //     ->groupByRaw('MONTH(fecha)')
            //     ->orderByRaw('YEAR(fecha)')
            //     ->orderByRaw('MONTH(fecha)')
            //     ->where('egreso', null)
            //     ->get();

            $cuarentena = StockProductoFinal::selectRaw('count(*) as cantidad')->where('cuarentena', true)
                ->where('egresado', false)->where('rechazado', false)->where('modelo', $mod->nombre)->get();

            $rechazados = StockProductoFinal::selectRaw('count(*) as cantidad')->where('rechazado', true)->where('modelo', $mod->nombre)->get();

            $mod->rechazados = $rechazados;
            $mod->cuarentena = $cuarentena;
            $mod->stock = $stock;

            $egresos = StockProductoFinal::selectRaw('fecha_egreso,count(*) as cantidad,run')->where('cuarentena', false)
                ->where('egresado', true)->where('rechazado', false)->where('modelo', $mod->nombre)
                ->whereBetween('fecha_egreso', [$fromDate, $toDate])
                ->groupByRaw('fecha_egreso,run')
                ->orderByRaw('fecha_egreso')
                ->get();

            // $egresos = UbicacionesMovimientos::selectRaw('substring(CONVERT(NVARCHAR,egreso,120),1,10) as egreso,count(*) as cantidad,1 as run')
            //     ->leftJoin('ubicacion_contenidos', 'ubicacion_contenidos.movimiento_id', '=', 'ubicaciones_movimientos.id')
            //     ->leftJoin('kanbans', 'ubicacion_contenidos.contenido', '=', 'kanbans.codigo')
            //     ->leftJoin('modelos', 'kanbans.modelo_id', '=', 'modelos.id')
            //     ->where('modelos.nombre', $mod->nombre)
            //     ->whereBetween('egreso', [$fromDate, $toDate])
            //     ->groupByRaw('egreso')
            //     ->orderByRaw('egreso')
            //     ->get();

            // Log::alert($egresos);

            $mod->egresos = $egresos;

            $stockAyer = StockProductoFinal::selectRaw('year(fecha) as ano,month(fecha) as mes, count(*) as cantidad')
                ->where('modelo', $mod->nombre)
                ->where('egresado', 0)
                ->orWhere(function ($q) use ($now, $mod) {
                    $q->where('fecha_egreso', '>=', $now)->where('egresado', 1)->where('modelo', $mod->nombre);
                })
                ->groupByRaw('year(fecha)')
                ->groupByRaw('month(fecha)')
                ->get();


            // $stockAyer = UbicacionesMovimientos::selectRaw('year(fecha) as ano,month(fecha) as mes, count(*) as cantidad')
            //     ->leftJoin('ubicacion_contenidos', 'ubicacion_contenidos.movimiento_id', '=', 'ubicaciones_movimientos.id')
            //     ->leftJoin('kanbans', 'ubicacion_contenidos.contenido', '=', 'kanbans.codigo')
            //     ->leftJoin('modelos', 'kanbans.modelo_id', '=', 'modelos.id')
            //     ->where('modelos.nombre', $mod->nombre)
            //     ->orWhere(function ($q) use ($now, $mod) {
            //         $q->where('egreso', '>=', $now)->where('modelos.nombre', $mod->nombre);
            //     })
            //     ->groupByRaw('year(fecha)')
            //     ->groupByRaw('month(fecha)')
            //     ->get();

            $mod->stockAyer = $stockAyer;

            array_push($res, $mod);
        }

        // Log::alert($res);
        return $this->setResponse($res);
    }

    public function store(Request $request) {

        $esEgreso = $request->egreso == 1;
        // $fhLimite = new DateTime('2023-12-31');

        if (strlen($request->kanban) > 16) {
            return $this->setResponse([], "Formato de kanban incorrecto", true);
        }

        $data = StockProductoFinal::where('codigo_kanban', $request->kanban)
            ->when($esEgreso, function ($q) {
                $q->where('egresado', false);
            })
            // ->where('egresado', false)
            ->first();

        if ($esEgreso) {
            //Verifico existencia
            if (!$data) {
                return $this->setResponse([], "El kanban a egresar no existe en stock o ya fue despachado.", true);
            }

            if ($data->cuarentena) {
                return $this->setResponse([], "El kanban se encuentra en cuarentena.", true);
            }

            //Verifico si requiere autorización
            $fecha = new DateTime($data->fecha);

            // if ($fecha <= $fhLimite) {
            //     try {
            //         StockProductoFinal::where('codigo_kanban', $request->kanban)->update(['cuarentena' => true]);
            //         return $this->setResponse([], "El kanban requiere autorización de calidad. Se pasa a cuarentena.", false);
            //     } catch (\Throwable $th) {
            //         Log::error("StockProductoFinalController::store Update carentena estado - " . $th->getMessage());
            //         return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
            //     }
            // }

            //Egreso
            //Al egresarlo, lo paso automáticamente a cuarentena para aprobación de calidad
            try {
                $fechaEgreso = date("Y-m-d");
                if ($request->run == "1") {
                    $fechaEgreso = Date('Y-m-d', strtotime('+1 days'));
                }

                StockProductoFinal::where('codigo_kanban', $request->kanban)->update(['cuarentena' => true, 'fecha_egreso' => $fechaEgreso, 'run' => $request->run]);
                // StockProductoFinal::where('codigo_kanban', $request->kanban)->update(['egresado' => true, 'fecha_egreso' => $fechaEgreso]);
                return $this->setResponse([], "El kanban requiere autorización de calidad. Se pasa a cuarentena");
            } catch (\Throwable $th) {
                Log::error("StockProductoFinalController::store Update egreso estado - " . $th->getMessage());
                return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
            }


            return $this->setResponse($data->toArray());
        }

        //INGRESO
        //Verifico existencia
        if ($data) {
            if ($data->egreso) {
                return $this->setResponse([], "El kanban ya fue egresado.", true);
            }
            return $this->setResponse([], "El kanban a ingresar ya se encuentra en deposito.", true);
        }

        $fecha = substr($request->kanban, 1, 6);
        $fecha = "20" . substr($fecha, 0, 2) . '-' . substr($fecha, 2, 2) . '-' . substr($fecha, 4, 2);
        $date = DateTime::createFromFormat("Y-m-d", $fecha);

        //Si no existe lo doy de alta
        //Obtengo el modelo del kanban
        $kanbanExistente = Kanban::registrarKanbanSiNoExiste($request->kanban);
        $kanban = Kanbans::with('modelo')->where('codigo', $request->kanban)->first();

        if (!$kanban) {
            return $this->setResponse([], "No se pudo identificar el kanban. Comuníquese con el encargado de sistemas.", true);
        }

        $payload = [
            'codigo_kanban' => $request->kanban,
            'modelo'        => $kanban->modelo->nombre,
            'fecha'         => $date->format("Y-m-d"),
            'mes'           => intval($date->format("m")),
            'egresado'      => false,
            'cuarentena'    => false,
            'rechazado'     => false,
        ];

        try {
            StockProductoFinal::create($payload);
            return $this->setResponse([], "Kanban ingresado correctamente a stock");
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("StockProductoFinalController::store - " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
        }

        return $this->setResponse($payload);
    }

    public function getCuarentena() {

        $data = StockProductoFinal::where('cuarentena', 1)->where('egresado', 0)->orderBy('fecha')->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function cambiarEstadoPorCalidad(Request $request, $kanban) {

        // Log::alert($kanban);

        $existe = StockProductoFinal::where('codigo_kanban', $kanban)->where('cuarentena', true)->where('egresado', false)->first();

        if (!$existe) {
            return $this->setResponse([], "El kanban ingresado no existe", true);
        }

        // Log::alert($existe);
        try {
            StockProductoFinal::where('codigo_kanban', $kanban)->update([
                'cuarentena'    => false,
                'egresado'      => true,
                'fecha_calidad' => date('Y-m-d'),
                'rechazado'     => ($request->rechazado == 1)
            ]);
            return $this->setResponse([], "Actualizado correctamente");
        } catch (\Throwable $th) {
            Log::error("StockProductoFinalController::cambiarEstadoPorCalidad - " . $th->getMessage());
            //throw $th;
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas.", true);
        }
    }

    public function show($kanban) {

        $data = StockProductoFinal::where('codigo_kanban', $kanban)->first();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([], "El kanban ingresado no existe", true);
        }
    }

    public function update(Request $request) {

        $codigoKanban = $request->codigo_kanban;
        $data = $request->toArray();

        if (intval($request->modelo) > 0) {
            $modelo = Modelos::where('id', intval($request->modelo))->first();
            if ($modelo) {
                $data['modelo'] = $modelo->nombre;
            }
        }

        $kanban = StockProductoFinal::where('codigo_kanban', $codigoKanban)->first();

        if (!$kanban) {
            return $this->setResponse([], "El kanban ingresado no existe", true);
        }

        try {
            StockProductoFinal::where('id', $kanban->id)
                ->update($data);
            return $this->setResponse([], "Actualizado correctamente");
        } catch (\Throwable $th) {
            Log::error("StockProductoFinalController::update - " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas.", true);
            //throw $th;
        }
    }

    public function filter(Request $request) {

        $data = StockProductoFinal::where('fecha_egreso', $request->fecha_egreso)->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function destroy(StockProductoFinal $stockProductoFinal) {
        //
    }
}
