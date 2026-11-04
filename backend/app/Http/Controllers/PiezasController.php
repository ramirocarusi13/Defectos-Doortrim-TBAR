<?php

namespace App\Http\Controllers;

use App\Http\Depositos;
use App\Http\Estados;
use App\Http\Kanban;
use App\Models\EstadoKanban;
use App\Models\Kanbans;
use App\Models\KanbansReemplazo;
use App\Models\Partes;
use App\Models\DadosPieza;
use App\Models\Piezas;
use App\Models\PiezasMateriales;
use App\Models\StockPiezas;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PiezasController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $data = Piezas::with(['parte.tipo', 'parte.lado', 'parte.modelo', 'material_pieza'])->get()->toArray();

        return $this->setResponse($data);
    }

    public function setPosicionPiezas(Request $request) {

        // Log::alert($request['rectangles']);

        foreach ($request['rectangles'] as $rect) {
            $position = intval($rect['position'] ?? 1);
            $fields = $position === 2
                ? [
                    'p2_left'   => $rect['x'],
                    'p2_top'    => $rect['y'],
                    'p2_width'  => $rect['width'],
                    'p2_height' => $rect['height'],
                ]
                : [
                    'p_left'    => $rect['x'],
                    'p_top'     => $rect['y'],
                    'p_width'   => $rect['width'],
                    'p_height'  => $rect['height'],
                ];

            Piezas::where('id', $rect['codigo'])->update($fields);
        }

        return $this->setResponse([]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $data = $request->validate([
            'codigo' => 'required|string|max:255',
            'parte_id' => 'required|exists:partes,id',
            'material_pieza_id' => 'nullable|exists:materiales_piezas,id',
            'pto_optimo' => 'nullable|integer',
            'minimo' => 'nullable|integer',
            'maximo' => 'nullable|integer',
            'dado' => 'nullable|string|max:255',
            'kanban_reposicion' => 'nullable|string|max:255',
            'p_left' => 'nullable|numeric',
            'p_top' => 'nullable|numeric',
            'p_width' => 'nullable|numeric',
            'p_height' => 'nullable|numeric',
            'p2_left' => 'nullable|numeric',
            'p2_top' => 'nullable|numeric',
            'p2_width' => 'nullable|numeric',
            'p2_height' => 'nullable|numeric',
        ]);

        try {
            $pieza = Piezas::create($data);
            $pieza->load(['material_pieza', 'parte.tipo', 'parte.lado', 'parte.modelo']);

            return $this->setResponse($pieza->toArray(), "Pieza creada correctamente");
        } catch (\Throwable $th) {
            Log::error("PiezasController::store : " . $th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Piezas  $piezas
     * @return \Illuminate\Http\Response
     */
    public function show(Piezas $pieza) {
        if (!$pieza) {
            return $this->setResponse([], "La pieza seleccionada no existe", true);
        }

        $pieza = Piezas::with(['dadoReposicion.material', 'dadoReposicion.modelo', 'material_pieza', 'parte.modelo', 'modelo'])
            ->where('id', $pieza->id)
            ->first();

        return $this->setResponse($pieza?->toArray() ?? []);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Piezas  $piezas
     * @return \Illuminate\Http\Response
     */
    public function edit(Piezas $piezas) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Piezas  $piezas
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Piezas $pieza) {

        if (!$pieza) {
            return $this->setResponse([], "La pieza seleccionada no existe", true);
        }

        $request->validate([
            'codigo' => 'sometimes|required|string|max:255',
            'parte_id' => 'sometimes|required|exists:partes,id',
            'material_pieza_id' => 'nullable|exists:materiales_piezas,id',
            'pto_optimo' => 'nullable|integer',
            'minimo' => 'nullable|integer',
            'maximo' => 'nullable|integer',
            'dado' => 'nullable|string|max:255',
            'kanban_reposicion' => 'nullable|string|max:255',
            'consumo' => 'nullable|numeric',
            't_lectra1' => 'nullable|string|max:255',
            't_lectra2' => 'nullable|string|max:255',
            't_lectra3' => 'nullable|string|max:255',
            't_lectra4' => 'nullable|string|max:255',
        ]);

        $data = [];
        if ($request->has('codigo')) {
            $data['codigo'] = $request->codigo;
        }

        if ($request->has('parte_id')) {
            $data['parte_id'] = intval($request->parte_id);
        }

        if ($request->has('pto_optimo')) {
            $data['pto_optimo'] = intval($request->pto_optimo);
        }

        if ($request->has('minimo')) {
            $data['minimo'] = intval($request->minimo);
        }

        if ($request->has('maximo')) {
            $data['maximo'] = intval($request->maximo);
        }

        if ($request->has('dado')) {
            $data['dado'] = !empty($request->dado) ? $request->dado : null;
        }

        if ($request->has('material_pieza_id')) {
            $data['material_pieza_id'] = !empty($request->material_pieza_id) ? intval($request->material_pieza_id) : null;
        }

        if ($request->hasFile('kanban_reposicion')) {
            $modelo = $pieza->modelo;
            $modeloNombre = $modelo?->nombre;

            if (empty($modeloNombre)) {
                return $this->setResponse([], "No fue posible determinar el modelo de la pieza", true);
            }

            $file = $request->file('kanban_reposicion');
            $directory = public_path('kanban_reposicion/' . $modeloNombre);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            $fileName = $file->getClientOriginalName();
            $file->move($directory, $fileName);
            $data['kanban_reposicion'] = $fileName;
        } elseif ($request->has('kanban_reposicion')) {
            $data['kanban_reposicion'] = $request->kanban_reposicion;
        }

        $dadoData = [];
        foreach (['t_lectra1', 't_lectra2', 't_lectra3', 't_lectra4'] as $field) {
            if ($request->has($field)) {
                $dadoData[$field] = !empty($request->$field) ? $request->$field : null;
            }
        }

        if ($request->has('dado')) {
            $dadoData['dado'] = !empty($request->dado) ? $request->dado : null;
        }

        if ($request->has('consumo')) {
            $dadoData['consumo'] = !empty($request->consumo) ? floatval($request->consumo) : null;
        }

        if ($request->has('material_pieza_id')) {
            $dadoData['material_id'] = !empty($request->material_pieza_id) ? intval($request->material_pieza_id) : null;
        }

        try {
            $pieza->update($data);

            if (!empty($dadoData)) {
                if (empty($pieza->dadoReposicion?->id)) {
                    $dadoData['pieza_id'] = $pieza->id;
                    $dadoData['modelo_id'] = $pieza->modelo?->id;
                    DadosPieza::create($dadoData);
                } else {
                    $pieza->dadoReposicion->update($dadoData);
                }
            }

            $pieza->load(['material_pieza', 'parte.tipo', 'parte.lado', 'parte.modelo']);

            return $this->setResponse($pieza->toArray(), "Actualizado correctamente");
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
        }
    }

    public function syncDadosPiezasPrueba() {
        $insertados = 0;
        $omitidos = 0;
        $errores = [];

        $totalPiezas = Piezas::count();
        $conDadoPrevios = DadosPieza::whereNotNull('pieza_id')->distinct('pieza_id')->count('pieza_id');

        Piezas::with(['modelo'])
            ->orderBy('id')
            ->chunkById(200, function ($piezas) use (&$insertados, &$omitidos, &$errores) {
                foreach ($piezas as $pieza) {
                    try {
                        $dadosPieza = DadosPieza::firstOrCreate(
                            ['pieza_id' => $pieza->id],
                            $this->buildDadosPiezaPruebaData($pieza)
                        );

                        if ($dadosPieza->wasRecentlyCreated) {
                            $insertados++;
                        } else {
                            $omitidos++;
                        }
                    } catch (\Throwable $th) {
                        Log::error('Error sincronizando dado de pieza de prueba', [
                            'pieza_id' => $pieza->id,
                            'error' => $th->getMessage()
                        ]);

                        $errores[] = [
                            'pieza_id' => $pieza->id,
                            'error' => $th->getMessage()
                        ];
                    }
                }
            });

        return $this->setResponse([
            'total_piezas' => $totalPiezas,
            'con_dado_previos' => $conDadoPrevios,
            'insertados' => $insertados,
            'omitidos' => $omitidos,
            'errores' => count($errores),
            'detalle_errores' => array_slice($errores, 0, 20)
        ], 'Backfill de dados_piezas ejecutado');
    }

    public function updateAreaByCodigo(Request $request, $codigo) {

        if (!$request->has('area') && !$request->has('material_pieza_id')) {
            return $this->setResponse([], "Debe indicar el area o el material_pieza_id a actualizar", true);
        }

        $piezas = Piezas::where('codigo', $codigo)->get();

        if (!$piezas || $piezas->count() == 0) {
            return $this->setResponse([], "No existen piezas para el codigo indicado", true);
        }

        $data = [];

        if ($request->has('area')) {
            $data['area'] = $request->area;
        }

        if ($request->has('material_pieza_id')) {
            $data['material_pieza_id'] = $request->material_pieza_id;
        }

        try {
            Piezas::where('codigo', $codigo)->update($data);

            return $this->setResponse([
                'codigo' => $codigo,
                'area' => $data['area'] ?? null,
                'material_pieza_id' => $data['material_pieza_id'] ?? null,
                'actualizados' => $piezas->count()
            ], "Actualizado correctamente");
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return $this->setResponse([], "Ocurrió un error. Comuníquese con el encargado de sistemas", true);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Piezas  $piezas
     * @return \Illuminate\Http\Response
     */
    public function destroy(Piezas $piezas) {
        //
    }

    public function getPiezasByParte($parteId) {
        // Log::alert("PASO");
        $parte = Partes::where('id', $parteId)->where('activo', 1)->first();

        // Log::alert($parte);

        if (!$parte) {
            return $this->setResponse([], "La parte no existe", true);
        }
        // $piezas = Piezas::with(['material.material'])->where('parte_id', $parte->id)->get();

        $piezas = PiezasMateriales::with(['pieza.modelo', 'material'])
            ->whereHas('pieza', function ($q) use ($parteId) {
                $q->where('parte_id', $parteId);
            })->get();

        // Log::alert($piezas);

        if ($piezas) {
            return $this->setResponse($piezas->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    private function buildDadosPiezaPruebaData(Piezas $pieza): array {
        return [
            'pieza_id' => $pieza->id,
            'modelo_id' => $pieza->modelo?->id,
            'material_id' => $pieza->material_pieza_id,
            'dado' => $pieza->dado,
            'consumo' => $this->fakeConsumoPieza($pieza->id),
            't_lectra1' => $this->fakeLectraTime($pieza->id, 1),
            't_lectra2' => $this->fakeLectraTime($pieza->id, 2),
            't_lectra3' => $this->fakeLectraTime($pieza->id, 3),
            't_lectra4' => $this->fakeLectraTime($pieza->id, 4),
        ];
    }

    private function fakeLectraTime(int $piezaId, int $lectra): string {
        $minutes = (($piezaId % 7) + 4) + (($lectra - 1) * 2);

        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    private function fakeConsumoPieza(int $piezaId): float {
        return round(0.5 + (($piezaId % 15) * 0.1), 2);
    }

    public function getPiezasBykanbanWithStockTienda($kanbanCode, $egreso = 1) {

        $esEgreso = boolval($egreso);
        // $kanban = Kanbans::with(['modelo.partes.piezas', 'estado'])->where('codigo', $kanbanCode)->first();

        $kanban = Kanban::exists($kanbanCode);

        if (!$kanban) {
            return $this->setResponse([], "El kanban ingresado no existe", true);
        }

        $kanbanReemplazo = KanbansReemplazo::where('kanban_id', $kanban->id)->first();

        if (!is_null($kanbanReemplazo->fecha_ingreso)) {
            $fecha = DateTime::createFromFormat("Y-m-d H:i:s.u", $kanbanReemplazo->fecha_ingreso);
            return $this->setResponse([], "El kanban ingresado fue procesado el " . $fecha->format('d/m/Y H:i'), true);
        }

        //Solamente permito sacar de tienda si el kanban esta en SUB ASSY, COSTURA O GENERADO
        //GENERADO es porque pueden pedir a tienda antes de cortar

        if ($esEgreso) {
            $estadoKanban = EstadoKanban::with(['estado'])->where('kanban_id', $kanban->id)->first();
            if ($estadoKanban->estado_id != Estados::GENERADO && $estadoKanban->estado_id != Estados::SUB_ASSY && $estadoKanban->estado_id != Estados::COSTURA) {
                return $this->setResponse([], "No se permite retirar piezas de este kanban. Se encuentra en estado : " . $estadoKanban->estado->descripcion, true);
            }
        }

        if ($esEgreso) {
            //Recorro cada pieza y obtengo el stock de tienda
            foreach ($kanban->modelo->partes as $parte) {
                // Log::alert($parte);
                foreach ($parte->piezas as $pieza) {
                    $pieza->stock = StockPiezas::select(DB::raw('SUM(cantidad) as stock'))->where('pieza_id', $pieza->id)
                        ->where('deposito_id', Depositos::TIENDA)
                        ->first()->stock;
                }
            }
        }

        if ($kanban) {
            return $this->setResponse($kanban->toArray());
        } else {
            return $this->setResponse([]);
        }
    }
}
