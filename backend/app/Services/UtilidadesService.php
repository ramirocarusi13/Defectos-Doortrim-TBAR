<?php

namespace App\Services;

use App\Enums\DaysOfWeek;
use App\Http\Depositos;
use App\Http\WmsUnidades;
use App\Models\ComparativoDespachosProduccionTemp;
use App\Models\Despachos;
use App\Models\DespachosItems;
use App\Models\DespachosPedido;
use App\Models\HoraHoraProduccion;
use App\Models\Lineas;
use App\Models\Modelos;
use App\Models\MovimientosContenido;
use App\Models\ProduccionParada;
use App\Models\StockFilaTemp;
use App\Models\TmpEficienciaProduccionHoraHora;
use App\Models\Ubicaciones;
use App\Models\User;
use App\Models\VTDespachos;
use App\Models\VTLogFG;
use App\Models\VTProduccionHRDTAR;
use DateInterval;
use DateTime;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

enum Filas: int {
    case FRONT = 1;
    case REAR1 = 2;
    case REAR2 = 3;
    case SUP = 5;
}

class UtilidadesService {

    private $posicionDespachos;
    private $posicionCuarentena;

    public function __construct() {
        $this->posicionDespachos = Ubicaciones::where('deposito_id', Depositos::DESPACHOS)->first();
        $this->posicionCuarentena = Ubicaciones::where('deposito_id', Depositos::RECHAZOS)->first();
    }

    private function getStockFilaAUnaFecha($fecha, $fila) {

        return  MovimientosContenido::selectRaw('sum(wms_movimientos_contenidos.cantidad) as stock, modelos.nombre, modelos.consumo,modelos.cantidad')
            ->leftJoin('kanbans', 'ref', 'kanbans.codigo')
            ->leftJoin('modelos', 'modelos.id', 'kanbans.modelo_id')
            ->where('modelos.fila_id', $fila)
            ->where('unidad_id', WmsUnidades::KANBAN)
            ->where('wms_movimientos_contenidos.created_at', '<=', $fecha . ' 06:00:00')
            ->where(function ($q) {
                $q->where('wms_movimientos_contenidos.ubicacion_id', '!=', $this->posicionCuarentena->id);
                $q->where('wms_movimientos_contenidos.ubicacion_id', '!=', $this->posicionDespachos->id);
            })
            ->groupBy('modelos.nombre')
            ->groupBy('modelos.consumo')
            ->groupBy('modelos.cantidad')
            ->get();
    }

    public function generaStockKanbanPorFila($fecha) {

        $filas = [
            [
                'nombre' => 'FRONT',
                'id'     => Filas::FRONT->value
            ],
            [
                'nombre' => 'REAR1',
                'id'     => Filas::REAR1->value
            ],
            [
                'nombre' => 'REAR2',
                'id'     => Filas::REAR2->value
            ],
            [
                'nombre' => 'SUP',
                'id'     => Filas::SUP->value
            ],
        ];


        foreach ($filas as $fila) {
            $data = $this->getStockFilaAUnaFecha($fecha, $fila['id']);
            $filaName = $fila['nombre'];

            foreach ($data as $d) {
                StockFilaTemp::updateOrCreate([
                    'modelo'    => $d->nombre,
                    'fecha'     => $fecha,
                    'fila'      => $filaName
                ], [
                    'modelo'    => $d->nombre,
                    'fecha'     => $fecha,
                    'stock'     => $d->stock * $d->cantidad,
                    'fila'      => $filaName,
                    'consumo'   => $d->consumo
                ]);
            }
        }
    }

    private function buscaVolumenEnArrayPorKey($array, string $key) {
        $cantidad = 0;

        foreach ($array as $elemento) {
            if ($elemento['tipo'] == $key) {
                $cantidad = $cantidad + intval($elemento['cantidad']);
            }
        }

        return $cantidad;
    }

