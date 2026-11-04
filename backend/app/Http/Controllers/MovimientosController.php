<?php

namespace App\Http\Controllers;

use App\Enums\DaysOfWeek;
use App\Http\Depositos as HttpDepositos;
use App\Http\Kanban;
use App\Http\LineasModelo;
use App\Http\Stock;
use App\Http\WmsUnidades;
use App\Models\Depositos;
use App\Models\Despachos;
use App\Models\DespachosItems;
use App\Models\Kanbans;
use App\Models\MaterialesAprobacionCalidad;
use App\Models\MaterialesPiezas;
use App\Models\Modelos;
use App\Models\Movimientos;
use App\Models\MovimientosContenido;
use App\Models\MovimientosStockKanbans;
use App\Models\StockFilaTemp;
use App\Models\StockHRDTAR;
use App\Models\StockKanbans;
use App\Models\StockMateriales;
// use App\Models\StockMateriales;
use App\Support\PositionName;
use App\Models\Ubicaciones;
use App\Models\Unidades;
use App\Services\MaterialesRetenerService;
use App\Services\RollosService;
use App\Services\StockService;
use DateInterval;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MovimientosController extends Controller {
    private $orderRawModelos = "
    CASE 
        WHEN nombre = 'SFEP'        THEN 1
        WHEN nombre = 'SFMR'        THEN 2
        WHEN nombre = 'SFKN'        THEN 3
        WHEN nombre = 'SFKQ'        THEN 4
        WHEN nombre = 'SFTS'        THEN 5
        WHEN nombre = 'SFBN'        THEN 6
        WHEN nombre = 'SFNG'        THEN 7
        WHEN nombre = 'SFNJ'        THEN 8
        WHEN nombre = 'SFHG'        THEN 9
        WHEN nombre = 'SFHP'        THEN 10
        WHEN nombre = 'SFJJ'        THEN 11
        WHEN nombre = 'SFLA'        THEN 12
        WHEN nombre = 'SFLB'        THEN 13
        WHEN nombre = 'SFLC'        THEN 14
        WHEN nombre = 'SFLE'        THEN 15
        WHEN nombre = 'SFPC'        THEN 16
        WHEN nombre = 'SFPB'        THEN 17
        WHEN nombre = 'SFPA'        THEN 18
        WHEN nombre = 'STES'        THEN 19
        WHEN nombre = 'STSS'        THEN 20
        WHEN nombre = 'STNS'        THEN 21
        WHEN nombre = 'STHS'        THEN 22
        WHEN nombre = 'STL1'        THEN 23
        WHEN nombre = 'STP1'        THEN 24
        WHEN nombre = 'SUKS'        THEN 25
        WHEN nombre = 'SUMS'        THEN 26
        WHEN nombre = 'SUNS'        THEN 27
        WHEN nombre = 'SUBS'        THEN 28
        WHEN nombre = 'SUJS'        THEN 29
        WHEN nombre = 'SSLN'        THEN 30
        WHEN nombre = 'SSBN'        THEN 31
        WHEN nombre = 'SSFN'        THEN 32
        WHEN nombre = 'HFHF'        THEN 33
        WHEN nombre = 'HRSH'        THEN 34
        WHEN nombre = 'HRDH-C'      THEN 35
        WHEN nombre = 'HRDH-B'      THEN 36
        WHEN nombre = 'HSHS'        THEN 37
        WHEN nombre = 'HFLD-R'      THEN 38
        WHEN nombre = 'HFLD'        THEN 39
        WHEN nombre = 'HFLD-L'      THEN 39
        WHEN nombre = 'HRSL'        THEN 40
        WHEN nombre = 'HRDL-C'      THEN 41
        WHEN nombre = 'HRDL-B'      THEN 42
        WHEN nombre = 'HRTL'        THEN 43
        WHEN nombre = 'HRTL-BLH'    THEN 44
        WHEN nombre = 'HRTL-BR'     THEN 45
        WHEN nombre = 'HSLS'        THEN 46
        WHEN nombre = 'HRTL-BL'     THEN 47
        WHEN nombre = 'HRTL-C'      THEN 48

    END";

    private $modelosHiace = [
        'HFHF',
        'HRSH',
        'HRDH-C',
        'HRDH-B',
        'HSHS',
        'HFLD-R',
        'HFLD-L',
    ];

    private $modelosHiaceOmitir = [
        // 'HFHF',
        // 'HRSH',
        // 'HRDH-C',
        // 'HRDH-B',
        // 'HSHS',
        'HFLD-R',
        'HFLD-L',
        'HRDL-B',
        'HRDL-C',
        'HRSL',
        'HSLS',
        'HRTL-C',
        'HRTL-BL',
        'HRTL-BR',
        'HFLD',
    ];

    private $posicionDespachos;
    private $posicionCuarentena;
    private $posicionBorrador;

    public function __construct() {
        $this->posicionDespachos = Ubicaciones::select('id')->where('deposito_id', HttpDepositos::DESPACHOS)->first();
        $this->posicionCuarentena = Ubicaciones::select('id')->where('deposito_id', HttpDepositos::RECHAZOS)->first();
        $this->posicionBorrador = Ubicaciones::select('id')->where('deposito_id', HttpDepositos::ENVIOS)->first();
    }

    public function getInfoKanban($kanban) {

        $stock = StockKanbans::where('ref', $kanban)->first();

        return $this->setResponse($stock ? $stock->toArray() : []);
    }

    public function moverDollyATemp() {

        //Verifico si hay user id
        try {
            $userId = auth()->guard('api')->user()->id;
        } catch (\Throwable $th) {
            //throw $th;
            $userId = null;
        }
        $dollies = StockKanbans::where('deposito_id', 9)->get();

        $stockService = new StockService();
        $stockService->generaMovimiento(WmsUnidades::KANBAN, $userId);
        foreach ($dollies as $dolly) {

            $stockService->insertaDetalle(
                $dolly->ref,
                floatval($dolly->cantidad) * -1,
                intval($dolly->ubicacion_id),
                $dolly->lote,
                $dolly->unidad_id,
                null
            );

            $stockService->insertaDetalle(
                $dolly->ref,
                floatval($dolly->cantidad),
                1451,
                $dolly->lote,
                $dolly->unidad_id,
                null
            );
        }

        return $this->setResponse([]);
    }

    public function andonCamiones() {
        // $fecha = date('Y-m-d');
        // $fecha = '2025-02-20';
        // $fechaSiguiente = '2025-02-21';
        $fecha = new DateTime();
        $fechaSiguiente = new DateTime();

        $dayOfWeek = $fechaSiguiente->format('N');
        if ($dayOfWeek == DaysOfWeek::VIERNES) {
            $fechaSiguiente->add(new DateInterval('P3D'));
        } else {
            $fechaSiguiente->add(new DateInterval('P1D'));
        }

        $response = [];

        $modelos = Modelos::select('id', 'nombre', 'consumo', 'cantidad')
            ->with('fila')
            ->where('activo', 1)
            // ->whereNotIn('nombre', ['HFLD-R', 'HFLD-L', 'HRSL', 'REAR-C', 'REAR-B', 'HRDL-C', 'HRDL-B', 'HRTL-BR', 'HSLS', 'HRTL-BL', 'HRTL-C'])
            ->orderByRaw($this->orderRawModelos)
            ->get();

        $despachos = Despachos::select('id', 'run', 'fecha_salida_camion', 'fecha_llegada_camion')
            ->where('fecha', $fecha->format('Y-m-d'))
            ->orderBy('run', 'ASC')->get()
            ->toArray();

        $despachoSiguiente = Despachos::select('id', 'run', 'fecha_salida_camion', 'fecha_llegada_camion')
            ->where('fecha', $fechaSiguiente->format('Y-m-d'))
            ->where('run', 1)
            ->orderBy('run', 'ASC')->get()
            ->toArray();

        foreach ($modelos as $modelo) {

            $runs = DespachosItems::selectRaw('COUNT(*) as cantidad, despachos.run, pickeado')
                ->leftJoin('despachos', 'despachos.id', '=', 'despacho_id')
                ->where(function ($q) use ($fecha) {
                    $q->where('despachos.fecha', $fecha->format('Y-m-d'));
                })
                ->where('modelo', $modelo->nombre)
                ->groupBy('despachos.run', 'pickeado')
                ->orderBy('despachos.run', 'asc')
                ->get();

            $nextRun = DespachosItems::selectRaw('COUNT(*) as cantidad')
                ->leftJoin('despachos', 'despachos.id', '=', 'despacho_id')
                ->where('despachos.fecha', $fechaSiguiente->format('Y-m-d'))
                ->where('run', 1)
                ->where('modelo', $modelo->nombre)
                ->first();


            $nextRunPickeado = DespachosItems::selectRaw('COUNT(*) as cantidad')
                ->leftJoin('despachos', 'despachos.id', '=', 'despacho_id')
                ->where('despachos.fecha', $fechaSiguiente->format('Y-m-d'))
                ->where('run', 1)
                ->where('modelo', $modelo->nombre)
                ->where('pickeado', true)
                ->first();
            // Log::alert($nextRun);

            $run1 = 0;
            $run2 = 0;
            $run3 = 0;
            $run4 = 0;
            $run1Pickeado = 0;
            $run2Pickeado = 0;
            $run3Pickeado = 0;
            $run4Pickeado = 0;

            $stockDollys = StockService::getStockModelo($modelo->nombre, HttpDepositos::DOLLYS);
            $stockSeguridad = StockService::getStockModelo($modelo->nombre, HttpDepositos::RACKS);
            $stockSeguridad2 = StockService::getStockModelo($modelo->nombre, HttpDepositos::TEMPORAL_A);

            foreach ($runs as $run) {
                if ($run->run == "1") {
                    if ($run->pickeado) {
                        $run1Pickeado = $run->cantidad;
                    }
                    $run1 = $run1 + $run->cantidad;
                } else if ($run->run == "2") {
                    if ($run->pickeado) {
                        $run2Pickeado = $run->cantidad;
                    }
                    $run2 = $run2 + $run->cantidad;
                } else if ($run->run == "3") {
                    if ($run->pickeado) {
                        $run3Pickeado = $run->cantidad;
                    }
                    $run3 = $run3 +  $run->cantidad;
                } else if ($run->run == "4") {
                    if ($run->pickeado) {
                        $run4Pickeado = $run->cantidad;
                    }
                    $run4 = $run4 + $run->cantidad;
                }
            }

            $item = [
                'run1_siguiente'            => $nextRun ? $nextRun->cantidad : 0,
                'run1_siguiente_pickeado'   => $nextRunPickeado ? $nextRunPickeado->cantidad : 0,
                'modelo'                    => $modelo->nombre,
                'run1'                      => $run1,
                'run1Pickeado'              => $run1Pickeado,
                'run2Pickeado'              => $run2Pickeado,
                'run3Pickeado'              => $run3Pickeado,
                'run4Pickeado'              => $run4Pickeado,
                'run2'                      => $run2,
                'run3'                      => $run3,
                'run4'                      => $run4,
                'pickeados'                 => 0,
                'total'                     => $run1 + $run2 + $run3 + $run4,
                'stock'                     => $stockDollys,
                'stockSeguridad'            => $stockSeguridad + $stockSeguridad2,
                'target'                    => $modelo->cantidad > 0 ? ceil(($modelo->consumo * 1) / $modelo->cantidad) : 1,
            ];

            array_push($response, $item);
        }

        $hayRun4 = Despachos::select('id')->where('fecha', $fecha->format('Y-m-d'))->where('run', 4)->first();

        return $this->setResponse(['despachos_siguiente' => $despachoSiguiente, 'modelos' => $response, 'despachos' => $despachos, 'hayRun4' => $hayRun4 ? true : false]);
    }

    public function reporte(Request $request) {
        //UTILIZADO PARA REPORTAR STOCK
        //OBTENGO LAS POSICIONES, Y SU CONTENIDO

        $depositoId     = $request?->deposito;
        $proveedorId    = $request?->proveedor;
        $materialId     = $request?->material;
        $ubicacion      = $request?->ubicacion;
        $modeloId       = $request?->modelo;
        $kanban         = $request?->kanban;
        $tipo           = $request?->tipo;
        $consolidado    = $request?->consolidado;
        $lote           = $request?->lote;
        $elemento       = $request?->elemento;

        if ($ubicacion) {
            $ubicacion = str_replace("'", "-", $ubicacion);
        }

        if ($tipo == WmsUnidades::KANBAN) {
            $data = StockKanbans::with(['FgDataSar', 'FgData'])->selectRaw('*')
                ->rightJoin('ubicaciones', 'ubicaciones.id', '=', 'ubicacion_id')
                ->when(!empty($depositoId), function ($q) use ($depositoId) {
                    $q->where('ubicaciones.deposito_id', $depositoId);
                })
                ->when(!empty($ubicacion), function ($q) use ($ubicacion) {
                    $q->whereIn('ubicaciones.nombre', PositionName::candidates($ubicacion));
                })
                ->when(!empty($modeloId), function ($q) use ($modeloId) {
                    $q->where('modelo_id', $modeloId);
                })
                ->when(!empty($kanban), function ($q) use ($kanban) {
                    $q->where('ref', $kanban);
                })
                ->where('ubicaciones.habilitada', true)
                ->when(!empty($modeloId), function ($q) {
                    $q->orderBy('ref', 'ASC');
                })
                ->when(empty($modeloId), function ($q) {
                    $q->orderBy('ubicaciones.nombre', 'ASC');
                })
                ->where(function ($q) {
                    // Keep free positions from RIGHT JOIN (NULL unidad_id) visible in report.
                    $q->where('unidad_id', WmsUnidades::KANBAN)
                        ->orWhereNull('unidad_id');
                })
                ->where('ubicaciones.deposito_id', '!=', HttpDepositos::ENVIOS)
                ->get();
        } else if ($tipo == WmsUnidades::MATERIAL) {

            $query = StockMateriales::leftJoin('ubicaciones', 'ubicaciones.id', '=', 'ubicacion_id')
                ->when(!empty($depositoId), function ($q) use ($depositoId) {
                    $q->where('ubicaciones.deposito_id', $depositoId);
                })
                ->when(!empty($ubicacion), function ($q) use ($ubicacion) {
                    $q->whereIn('ubicacion', PositionName::candidates($ubicacion));
                })
                ->when(!empty($proveedorId), function ($q) use ($proveedorId) {
                    $q->where('proveedor_id', $proveedorId);
                })
                ->when(!empty($materialId), function ($q) use ($materialId) {
                    $q->where('codigo', $materialId);
                })
                ->when(!empty($lote), function ($q) use ($lote) {
                    $q->where('lote', 'like', '%' . $lote . '%');
                })
                ->where('ubicaciones.habilitada', true);


            if ($consolidado) {
                $query = $query->selectRaw('SUM(cantidad) as cantidad,codigo,material,proveedor,deposito')
                    ->groupBy('codigo')
                    ->groupBy('material')
                    ->groupBy('proveedor')
                    ->groupBy('deposito');
            }

            $data = $query->get();
        } else {
            $query = StockHRDTAR::leftJoin('ubicaciones', 'ubicaciones.id', '=', 'ubicacion_id')
                ->when(!empty($depositoId), function ($q) use ($depositoId) {
                    $q->where('ubicaciones.deposito_id', $depositoId);
                })
                ->when(!empty($ubicacion), function ($q) use ($ubicacion) {
                    $q->whereIn('ubicacion', PositionName::candidates($ubicacion));
                })
                ->when(!empty($proveedorId), function ($q) use ($proveedorId) {
                    $q->where('proveedor_id', $proveedorId);
                })
                ->when(!empty($elemento), function ($q) use ($elemento) {
                    $q->where('ref', $elemento);
                })
                ->where('unidad_id', $tipo)
                ->where('ubicaciones.habilitada', true);

            $query = $query->selectRaw('SUM(cantidad) as cantidad,descripcion,ref,deposito,unidad')
                ->groupBy('ref')
                ->groupBy('descripcion')
                ->groupBy('unidad')
                ->groupBy('deposito');


            $data = $query->get();
        }

        $response = [
            'stock'         => $data ? $data->toArray() : [],
            // 'depositoInfo'  => $dataDeposito ? $dataDeposito->toArray() : []
        ];

        return $this->setResponse($response);
    }

    public function registrarMovimientoMateriales(Request $request) {
        $lineas = $request->input('materiales');

        if (!is_array($lineas)) {
            $lineas = [[
                'material_id' => $request->input('material_id'),
                'cantidad'    => $request->input('cantidad')
            ]];
        }

        $tipo = strtolower(trim(strval($request->input('tipo', ''))));
        $motivo = trim(strval($request->input('motivo', '')));

        if (!in_array($tipo, ['ingreso', 'egreso'])) {
            return $this->setResponse([], 'Debe informar un tipo de movimiento valido.', true, 422);
        }

        if ($motivo === '') {
            return $this->setResponse([], 'Debe informar un motivo.', true, 422);
        }

        if (count($lineas) === 0) {
            return $this->setResponse([], 'Debe informar al menos un material.', true, 422);
        }

        $materialesNormalizados = [];
        foreach ($lineas as $index => $linea) {
            $materialId = intval(data_get($linea, 'material_id'));
            $cantidad = floatval(str_replace(',', '.', data_get($linea, 'cantidad', 0)));

            if ($materialId <= 0) {
                return $this->setResponse([], 'El material del renglon ' . ($index + 1) . ' es invalido.', true, 422);
            }

            if ($cantidad <= 0) {
                return $this->setResponse([], 'La cantidad del renglon ' . ($index + 1) . ' debe ser mayor a cero.', true, 422);
            }

            if (!isset($materialesNormalizados[$materialId])) {
                $materialesNormalizados[$materialId] = 0;
            }

            $materialesNormalizados[$materialId] += $cantidad;
        }

        $posicionMateriales = Ubicaciones::where('deposito_id', HttpDepositos::MATERIALES)
            ->where('habilitada', true)
            ->first();

        if (!$posicionMateriales) {
            return $this->setResponse([], 'No existe una posicion de materiales configurada.', true);
        }

        $materiales = MaterialesPiezas::whereIn('id', array_keys($materialesNormalizados))
            ->get()
            ->keyBy('id');

        foreach ($materialesNormalizados as $materialId => $cantidad) {
            $material = $materiales->get($materialId);

            if (!$material) {
                return $this->setResponse([], 'No se encontro el material #' . $materialId . '.', true, 404);
            }

            if ($tipo === 'egreso') {
                $stockActual = floatval(StockMateriales::where('ref', $material->codigo)->sum('cantidad'));
                if ($stockActual < $cantidad) {
                    return $this->setResponse([], 'Stock insuficiente para ' . $material->codigo . '. Disponible: ' . $stockActual . '.', true, 422);
                }
            }
        }

        DB::beginTransaction();

        try {
            $userId = auth()->guard('api')->id();

            $movimiento = Movimientos::create([
                'unidad_id'     => WmsUnidades::MATERIAL,
                'ubicacion_id'  => null,
                'finalizado'    => true,
                'user_id'       => $userId ? intval($userId) : null,
                'motivo'        => $motivo
            ]);

            foreach ($materialesNormalizados as $materialId => $cantidad) {
                $material = $materiales->get($materialId);

                MovimientosContenido::create([
                    'movimiento_id'     => $movimiento->id,
                    'ref'               => $material->codigo,
                    'cantidad'          => $tipo === 'egreso' ? $cantidad * -1 : $cantidad,
                    'ubicacion_id'      => intval($posicionMateriales->id),
                    'unidad_id'         => WmsUnidades::MATERIAL,
                    'lote'              => null,
                    'ref_id'            => null
                ]);
            }

            DB::commit();

            return $this->setResponse([
                'movimiento_id' => $movimiento->id,
                'tipo'          => $tipo,
                'motivo'        => $motivo,
                'materiales'    => collect($materialesNormalizados)->map(function ($cantidad, $materialId) use ($materiales) {
                    $material = $materiales->get($materialId);
                    return [
                        'material_id' => intval($materialId),
                        'codigo'      => $material->codigo,
                        'cantidad'    => $cantidad
                    ];
                })->values()->toArray()
            ], 'Movimiento registrado correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("MovimientosController::registrarMovimientoMateriales : " . $th->getMessage());
            return $this->setResponse([], 'Ocurrio un error al registrar el movimiento de materiales.', true);
        }
    }

    public function getMaterialesARetenerQC() {
        return $this->setResponse(MaterialesRetenerService::getMateriales());
    }

    public function agregarMaterialesARetenerQC(Request $request) {

        $materialService = new MaterialesRetenerService();
        $materialService->agregarMaterialARetener($request);

        return $this->setResponse([], $materialService->message, $materialService->hayError);
    }

    public function eliminaMaterialARetenerQC($id) {
        MaterialesRetenerService::eliminarMaterialARetener($id);
        return $this->setResponse([], "Eliminado correctamente");
    }

    public function getMaterialesEnCuarentena() {

        $data = StockMateriales::with('referencia')->where('deposito_id', HttpDepositos::RECHAZOS)->get();

        if ($data) {
            return $this->setResponse($data->toArray());
        } else {
            return $this->setResponse([]);
        }
    }

    public function moverMaterialesEnCuarentena(Request $request) {

        $lote = $request->lote;
        $metros = $request->metros;
        $codigo = $request->codigo;
        $depositoDestino = $request->depositoDestino;
        $refId = $request?->refId;
        $userId = auth()->guard('api')->user()->id;

        $stockService = new StockService;
        $posicionDestino = $stockService->obtenerPosicionDefault(intval($depositoDestino));

        if ($refId) {
            $materialMovimiento = MovimientosContenido::where('ref_id', $refId)
                ->where('cantidad', '>', 0)
                ->first();
        } else {
            $materialMovimiento = MovimientosContenido::where('lote', $lote)
                ->where('cantidad', $metros)
                ->where('ref', $codigo)
                ->where('cantidad', '>', 0)
                ->first();
        }

        if ($materialMovimiento) {

            $stockService->generaMovimiento($materialMovimiento->unidad_id, $userId);

            $stockService->insertaDetalle(
                $materialMovimiento->ref,
                floatval($materialMovimiento->cantidad),
                $posicionDestino->id,
                $materialMovimiento->lote,
                $materialMovimiento->unidad_id,
                $refId
            );

            $stockService->insertaDetalle(
                $materialMovimiento->ref,
                floatval($materialMovimiento->cantidad) * -1,
                intval($materialMovimiento->ubicacion_id),
                $materialMovimiento->lote,
                $materialMovimiento->unidad_id,
                $refId
            );

            return $this->setResponse([]);
        } else {
            return $this->setResponse([], 'No se encontro el material', true);
        }
    }

    public function liberarMaterialRechazado(Request $request) {

        // Log::alert($request);

        $codigoEscaneado = $request->codigo ?? $request->codigo_escaneado ?? $request->qr;
        $userId = $request->user1 ?? auth()->guard('api')->id();
        $rechazado = filter_var($request->rechazado ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($codigoEscaneado)) {
            return $this->setResponse([], 'Debe informar el código escaneado.', true);
        }

        $datosCodigo = RollosService::obtieneInterfazProveedorPorQR($codigoEscaneado);

        // Log::alert($datosCodigo);

        if (!$datosCodigo || empty($datosCodigo['material'])) {
            return $this->setResponse([], 'No se encontró información del material para el código escaneado.', true);
        }

        $material = $datosCodigo['material'];
        $lote = $datosCodigo['lote'] ?? null;
        $camposInterfaz = $datosCodigo['campos_interfaz'] ?? [];

        if (empty($camposInterfaz)) {
            return $this->setResponse([], 'No se pudo identificar la interfaz del proveedor.', true);
        }

        $posicionRechazo = Ubicaciones::where('deposito_id', HttpDepositos::RECHAZOS)
            ->where('habilitada', true)
            ->first();

        if (!$posicionRechazo) {
            return $this->setResponse([], 'No existe una posición de rechazos configurada.', true);
        }

        $stockService = new StockService();
        $posicionDestino = $rechazado
            ? $stockService->obtenerPosicionDefault(HttpDepositos::ENVIOS) //Ubicaciones::where('id', 11451)->first()
            : $stockService->obtenerPosicionDefault(HttpDepositos::MATERIALES);

        if (!$posicionDestino) {
            return $this->setResponse([], $rechazado ? 'No existe la posicion de despachos configurada.' : 'No existe una posicion de materiales configurada.', true);
        }

        $stockQuery = StockMateriales::where('ref', $material->codigo)
            ->where('ubicacion_id', $posicionRechazo->id)
            ->where('cantidad', '>', 0);

        if (in_array('ML', $camposInterfaz)) {
            $cantidad = $this->getCantidadMaterialLiberado($datosCodigo);

            if ($cantidad <= 0) {
                return $this->setResponse([], 'El codigo escaneado no informa una cantidad valida.', true);
            }

            $stockQuery->whereRaw('ABS(cantidad - ?) < 0.0001', [$cantidad]);
        }

        if (in_array('LOTE', $camposInterfaz)) {
            if (empty($lote)) {
                return $this->setResponse([], 'El codigo escaneado no informa lote.', true);
            }

            $stockQuery->where('lote', $lote);
        }

        $stock = $stockQuery
            ->whereHas('referencia', function ($q) use ($datosCodigo, $material, $camposInterfaz) {
                if (in_array('COD', $camposInterfaz)) {
                    $q->where('proveedor_id', $material->proveedor_id)
                        ->where('material_id', $material->id)
                        ->where('codigo_sar', $material->codigo);
                }

                $this->aplicarFiltrosInterfazRegistro($q, $datosCodigo, $camposInterfaz);
            })
            ->first();

        if (!$stock) {
            return $this->setResponse([], 'No se encontró stock en cuarentena que coincida con el código escaneado.', true);
        }

        DB::beginTransaction();

        try {
            $movimiento = Movimientos::create([
                'unidad_id'     => WmsUnidades::MATERIAL,
                'ubicacion_id'  => null,
                'finalizado'    => true,
                'user_id'       => $userId ? intval($userId) : null
            ]);

            if (!$movimiento) {
                DB::rollBack();
                return $this->setResponse([], 'No se pudo registrar el movimiento.', true);
            }

            $ref = $stock->ref ?? $material->codigo;
            $cantidadStock = floatval($stock->cantidad);
            $loteStock = $stock->lote ?? $lote;
            $refId = $stock->ref_id ?? null;

            MovimientosContenido::create([
                'movimiento_id'     => $movimiento->id,
                'ref'               => $ref,
                'cantidad'          => $cantidadStock * -1,
                'ubicacion_id'      => intval($stock->ubicacion_id),
                'unidad_id'         => WmsUnidades::MATERIAL,
                'lote'              => $loteStock,
                'ref_id'            => $refId
            ]);

            MovimientosContenido::create([
                'movimiento_id'     => $movimiento->id,
                'ref'               => $ref,
                'cantidad'          => $cantidadStock,
                'ubicacion_id'      => intval($posicionDestino->id),
                'unidad_id'         => WmsUnidades::MATERIAL,
                'lote'              => $loteStock,
                'ref_id'            => $refId
            ]);

            DB::commit();

            return $this->setResponse([
                'movimiento_id'      => $movimiento->id,
                'codigo'             => $ref,
                'lote'               => $loteStock,
                'cantidad'           => $cantidadStock,
                'ubicacion_origen'   => intval($stock->ubicacion_id),
                'ubicacion_destino'  => intval($posicionDestino->id),
                'rechazado'          => $rechazado,
            ], $rechazado ? 'Material rechazado correctamente.' : 'Material liberado correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("MovimientosController::liberarMaterialRechazado : " . $th->getMessage());
            return $this->setResponse([], 'Ocurrió un error al liberar el material rechazado.', true);
        }
    }

    private function getCantidadMaterialLiberado(array $datosCodigo): float {
        $unidad = $datosCodigo['unidad'] ?? null;
        $material = $datosCodigo['material'] ?? null;

        if ($unidad == \App\Services\Unidades::BULTO->value || $unidad == \App\Services\Unidades::CAJA->value) {
            return (is_null($material->lote) || $material->lote == '') ? 1 : floatval($material->lote);
        }

        $cantidad = floatval($datosCodigo['cantidad'] ?? 0);

        return $cantidad == 0 ? 1 : $cantidad;
    }

    private function aplicarFiltrosInterfazRegistro($query, array $datosCodigo, array $camposInterfaz): void {
        $camposValidables = [
            'LOTE'  => ['key' => 'lote', 'column' => 'lote', 'type' => 'string'],
            'MB'    => ['key' => 'mt_bruto', 'column' => 'mt_bruto', 'type' => 'float'],
            'PB'    => ['key' => 'pe_bruto', 'column' => 'peso_bruto', 'type' => 'float'],
            'PL'    => ['key' => 'pe_liquido', 'column' => 'peso_liquido', 'type' => 'float'],
            'OTRO'  => ['key' => 'otros', 'column' => 'otros', 'type' => 'string'],
            'FL'    => ['key' => 'fallas', 'column' => 'fallas', 'type' => 'int'],
        ];

        foreach ($camposInterfaz as $campo) {
            if (!isset($camposValidables[$campo])) {
                continue;
            }

            $validacion = $camposValidables[$campo];
            if (!Schema::hasColumn('registro_ingreso_rollos', $validacion['column'])) {
                continue;
            }

            $valor = $validacion['value'] ?? ($datosCodigo[$validacion['key']] ?? null);

            if ($validacion['type'] == 'float') {
                $query->whereRaw('ABS(' . $validacion['column'] . ' - ?) < 0.0001', [floatval($valor)]);
            } else if ($validacion['type'] == 'int') {
                $query->where($validacion['column'], intval($valor));
            } else {
                $query->where($validacion['column'], $valor);
            }
        }
    }

    private function getStockFromArray($datos, $modeloBuscado, $depositoBuscado) {

        // Filtrar los resultados
        $resultado = array_filter($datos, function ($item) use ($modeloBuscado, $depositoBuscado) {
            return $item['modelo'] === $modeloBuscado && intval($item['deposito_id']) === $depositoBuscado;
        });

        // if($modeloBuscado == 'SFHG'){
        //     Log::alert($resultado);
        // }

        // Convertir a array indexado
        $resultado = array_values($resultado);

        try {
            if (count($resultado) > 0) {
                return floatval($resultado[0]['cantidad']);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return 0;
        }
    }

    public function getStockKanbans() {

        $fecha = new DateTime();
        $fechaSiguiente = new DateTime();
        $fechaMesAnterior = new DateTime();

        $fechaSiguiente->add(new DateInterval('P1D'));
        $fechaMesAnterior->sub(new DateInterval('P35D'));

        // $stock = MovimientosContenido::selectRaw("sum(wms_movimientos_contenidos.cantidad) as cantidad,modelos.nombre as modelo,isnull(modelos.consumo,1) as consumo")
        //     ->leftJoin('kanbans', 'wms_movimientos_contenidos.ref', 'kanbans.codigo')
        //     ->leftJoin('modelos', 'kanbans.modelo_id', 'modelos.id')
        //     ->where('wms_movimientos_contenidos.unidad_id', WmsUnidades::KANBAN)
        //     ->whereNotIn('modelos.nombre', $this->modelosHiaceOmitir)
        //     ->where(function ($q) {
        //         $q->where('ubicacion_id', '!=', $this->posicionBorrador->id);
        //     })
        //     ->orderByRaw($this->orderRawModelos)
        //     ->groupBy('modelos.nombre', 'modelos.consumo')
        //     ->get();

        $stock = StockKanbans::selectRaw("sum(vt_stock_kanbans.cantidad) as cantidad,modelos.nombre as modelo,isnull(modelos.consumo,1) as consumo")
            ->leftJoin('kanbans', 'vt_stock_kanbans.ref', 'kanbans.codigo')
            ->leftJoin('modelos', 'kanbans.modelo_id', 'modelos.id')
            ->where('vt_stock_kanbans.unidad_id', WmsUnidades::KANBAN)
            ->whereNotIn('modelos.nombre', $this->modelosHiaceOmitir)
            ->where('modelos.consumo', '>', 0)
            ->where(function ($q) {
                $q->where('ubicacion_id', '!=', $this->posicionBorrador->id);
            })
            ->orderByRaw($this->orderRawModelos)
            ->groupBy('modelos.nombre', 'modelos.consumo')
            ->get();


        $stockDepositos = Stock::getStockModeloPorDeposito(null, [HttpDepositos::MATERIALES, HttpDepositos::ARHRDT]);
        // Log::alert($stockDepositos);
        $stockDepositos = json_decode($stockDepositos, true);

        foreach ($stock as $s) {
            // if ($s->modelo == 'SFHG') {
            //     Log::alert($s);
            // }
            $cantidad = 0;
            $modelo = Modelos::with('lineas')->where('nombre', $s->modelo)->first();
            $cantidad = $modelo->cantidad;
            if (is_null($cantidad)) {
                $cantidad = 10;
            }

            $stockRack = $this->getStockFromArray($stockDepositos, $s->modelo, HttpDepositos::RACKS);
            $stockTemporal = $this->getStockFromArray($stockDepositos, $s->modelo, HttpDepositos::TEMPORAL_A);
            $stockCuarentena = $this->getStockFromArray($stockDepositos, $s->modelo, HttpDepositos::RECHAZOS);
            $stockDollys = $this->getStockFromArray($stockDepositos, $s->modelo, HttpDepositos::DOLLYS);
            $stockDespacho = $this->getStockFromArray($stockDepositos, $s->modelo, HttpDepositos::DESPACHOS);

            $cant = $s->cantidad * $cantidad;
            $s->cantidad = number_format($cant, 2);
            $s->sets = $cantidad;

            if (is_null($s->consumo)) {
                $s->consumo = 0;
                $s->stock = $s->cantidad;

                $s->stockRack = 0;
                $s->stockDollys = 0;
                $s->stockDespacho = 0;
                $s->stockTemporal = 0;
                // $s->stockCuarentena = 0;
            } else {
                if (floatval($s->consumo) > 0) {
                    // if ($s->modelo == 'SFHG') {
                    //     Log::alert($stockCuarentena);
                    //     Log::alert($cant);
                    // }
                    $s->stock = (floatval($cant) - ($stockCuarentena * $cantidad)) / floatval($s->consumo);

                    $s->stockDiasRack = number_format((floatval($stockRack) * $cantidad) / floatval($s->consumo), 2);
                    $s->stockDiasDollys = number_format((floatval($stockDollys) * $cantidad) / floatval($s->consumo), 2);
                    $s->stockDiasDespacho = number_format((floatval($stockDespacho) * $cantidad) / floatval($s->consumo), 2);
                    $s->stockDiasTemporal = number_format((floatval($stockTemporal) * $cantidad) / floatval($s->consumo), 2);
                    $s->stockDiasCuarentena = number_format((floatval($stockCuarentena) * $cantidad) / floatval($s->consumo), 2);
                } else {
                    $s->stock = 0;
                    $s->stockDiasRack = 0;
                    $s->stockDiasDollys = 0;
                    $s->stockDiasDespacho = 0;
                    $s->stockDiasTemporal = 0;
                    $s->stockDiasCuarentena = 0;
                }
            }

            $movimientosDelDia = MovimientosStockKanbans::select(['cantidad', 'deposito', 'created_at'])
                ->where('modelo', $s->modelo)
                ->where(function ($s) use ($fecha, $fechaSiguiente) {
                    // $s->where(function ($q) use ($fecha, $fechaSiguiente) {
                    $s->where('created_at', '>=', $fecha->format('Y-m-d') . ' 06:00:00');
                    $s->where('created_at', '<=', $fechaSiguiente->format('Y-m-d') . ' 00:50:00');
                    // });
                })
                ->where(function ($q) {
                    $q->where(function ($w) {
                        $w->where('cantidad', '<', 0);
                        $w->where('ubicacion_id', $this->posicionDespachos->id);
                    });
                    $q->orWhere(function ($w) {
                        $w->where('cantidad', '>', 0);
                        $w->where('ubicacion_id', 9);
                    });
                })
                ->orderBy('created_at', 'ASC')
                ->get();


            $s->vehiculo = LineasModelo::VEHICULOS[$s->modelo];
            if ($s->stock < 0) {
                $s->stock = 0;
            } else {
                $s->stock = number_format($s->stock, 2);
            }

            $s->stockRack = number_format($stockRack * $cantidad, 0);
            $s->stockDollys = number_format($stockDollys * $cantidad, 0);
            $s->stockDespacho = number_format($stockDespacho * $cantidad, 0);
            $s->stockTemporal = number_format($stockTemporal * $cantidad, 0);
            $s->stockCuarentena = number_format($stockCuarentena * $cantidad, 0);
            $s->consumo = number_format($s->consumo, 1);
            $s->movimientos = $movimientosDelDia ? $movimientosDelDia->toArray() : [];


            if (!is_null($modelo->lineas)) {
                $s->linea = $modelo->lineas[0]->id;
            } else {
                $s->linea = 9999;
            }
        }

        //EVALUO HIACE
        // $stockHiace = MovimientosContenido::selectRaw("sum(wms_movimientos_contenidos.cantidad) as cantidad,modelos.nombre as modelo,isnull(modelos.consumo,1) as consumo")
        //     ->leftJoin('kanbans', 'wms_movimientos_contenidos.ref', 'kanbans.codigo')
        //     ->leftJoin('modelos', 'kanbans.modelo_id', 'modelos.id')
        //     ->where('wms_movimientos_contenidos.unidad_id', WmsUnidades::KANBAN)
        //     ->whereIn('modelos.nombre', $this->modelosHiace)
        //     ->groupBy('modelos.nombre', 'modelos.consumo')
        //     ->where(function ($q) {
        //         $q->where('ubicacion_id', '!=', $this->posicionBorrador->id);
        //     })
        //     ->orderByRaw($this->orderRawModelos)
        //     ->get();

        $stockHiace = StockKanbans::selectRaw("sum(vt_stock_kanbans.cantidad) as cantidad,modelos.nombre as modelo,isnull(modelos.consumo,1) as consumo")
            ->leftJoin('kanbans', 'vt_stock_kanbans.ref', 'kanbans.codigo')
            ->leftJoin('modelos', 'kanbans.modelo_id', 'modelos.id')
            ->where('vt_stock_kanbans.unidad_id', WmsUnidades::KANBAN)
            ->whereIn('modelos.nombre', $this->modelosHiaceOmitir)
            ->where('modelos.consumo', '>', 0)
            ->where(function ($q) {
                $q->where('ubicacion_id', '!=', $this->posicionBorrador->id);
            })
            ->orderByRaw($this->orderRawModelos)
            ->groupBy('modelos.nombre', 'modelos.consumo')
            ->get();

        foreach ($stockHiace as $s) {

            $cantidad = 0;
            $modelo = Modelos::where('nombre', $s->modelo)->first();
            $cantidad = $modelo->cantidad;
            if (is_null($cantidad)) {
                $cantidad = 10;
            }

            $s->sets = $cantidad;

            $s->cantidad = number_format($s->cantidad * $cantidad, 2);

            $stockRack = $this->getStockFromArray($stockDepositos, $s->modelo, HttpDepositos::RACKS);
            $stockTemporal = $this->getStockFromArray($stockDepositos, $s->modelo, HttpDepositos::TEMPORAL_A);
            $stockCuarentena = $this->getStockFromArray($stockDepositos, $s->modelo, HttpDepositos::RECHAZOS);
            $stockDollys = $this->getStockFromArray($stockDepositos, $s->modelo, HttpDepositos::DOLLYS);
            $stockDespacho = $this->getStockFromArray($stockDepositos, $s->modelo, HttpDepositos::DESPACHOS);


            if (is_null($s->consumo)) {
                $s->consumo = 0;
                $s->stock = $s->cantidad;

                $s->stockRack = 0;
                $s->stockDollys = 0;
                $s->stockDespacho = 0;
                $s->stockTemporal = 0;
                $s->stockCuarentena = 0;
            } else {
                if (floatval($s->consumo) > 0) {
                    // $s->stock = (floatval($cant) - ($stockCuarentena * $cantidad)) / floatval($s->consumo);

                    $s->stock = number_format((floatval($s->cantidad) - floatval($stockCuarentena  * $cantidad)) / floatval($s->consumo), 2);

                    $s->stockDiasRack = number_format(floatval($stockRack * $cantidad) / floatval($s->consumo), 1);
                    $s->stockDiasDollys = number_format(floatval($stockDollys  * $cantidad) / floatval($s->consumo), 1);
                    $s->stockDiasDespacho = number_format(floatval($stockDespacho  * $cantidad) / floatval($s->consumo), 1);
                    $s->stockDiasTemporal = number_format(floatval($stockTemporal  * $cantidad) / floatval($s->consumo), 1);
                    $s->stockDiasCuarentena = number_format(floatval($stockCuarentena  * $cantidad) / floatval($s->consumo), 1);
                } else {
                    $s->stock = 0;
                    $s->stockDiasRack = 0;
                    $s->stockDiasDollys = 0;
                    $s->stockDiasDespacho = 0;
                    $s->stockDiasTemporal = 0;
                    $s->stockDiasCuarentena = 0;
                }
            }

            $movimientosDelDia = MovimientosStockKanbans::select(['cantidad', 'deposito', 'created_at'])
                ->where('modelo', $s->modelo)
                ->where(function ($s) use ($fecha, $fechaSiguiente) {
                    $s->where('created_at', '>=', $fecha->format('Y-m-d') . ' 06:00:00');
                    $s->where('created_at', '<=', $fechaSiguiente->format('Y-m-d') . ' 00:50:00');
                })
                ->where(function ($q) {
                    $q->where(function ($w) {
                        $w->where('cantidad', '<', 0);
                        $w->where('ubicacion_id', $this->posicionDespachos->id);
                    });
                    $q->orWhere(function ($w) {
                        $w->where('cantidad', '>', 0);
                        $w->where('ubicacion_id', 9);
                    });
                })
                ->orderBy('created_at', 'ASC')
                ->get();

            $s->vehiculo = LineasModelo::VEHICULOS[$s->modelo];
            if ($s->stock < 0) {
                $s->stock = 0;
            } else {
                $s->stock = number_format($s->stock, 2);
            }

            $s->consumo = number_format($s->consumo, 2);
            $s->stockRack = number_format($stockRack * $cantidad, 2);
            $s->stockDollys = number_format($stockDollys * $cantidad, 2);
            $s->stockDespacho = number_format($stockDespacho * $cantidad, 2);
            $s->stockTemporal = number_format($stockTemporal * $cantidad, 2);
            $s->stockCuarentena = number_format($stockCuarentena * $cantidad, 2);
            $s->movimientos = $movimientosDelDia ? $movimientosDelDia->toArray() : [];
        }

        $stockFilas = StockFilaTemp::selectRaw('sum(stock)/sum(consumo) as stock,fila,fecha')
            ->where('fecha', '>=', $fechaMesAnterior->format('Y-m-d'))
            ->groupBy('fila')->groupBy('fecha')
            ->orderBy('fecha')->orderBy('fila')
            ->get();

        $fechaMesesAnteriores = new DateTime();
        $fechaMesesAnteriores->sub(new DateInterval('P6M'));

        $stockFilasMes = StockFilaTemp::selectRaw('sum(stock)/sum(consumo) as stock,fila,fecha')
            ->where('fecha', '>=', $fechaMesesAnteriores->format('Y-m-d'))
            ->groupBy('fila')->groupBy('fecha')
            ->orderBy('fecha')->orderBy('fila')
            ->get();

        $stockFinal = [
            'stock'     => $stock,
            'hiace'     => $stockHiace,
            'filas'     => $stockFilas,
            'filas_mes' => $stockFilasMes
        ];

        return $this->setResponse($stockFinal);
    }

    private function getFreePosition($depositoId, $volumenRequerido) {
        //Busco una posición libre para el producto/unidad que requieren

        if ($depositoId == HttpDepositos::TEMPORAL_A) {
            $ubicacion = Ubicaciones::where('deposito_id', HttpDepositos::TEMPORAL_A)->first();
            return $ubicacion;
        }

        //Obtengo todas las ubicaciónes habilitadas del deposito
        $ubicaciones = Ubicaciones::with('contenido.detalle.unidad')->where('deposito_id', $depositoId)->where('habilitada', true)->get();

        foreach ($ubicaciones as $ubicacion) {
            //Por cada ubicación, obtengo la ocupación
            //Capacidad de la posicion
            $capacidad = $ubicacion->capacidad;

            //Ocupación de la posición
            $ocupacion = 0;
            try {
                foreach ($ubicacion->contenido as $c) {
                    foreach ($c->detalle as $d) {
                        if (!is_null($d->unidad)) {
                            if ($d->cantidad > 0) {
                                $ocupacion = $ocupacion + $d->unidad->volumen;
                            } else {
                                $ocupacion = $ocupacion - $d->unidad->volumen;
                            }
                        }
                    }
                }
            } catch (\Throwable $th) {
                Log::error("MovimientosController::getFreePosition : " . $th->getMessage());
                $ocupacion = 0;
            }

            $disponible = $capacidad - $ocupacion;

            if ($disponible >= $volumenRequerido) {
                //TOOMO LA POSICION PARA ALMACENAR
                return $ubicacion;
            }
        }

        return null;
    }

    public function store(Request $request) {
        $posicionId = $request->posicionId;
        $depositoId = $request->deposito;
        $asignacionAutomatica = boolval($request->automatico);
        $unidadId = $request->unidad;
        $items = $request->items;
        $esPositionName = $request->positionName;
        $userId = $request->user1;
        $userId2 = $request->user2;
        $siExisteMueve = $request?->si_existe_mueve;

        // Log::alert($request);
        // return $this->setResponse([]);

        if (is_null($items)) {
            $items = [];
        }

        if (is_null($unidadId)) {
            $unidadId = WmsUnidades::KANBAN;
        }

        DB::beginTransaction();

        $payload = [
            'unidad_id'     => $unidadId,
            'ubicacion_id'  => null,
            'finalizado'    => false,
            'user_id'       => $userId,
            'user_id2'      => $userId2
        ];

        $movimiento = Movimientos::create($payload);

        if (!$movimiento) {
            Log::error('Error al crear el movimiento : ' . json_encode($payload));
            return $this->setResponse([], 'Error al crear el movimiento', true);
        }

        try {
            if (count($items) > 0) {
                foreach ($items as $item) {
                    //Verifico si la posicion asignada tiene capacidad
                    if ($asignacionAutomatica == true) {
                        //OBTENGO LA POSICION A ALMACENAR
                        $posicionId = $this->getFreePosition($depositoId, intval($item['cantidad']));
                        // $posicionId = Stock::getFreePosition2($depositoId, WmsUnidades::KANBAN, intval($item['cantidad']));

                        if (!$posicionId) {
                            return $this->setResponse([], 'No hay capacidad suficiente en el deposito', true);
                            DB::rollBack();
                        } else {
                            $posicionId = $posicionId->id;
                        }
                    } else {
                        if ($esPositionName) {
                            $positionCandidates = PositionName::candidates($item['posicion']);
                            $posicion = Ubicaciones::whereIn('nombre', $positionCandidates)
                                ->where('deposito_id', $depositoId)->first();

                            if ($posicion) {
                                $posicionId = $posicion->id;
                            } else {
                                return $this->setResponse([], 'La posición informada no existe', true);
                            }
                        } else {
                            $posicionId = intval($item['posicion']);
                        }
                    }

                    if ($unidadId = WmsUnidades::KANBAN) {
                        //VERIFICO SI EL KANBAN ESTA EN STOCK
                        if (Stock::existeKanbanEnStock($item['ref'])) {
                            // //SI EXISTE VERIFICO SI ES EN DOLLYS, SI ES ASI LO PASO A RACK SOLO POR AHORA
                            // //TODO ELIMINAR ESTO DESPUES
                            // $kExistente = MovimientosContenido::where('ref', $item['ref'])->where('ubicacion_id', 9)->first();
                            // if ($kExistente) {
                            //     DB::beginTransaction();
                            //     try {
                            //         Movimientos::where('id', $kExistente->movimiento_id)->delete();
                            //         MovimientosContenido::where('id', $kExistente->id)->delete();
                            //         DB::commit();
                            //     } catch (\Throwable $th) {
                            //         DB::rollBack();
                            //         return $this->setResponse([], 'Ocurrió un error al eliminar de dollys', true);
                            //     }
                            // }

                            if ($siExisteMueve) {
                                $canStore = Stock::canStoreInPosition($unidadId, $posicionId, intval($item['cantidad']));

                                if ($canStore) {
                                    $materialMovimiento = StockKanbans::where('ref', $item['ref'])->first();

                                    if ($materialMovimiento) {
                                        if (intval($materialMovimiento->ubicacion_id) != $posicionId) {

                                            $stockService = new StockService();

                                            $stockService->generaMovimiento(WmsUnidades::KANBAN, $userId);

                                            $stockService->insertaDetalle(
                                                $materialMovimiento->ref,
                                                floatval($materialMovimiento->cantidad) * -1,
                                                intval($materialMovimiento->ubicacion_id),
                                                $materialMovimiento->lote,
                                                $materialMovimiento->unidad_id,
                                                null
                                            );

                                            $stockService->insertaDetalle(
                                                $materialMovimiento->ref,
                                                floatval($materialMovimiento->cantidad),
                                                $posicionId,
                                                $materialMovimiento->lote,
                                                $materialMovimiento->unidad_id,
                                                null
                                            );

                                            DB::commit();
                                            return $this->setResponse([], 'MOVIDO CORRECTAMENTE');
                                        } else {
                                            DB::rollBack();
                                            return $this->setResponse([], 'YA SE ENCUENTRA EN EL DEPOSITO ACTUAL');
                                        }
                                    }
                                } else {
                                    DB::rollBack();
                                    return $this->setResponse([], 'No hay capacidad suficiente para almacenar los items', true);
                                }
                            } else {
                                DB::rollBack();
                                return $this->setResponse([], 'El kanban ya se encuentra almacenado', true);
                            }
                        }

                        //VALIDO QUE EL KANBAN EXISTA
                        try {
                            $existe = Kanban::registrarKanbanSiNoExiste($item['ref']);
                        } catch (\Throwable $th) {
                            return $this->setResponse([], 'El kanban informado no existe', true);
                        }

                        if (!$existe) {
                            return $this->setResponse([], 'El kanban informado no existe', true);
                        }
                    }

                    $canStore = Stock::canStoreInPosition($unidadId, $posicionId, intval($item['cantidad']));

                    if ($canStore) {
                        MovimientosContenido::create([
                            'movimiento_id'     => $movimiento->id,
                            'ref'               => strtoupper($item['ref']),
                            'cantidad'          => $item['cantidad'],
                            'ubicacion_id'      => $posicionId,
                            'unidad_id'         => $unidadId,
                            'lote'              => array_key_exists('lote', $item) ? $item['lote'] : null
                        ]);
                    } else {
                        //DEBERIA HACER ROLLBACK
                        DB::rollBack();
                        return $this->setResponse([], 'No hay capacidad suficiente para almacenar los items', true);
                    }
                }
            } else {
                // Log::alert("PASO ACA 2");
                //Creo el contenido del movimiento
                MovimientosContenido::create([
                    'movimiento_id'     => $movimiento->id,
                    'ref'               => strtoupper($request->ref),
                    'cantidad'          => $request->cantidad,
                    'ubicacion_id'      => $posicionId,
                    'unidad_id'         => $unidadId
                ]);

                //LO ELIMINO DE OTRA POSICION EXISTENTE
                $tPosicion = Ubicaciones::where('id', $posicionId)->first();
                if ($tPosicion) {
                    if ($tPosicion->deposito_id != HttpDepositos::DOLLYS && $tPosicion->deposito_id != HttpDepositos::TEMPORAL_A) {
                        MovimientosContenido::where('ubicacion_id', '<>', $posicionId)->where('ref', strtoupper($request->ref))->delete();
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            //throw $th;
            Log::error("MovimientosController::store : " . $th->getMessage());
            return $this->setResponse([], 'Ocurrió un error al generar el movimiento de stock', true);
            DB::rollBack();
        }

        return $this->setResponse([], 'ALMACENADO CORRECTAMENTE');
    }
}
