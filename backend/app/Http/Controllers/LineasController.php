<?php

namespace App\Http\Controllers;

use App\Http\Estados;
use App\Models\EstadoKanban;
use App\Models\Lineas;
use App\Models\Modelos;
use App\Models\ModeloSublineTiempo;
use App\Models\PlanCostura;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use stdClass;


class LineasController extends Controller {

    private $CANTIDAD_DEFECTO_LINEAS  = [
        'M1'        => 180,
        'M2'        => 170,
        'M3'        => 125,
        'M4'        => 120,
        'M5'        => 75,
        'M6'        => 72,
        'M7'        => 0,
        'M8'        => 0,
        'M9'        => 0,
        'M10'       => 0,
        'M11'       => 140,
    ];

    public function index() {
        $data = Lineas::get()->toArray();
        return $this->setResponse($data);
    }

    public function lineasOcupacion() {
        $response = [];
        $data = Lineas::with(['kanbansEnBuffer.kanban.modelo'])
            ->whereIn('id', [1, 2, 3, 4, 5, 6, 11])
            ->get()
            ->toArray();

        $fechaActual = date('d/m/Y');

        try {
            foreach ($data as $d) {
                $linea = [];
                $linea['minutos_ocupacion'] = 0;
                $linea['codigo'] = $d['codigo'];
                $linea['id'] = $d['id'];

                $plan = PlanCostura::selectRaw('sum(tm) as cantidad')->where('linea', $d['codigo'])->where('fecha', $fechaActual)->first();

                try {
                    $cantidadPlan = intval($plan->cantidad);
                    $linea['plan_costura'] = $cantidadPlan;
                } catch (\Throwable $th) {
                    $linea['plan_costura'] = 0;
                }

                $total = 0;
                $modelos = [];

                if (is_null($d['kanbans_en_buffer'])) {
                    $kanbansEnBuffer = [];
                } else {
                    $kanbansEnBuffer = $d['kanbans_en_buffer'];
                }

                foreach ($kanbansEnBuffer as $k) {

                    $mod = $k['kanban']['modelo']['nombre'];
                    $cantidad = intval($k['kanban']['modelo']['cantidad']);
                    $minimo = intval($k['kanban']['modelo']['minimo_buffer']);
                    $ptoPedido = intval($k['kanban']['modelo']['ptopedido_buffer']);

                    $pos = $this->existeEnArray($mod, $modelos);

                    if ($pos >= 0) {
                        $modelos[$pos]['cantidad'] = intval($modelos[$pos]['cantidad']) + intval($cantidad);
                    } else {
                        array_push($modelos, [
                            'nombre'    => $mod,
                            'cantidad'  => $cantidad,
                            'minimo'    => $minimo,
                            'ptopedido' => $ptoPedido,
                        ]);
                    }

                    $total +=  intval($cantidad);
                }
                $linea['modelos'] = $modelos;
                $linea['total'] = $total;

                //TOMO DE ACUERDO AL PLAN, SI NO, TOMO VALORES POR DEFECTO PARA QUE NO QUEDE EN 0
                $requerido = intval($linea['plan_costura']);
                if ($requerido == 0) {
                    $requerido = $this->CANTIDAD_DEFECTO_LINEAS[$d['codigo']];
                }

                if ($total < $requerido) {
                    $linea['bgColor'] = '!bg-red-500';
                    $linea['turnos_cubiertos'] = 0;
                } else if ($total <= ($requerido + 20)) {
                    $linea['turnos_cubiertos'] = 1;
                    $linea['bgColor'] = '!bg-yellow-300';
                } else {
                    $linea['turnos_cubiertos'] = 2;
                    $linea['bgColor'] = '!bg-green-500';
                }

                $linea['requerido'] = $requerido;

                array_push($response, $linea);
            }
        } catch (\Throwable $th) {
            Log::error("LineasController::lineasOcupacion : " . $th->getMessage());
        }

        return $this->setResponse($response);
    }

    public function getModelosLinea($linea) {

        $modelos = Modelos::with(['fallas.tipo', 'fallas.lado', 'partes.lado', 'partes.tipo', 'partes.piezas.material_pieza'])
            ->where('activo', 1)
            ->whereHas('lineas', function ($q) use ($linea) {
                $q->where('linea_id', intval($linea));
            })
            ->get();

        return $this->setResponse($modelos ? $modelos->toArray() : []);
    }

    public function getModelosLineaFlex() {

        $modelos = Modelos::with(['lineas', 'fallas.tipo', 'fallas.lado', 'partes.lado', 'partes.tipo', 'partes.piezas.material_pieza'])
            ->where('activo', 1)
            ->get();

        return $this->setResponse($modelos ? $modelos->toArray() : []);
    }


    private function existeEnArray($value, $array) {

        foreach ($array as $k => $v) {
            if ($value == $v['nombre']) {
                return $k;
            }
        }

        return -1;
    }

    public function store(Request $request) {

        $id = $request->id;
        $data = [
            'codigo' => $request->codigo,
            'capacidad' => $request->capacidad,
            'posicion' => $request->posicion,
            'columnas' => $request->columnas,
        ];


        try {
            if ($id) {
                Lineas::where('id', $id)->update($data);
            } else {
                Lineas::create($data);
            }

            return $this->setResponse([]);
        } catch (\Throwable $th) {
            Log::error("LineasController::store - Error : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas");
        }
    }
}