    public function generaVolumenProduccionDespachoPBI($fecha) {
        $fechaDesde = DateTime::createFromFormat('Y-m-d', $fecha);
        $fechaHasta = DateTime::createFromFormat('Y-m-d', $fecha);
        $fechaReal = DateTime::createFromFormat('Y-m-d', $fecha);

        $dayOfWeek = $fechaDesde->format('N');
        //SI ES LUNES ENTONCES TOMO LA INFO DEL VIERNES
        if ($dayOfWeek == DaysOfWeek::DOMINGO) {
            $fechaReal = clone $fechaDesde;
            $fechaDesde->sub(new DateInterval('P2D'));
            // $fechaHasta->add(new DateInterval('P1D'));
        } else {
            $fechaHasta->add(new DateInterval('P1D'));
        }

        // Log::alert($fechaDesde->format('d/m/y'));
        // Log::alert($fechaHasta->format('d/m/y'));
        // Log::alert("====================================");

        $pnM7 = ['71921-X7A18','71921-X7A71','71921-X7A21-C0', '71921-X7A22-C1', '71921-X7A22-A0', '71921-X7A88-C0', '71921-X7A20-C0'];

        $pnM9 = [
            '74221-X7A18',
            '74221-X7A48-A1',
            '74222-X7A18',
            '74222-X7A48-A1',
            '74261-X7A14',
            '74261-X7A40-A1',
            '74262-X7A14',
            '74262-X7A40-A1',
            '74221-X7A65',
            '74222-X7A65',
            '74261-X7A58',
            '74262-X7A58',
            '74221-X7A21-C1',
            '74222-X7A21-C1',
            '74261-X7A17-C1',
            '74262-X7A17-C1',
            '74221-X7A19-C0',
            '74222-X7A19-C0',
            '74261-X7A15-C0',
            '74262-X7A15-C0',
            '74222-X7A21-C2',
            '74221-X7A18-C0',
            '74221-X7A21-C2',
            '74222-X7A18-C0',
            '74261-X7A14-C0',
            '74261-X7A17-C2',
            '74262-X7A14-C0',
            '74262-X7A17-C2',
        ];

        //OBTENGO DESPACHOS
        $datosDespachos = VTDespachos::selectRaw('tipo,sum(cantidad) * cantidadModelo as cantidad,fecha')
            ->whereBetween('fecha', [$fechaDesde->format('Y-m-d'), $fechaDesde->format('Y-m-d')])
            ->where('modelo', 'not like', 'H%')
            ->groupBy('tipo')->groupBy('cantidadModelo')->groupBy('fecha')
            ->get();

        $datosDespachosHiace = VTDespachos::selectRaw('sum(cantidad) * sum(cantidadModelo) as cantidad')
            ->whereBetween('fecha', [$fechaDesde->format('Y-m-d'), $fechaDesde->format('Y-m-d')])
            ->where('modelo', 'like', 'H%')
            ->first();

        $despachosFront = $this->buscaVolumenEnArrayPorKey($datosDespachos, 'FRONT');
        $despachosFrontBCAB = $this->buscaVolumenEnArrayPorKey($datosDespachos, 'FRONT B-CAB');
        $despachosFrontCuero = $this->buscaVolumenEnArrayPorKey($datosDespachos, 'FRONT CUERO');
        $despachosTUP = $this->buscaVolumenEnArrayPorKey($datosDespachos, 'T-UP');
        // $despachosM9 = $this->buscaVolumenEnArrayPorKey($datosDespachos, 'T-UP');

        //OBTENGO PRODUCCIÓN
        $datosProduccion = VTLogFG::selectRaw('tipo,sum(cantidad) * cantidadModelo as cantidad,CONVERT(date,fecha,103) as fecha')
            ->whereBetween('fecha', [$fechaDesde->format('Y-m-d') . ' 06:00:00', $fechaHasta->format('Y-m-d') . ' 01:50:00'])
            ->where('nombre', 'not like', 'H%')
            ->groupBy('tipo')
            ->groupBy('cantidadModelo')
            ->groupByRaw('CONVERT(date,fecha,103)')
            ->get();

        $datosProduccionHiace = VTLogFG::selectRaw('sum(cantidad) * sum(cantidadModelo) as cantidad')
            ->whereBetween('fecha', [$fechaDesde->format('Y-m-d') . ' 06:00:00', $fechaHasta->format('Y-m-d') . ' 01:50:00'])
            ->where('nombre', 'like', 'H%')
            ->first();

        if ($datosProduccionHiace) {
            $produccionHiace = $datosProduccionHiace->cantidad;
        } else {
            $produccionHiace = 0;
        }

        if ($datosDespachosHiace) {
            $despachosHiace = $datosDespachosHiace->cantidad;
        } else {
            $despachosHiace = 0;
        }

        $produccionFront = $this->buscaVolumenEnArrayPorKey($datosProduccion, 'FRONT');
        $produccionFrontBCAB = $this->buscaVolumenEnArrayPorKey($datosProduccion, 'FRONT B-CAB');
        $produccionFrontCuero = $this->buscaVolumenEnArrayPorKey($datosProduccion, 'FRONT CUERO');
        $produccionTUP = $this->buscaVolumenEnArrayPorKey($datosProduccion, 'T-UP');

        $despachosVehiculos = $despachosFront + $despachosFrontBCAB + $despachosFrontCuero;
        $produccionVehiculos = $produccionFront + $produccionFrontBCAB + $produccionFrontCuero;

        $produccionM7 = VTProduccionHRDTAR::selectRaw('sum(isnull(cantidad,0)) as cantidad')
            ->whereIn('ref', $pnM7)
            ->whereBetween('created_at', [$fechaDesde->format('Y-m-d') . ' 06:00:00', $fechaHasta->format('Y-m-d') . ' 00:50:00'])
            ->first();

        $produccionM9 = VTProduccionHRDTAR::selectRaw('sum(isnull(cantidad,0)) as cantidad')
            ->whereIn('ref', $pnM9)
            ->whereBetween('created_at', [
                $fechaDesde->format('Y-m-d') . ' 06:00:00',
                $fechaHasta->format('Y-m-d') . ' 00:50:00'
            ])
            ->first();


        $dataDespachoM7 = MovimientosContenido::selectRaw('sum(isnull(cantidad,0)) as cantidad')
            ->whereIn('ref', $pnM7)
            ->whereBetween('created_at', [
                $fechaDesde->format('Y-m-d') . ' 06:00:00',
                $fechaHasta->format('Y-m-d') . ' 00:50:00'
            ])
            ->where('cantidad', '<', 0)
            ->first();


        $dataDespachoM9 = Despachos::selectRaw('sum(despachos_pedidos.pedido)*m_door_trims.lote as cantidad')
            ->leftJoin('despachos_pedidos', 'despachos_pedidos.despacho_id', 'despachos.id')
            ->leftJoin('m_door_trims', 'm_door_trims.nombre', 'despachos_pedidos.modelo')
            ->where('fecha', $fechaDesde->format('Y-m-d'))
            ->where('despachos_pedidos.pedido', '>', 0)
            ->where('despachos_pedidos.tipo', 'DOORTRIM')
            ->groupBy('m_door_trims.lote')
            ->first();


        $prodM7 = ($produccionM7 && !is_null($produccionM7->cantidad)) ? $produccionM7?->cantidad / 2 : 0;
        $prodM9 = ($produccionM9 && !is_null($produccionM9->cantidad)) ? $produccionM9?->cantidad / 4 : 0;

        $despachoM9 = ($dataDespachoM9 && !is_null($dataDespachoM9->cantidad)) ? $dataDespachoM9?->cantidad / 4 : 0;
        $despachoM7 = ($dataDespachoM7 && !is_null($dataDespachoM7->cantidad)) ? $dataDespachoM7?->cantidad / 2 : 0;

        $titulo = 'PRODUCCIÓN SAR';

        ComparativoDespachosProduccionTemp::updateOrCreate(
            [
                'fecha'             => $dayOfWeek == DaysOfWeek::DOMINGO ? $fechaReal->format('Y-m-d') : $fechaDesde->format('Y-m-d'),
                'titulo'            => $titulo,
            ],
            [
                'fecha'             => $dayOfWeek == DaysOfWeek::DOMINGO ? $fechaReal->format('Y-m-d') : $fechaDesde->format('Y-m-d'),
                'titulo'            => $titulo,
                'vehiculos'         => $produccionVehiculos,
                'cuero'             => $produccionFrontCuero,
                'bcab'              => $produccionFrontBCAB,
                'tup'               => $produccionTUP,
                'm7'                => ceil($prodM7),
                'm9'                => ceil($prodM9),
                'orden'             => 0,
                'hiace'             => $produccionHiace,
                'prod_hiace'        => 0,
                'dif_hiace'         => 0
            ]
        );

        $titulo = 'DESPACHOS A TBAR';

        ComparativoDespachosProduccionTemp::updateOrCreate(
            [
                'fecha'             => $dayOfWeek == DaysOfWeek::DOMINGO ? $fechaReal->format('Y-m-d') : $fechaDesde->format('Y-m-d'),
                'titulo'            => $titulo,
            ],
            [
                'fecha'             => $dayOfWeek == DaysOfWeek::DOMINGO ? $fechaReal->format('Y-m-d') : $fechaDesde->format('Y-m-d'),
                'titulo'            => $titulo,
                'vehiculos'         => $despachosVehiculos * -1,
                'cuero'             => $despachosFrontCuero * -1,
                'bcab'              => $despachosFrontBCAB * -1,
                'tup'               => $despachosTUP * -1,
                'm7'                => ceil($despachoM7),
                'm9'                => ceil($despachoM9) * -1,
                'orden'             => 1,
                'hiace'             => $despachosHiace * -1,
                'prod_hiace'        => 0,
                'dif_hiace'         => 0
            ]
        );
    }

    public function generaDespachoPedidoEnBaseaItems($despachoId) {
        //SI EXISTE ALGO, OMITO POR AHORA
        $existe = DespachosPedido::where('despacho_id', $despachoId)->first();
        if ($existe) {
            Log::alert("EXISTE DESPACHO " . $despachoId);
            return;
        }

        $items = DespachosItems::selectRaw('modelo,count(*) as cantidad,tipo')
            ->where('despacho_id', $despachoId)
            ->groupBy('modelo')
            ->groupBy('tipo')
            ->get();

        foreach ($items as $item) {

            $produccion = DespachosItems::where('despacho_id', $despachoId)->where('modelo', $item->modelo)
                ->where('produccion', '1')->get()->count();

            $desbalanceo = DespachosItems::where('despacho_id', $despachoId)->where('modelo', $item->modelo)
                ->where('desbalanceo', '1')->get()->count();

            DespachosPedido::updateOrCreate(
                [
                    'despacho_id'       => $despachoId,
                    'modelo'            => $item->modelo
                ],
                [
                    'despacho_id'       => $despachoId,
                    'pedido'            => intval($item->cantidad),
                    'modelo'            => $item->modelo,
                    'pendiente'         => 0,
                    'produccion'        => $produccion,
                    'desbalanceo'       => $desbalanceo,
                    'cl2'               => 0,
                    'tipo'              => $item->tipo,
                ]
            );
        }
    }

    public function generaTemporalEficienciaProduccionHoraHora() {

        $fechaDesde = '2025-06-01';
        $fechaHasta = '2025-06-30';
        $hsReales = 0;

        $datos = HoraHoraProduccion::selectRaw('sum([real]) as real, sum([plan]) as volumen,fecha,linea,turno_nombre')
            ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->groupBy('fecha')
            ->groupBy('linea')
            ->groupBy('turno_nombre')
            ->get();

        $hc = 0;
        $taktTime = 0;

        foreach ($datos as $item) {
            $linea = Lineas::where('id', $item->linea)->first();
            $paradas = ProduccionParada::selectRaw('sum(minutos) as minutos')
                ->where('turno_nombre', $item->turno_nombre)
                ->where('fecha', $item->fecha)->where('shop', "M" . $item->linea)->first();

            $horasParada = $paradas ? $paradas->minutos / 60 : 0;

            if ($linea) {
                $hc = intval($linea?->hc);
                $taktTime = floatval($linea?->takt_time);
            }
            $hsReales = ((7.97 * $hc) + 0 - $horasParada) / (intval($item->real) > 0 ? intval($item->real) : 1);
            if (intval($item->volumen) > 0) {
                $eficiencia = ((7.97 * $hc) / intval($item->volumen)) / $hsReales;
            }
            $oa = (intval($item->real) * $taktTime) / (3600 * 7.97);

            TmpEficienciaProduccionHoraHora::updateOrCreate(
                [
                    'shop'          => "M" . $item->linea,
                    'fecha'         => $item->fecha,
                    'turno'         => $item->turno_nombre,
                ],
                [
                    'shop'          => "M" . $item->linea,
                    'fecha'         => $item->fecha,
                    'turno'         => $item->turno_nombre,
                    'volumen'       => intval($item->volumen),
                    'real'          => intval($item->real),
                    'tipo'          => "PROMEDIO",
                    'eficiencia'    => $eficiencia,
                    'oa'            => $oa
                ]
            );
        }

        // foreach ($request->modelos as $key => $value) {
        //     # code...
        // }
    }
}
