<?php

namespace App\Services;

use App\Http\Depositos;
use App\Http\WmsUnidades;
use App\Models\InterfazProveedor;
use App\Models\MaterialesPiezas;
use App\Models\Proveedores;
use App\Models\Recepcion;
use App\Models\RecepcionPackingList;
use App\Models\RegistroIngresoRollo;
use App\Models\Sar\TKanban;
use App\Models\Sar\TKanbanMaterial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

enum Campos: string {
    case CODIGO         = 'COD';
    case OTRO           = 'OTRO';
    case LOTE           = 'LOTE';
    case MT_BRUTO       = 'MB';
    case CANTIDAD       = 'ML';
    case PESO_BRUTO     = 'PB';
    case PESO_LIQUIDO   = 'PL';
    case OMITIR         = 'O';
    case ID_PROV        = 'ID';
    case FALLAS         = 'FL';
    case UNIDAD         = 'UD';
    case REMITO         = 'RM';
    case SECUENCIA      = 'SC';
}

enum Unidades: string {
    case UNIDAD     = 'UD';
    case CAJA       = 'CJ';
    case BULTO      = 'BL';
}
class RollosService {
    protected $interfaz = null;
    protected Proveedores $proveedor;
    protected $movimientoId;
    protected StockService $stockService;
    protected $posicionMaterialesDefault;
    protected $posicionCuarentena;
    public $codigoOperacion;
    public $mensajeError = null;

    static function crearCodigoOperacion(): string {
        return Str::uuid();
    }

    public function __construct(int $proveedorId, $codigoOperacion = null, bool $generarMovimiento = true) {
        $this->obtenerProveedor($proveedorId);
        $this->obtenerInterfazProveedor($proveedorId);

        if ($generarMovimiento) {
            $this->stockService = new StockService;
            $this->stockService->generaMovimiento(WmsUnidades::MATERIAL);
            $this->posicionMaterialesDefault = $this->stockService->obtenerPosicionDefault(Depositos::MATERIALES);
            $this->posicionCuarentena = $this->stockService->obtenerPosicionDefault(Depositos::RECHAZOS);
        }

        if (is_null($codigoOperacion) || $codigoOperacion == '') {
            $this->codigoOperacion = $this->crearCodigoOperacion();
        } else {
            $this->codigoOperacion = $codigoOperacion;
        }
    }

    private function obtenerProveedor(int $proveedorId) {
        $this->proveedor = Proveedores::select('id')->where('id', $proveedorId)->first();
    }

    private function obtenerInterfazProveedor(int $proveedorId) {
        $interfaz = InterfazProveedor::select('interfaz', 'delimitador', 'interfaz_p', 'delimitador_p')
            ->where('proveedor_id', $proveedorId)
            ->first();

        if ($interfaz) {
            $this->interfaz = $interfaz;
        } else {
            $this->interfaz = null;
        }
    }

    private function obtenerValor($campo, $data) {
        // if ($this->interfaz->fijo) {
        // } else {
        // }
        $keys = explode("|", $this->interfaz->interfaz);
        $posicion = array_search($campo->value, $keys, true);
        // Log::alert(count($data));
        // if (count($data) == 1) {
        //     Log::alert($data[0]);
        //     Log::alert($this->interfaz->delimitador);
        //     $dataExplode = explode($this->interfaz->delimitador, $data[0]);
        // } else {
        //     $dataExplode = explode($this->interfaz->delimitador, $data);
        // }

        // Log::alert($campo->value);
        // Log::alert("==================================");
        // // Log::alert($keys);
        // // Log::alert("______________________________");
        // // Log::alert($data);
        // // Log::alert($this->interfaz->interfaz);
        // Log::alert("POSICION: " . $posicion);
        // // Log::alert($campo->value);
        // Log::alert($dataExplode);
        // Log::alert("==================================");

        // Log::alert($this->interfaz->interfaz);
        // Log::alert($data[$posicion]);



        // Log::alert("===========================");
        return ($posicion !== false && isset($data[$posicion])) ? $data[$posicion] : null;
    }

    static function obtenerProveedorDeQR(string $qr) {

        if (strpos($qr, "]") == 0) {
            return 0;
        }

        try {
            $campos = explode("]", $qr);
            return intval($campos[0]);
        } catch (\Throwable $th) {
            //throw $th;
            return 0;
        }
    }

    private function procesaCodigoDeBarras($codigo) {
        $cod = ltrim(rtrim(str_replace("'", "-", $codigo)));
        $cod = str_replace("(", "*", $cod);
        $cod = str_replace('"', '@', $cod);

        return $cod;
    }

    private function obtenerValores($campos, $data) {
        $valores = [
            'codigo'        => '',
            'lote'          => '',
            'mt_bruto'      => 0,
            'cantidad'      => 0,
            'pe_bruto'      => 0,
            'pe_liquido'    => 0,
            'otros'         => '',
            'fallas'        => 0,
            'material'      => '',
            'unidad'        => ''
        ];

        $mapaCampos = [
            Campos::LOTE->value             => 'lote',
            Campos::MT_BRUTO->value         => 'mt_bruto',
            Campos::CANTIDAD->value         => 'cantidad',
            Campos::PESO_BRUTO->value       => 'pe_bruto',
            Campos::PESO_LIQUIDO->value     => 'pe_liquido',
            Campos::OTRO->value             => 'otros',
            Campos::FALLAS->value           => 'fallas',
            Campos::CODIGO->value           => 'codigo',
            Campos::UNIDAD->value           => 'unidad'
        ];

        foreach ($campos as $key => $value) {

            if (isset($mapaCampos[$value], $data[$key])) {
                $campo =  $mapaCampos[$value];

                if (in_array($campo, ['mt_bruto', 'cantidad', 'pe_bruto', 'pe_liquido', 'fallas'])) {
                    $valores[$campo] = (float) str_replace(",", ".", $data[$key]);
                } else {
                    $valores[$campo] = $this->procesaCodigoDeBarras($data[$key]);
                }
            }
        }

        // $valores['cantidad'] = $valores['cantidad'] == 0 ? 1 : $valores['cantidad'];
        // Log::alert($valores);
        return $valores;
    }

    public function procesarIngreso(string $qr) {

        //Verifico interfaz del proveedor
        if (is_null($this->interfaz)) {
            return $this->setError('El proveedor no tiene una interfaz informada.');
        }

        $campos = explode("|", $this->interfaz->interfaz);
        $newQr = $this->procesaCodigoDeBarras($qr);

        //VERIFICO SI VIENE EL DIGITO @ PARA PROCESAR VARIOS REGISTROS
        $multipleIngreso  = strpos($newQr, "@");

        $items = [];

        if ($multipleIngreso > -1) {
            $items = explode("@", $newQr);
        } else {
            array_push($items, $newQr);
        }

        $pendientesIngreso = [];

        $material = null;
        $registro = null;
        $noProcesoDatos = true;

        DB::beginTransaction();

        foreach ($items as $item) {

            if (strlen($item) < 5) {
                continue;
            }

            $noProcesoDatos = false;

            // Log::alert($item);
            // Obtener datos desde el QR
            $data = explode($this->interfaz->delimitador, $item);
            // Log::alert($data);

            // Obtener y procesar el código del material
            $codigoOriginal = $this->obtenerValor(Campos::CODIGO, $data);

            if (empty($codigoOriginal)) {
                return $this->setError('El código de material ' . $codigoOriginal . ' no existe');
            }

            $codigoProcesado = $this->procesaCodigoDeBarras($codigoOriginal);
            // Log::alert("COD MATERIAL PROCESADO : " . $codigoProcesado);

            // Buscar el material asociado
            $material = MaterialesPiezas::with('aprobacion_calidad')->select('id', 'codigo', 'nombre', 'lote')
                ->where('codigo_proveedor', $codigoProcesado)
                ->where('proveedor_id', $this->proveedor->id)
                ->first();

            // Log::alert("MATERIAL ENCONTRADO PARA EL CODIGO " . $codigoProcesado);
            // Log::alert($material);

            if (!$material) {
                return $this->setError('El material no existe');
            }

            // Obtener los valores del QR
            $valores = $this->obtenerValores($campos, $data);

            if ($valores['unidad'] == Unidades::BULTO->value || $valores['unidad'] == Unidades::CAJA->value) {
                //ES UNA CAJA O BULTO ENTONCES DIVIDO
                //Obtengo el lote del material y lo divido por la cantidad informada para saber cuantos registros son
                $lote = (is_null($material->lote) || $material->lote == '') ? 1 : intval($material->lote);
                $cantidadContenidoCaja = intval($valores['cantidad']) / $lote;

                for ($i = 0; $i < ($cantidadContenidoCaja); $i++) {
                    $existePendienteDeIngreso = $this->existeMaterialEnRecepcionPendiente([
                        'codigo'    => $valores['codigo'],
                        'lote'      => $valores['lote'],
                        'cantidad'  => $lote
                    ]);

                    if (!$existePendienteDeIngreso) {

                        Log::alert('No existe pendiente de ingreso para ' . json_encode([
                            'i'         => $i,
                            'codigo'    => $valores['codigo'],
                            'lote'      => $valores['lote'],
                            'cantidad'  => $lote
                        ]));
                        DB::rollBack();
                        // Log::alert("2");

                        return $this->setError('El código escaneado no esta pendiente de recepción');
                    }

                    $codigoEscanedado = str_replace($this->interfaz->delimitador . $valores['unidad'] . $this->interfaz->delimitador, $this->interfaz->delimitador . "UD" . $this->interfaz->delimitador, $item);

                    $registro = RegistroIngresoRollo::create([
                        'proveedor_id'      => $this->proveedor->id,
                        'material_id'       => $material->id,
                        'codigo_escaneado'  => $codigoEscanedado,
                        'user_id'           => null,
                        'codigo_sar'        => $material->codigo,
                        'lote'              => $valores['lote'],
                        'peso_bruto'        => $valores['pe_bruto'],
                        'peso_liquido'      => $valores['pe_liquido'],
                        'mt_bruto'          => $valores['mt_bruto'],
                        'cantidad'          => $lote,
                        'fallas'            => intval($valores['fallas']),
                        'otros'             => $valores['otros'],
                        'operacion'         => $this->codigoOperacion,
                    ]);

                    if ($registro) {
                        $posicionDestino = optional($material->aprobacion_calidad)->id > 0
                            ? $this->posicionCuarentena->id
                            : $this->posicionMaterialesDefault->id;

                        RecepcionPackingList::where('id', $existePendienteDeIngreso->id)
                            ->update(['ingreso_id' => $registro->id]);

                        $this->stockService->insertaDetalle(
                            $material->codigo,
                            $lote,
                            $posicionDestino,
                            $valores['lote'],
                            null,
                            $registro->id
                        );

                        // array_push($ingresos, $existePendienteDeIngreso->id);
                        array_push($pendientesIngreso, $existePendienteDeIngreso->id);
                    }
                }
            } else {
                $existePendienteDeIngreso = $this->existeMaterialEnRecepcionPendiente($valores);

                if (!$existePendienteDeIngreso) {
                    // Log::alert("1");
                    return $this->setError('El código escaneado no esta pendiente de recepción');
                }

                array_push($pendientesIngreso, $existePendienteDeIngreso->id);
                // Crear el registro de ingreso
                $registro = RegistroIngresoRollo::create([
                    'proveedor_id'      => $this->proveedor->id,
                    'material_id'       => $material->id,
                    'codigo_escaneado'  => $qr,
                    'user_id'           => null,
                    'codigo_sar'        => $material->codigo,
                    'lote'              => $valores['lote'],
                    'peso_bruto'        => $valores['pe_bruto'],
                    'peso_liquido'      => $valores['pe_liquido'],
                    'mt_bruto'          => $valores['mt_bruto'],
                    'cantidad'          => $valores['cantidad'] == 0 ? 1 : $valores['cantidad'],
                    'fallas'            => intval($valores['fallas']),
                    'otros'             => $valores['otros'],
                    'operacion'         => $this->codigoOperacion,
                ]);

                // Insertar en stock si se creó correctamente
                if ($registro) {
                    $posicionDestino = optional($material->aprobacion_calidad)->id > 0
                        ? $this->posicionCuarentena->id
                        : $this->posicionMaterialesDefault->id;

                    RecepcionPackingList::where('id', $existePendienteDeIngreso->id)
                        ->update(['ingreso_id' => $registro->id]);

                    $this->stockService->insertaDetalle(
                        $material->codigo,
                        $valores['cantidad'],
                        $posicionDestino,
                        $valores['lote'],
                        null,
                        $registro->id
                    );
                }

                $registro->material = $material;
            }
        }

        if ($noProcesoDatos) {
            return $this->setError('El código escaneado no es correcto');
        }

        //Verifico si quedo pendiente de escaneo, si no, lo finalizo
        $recepcionPendiente = Recepcion::select('packing_list', 'id')
            ->where('proveedor_id', $this->proveedor->id)
            ->where('pendiente', true)->where('en_darsena', true)
            ->where('fecha', date('Y-m-d'))
            ->first();

        $pendientes = RecepcionPackingList::whereNull('ingreso_id')
            ->where('operacion', $recepcionPendiente->packing_list)
            ->where('proveedor_id', $this->proveedor->id)
            ->count();

        if ($pendientes == 0) {
            Recepcion::where('id', $recepcionPendiente->id)
                ->update([
                    'pendiente' => false,
                    'en_darsena' => false
                ]);
        }

        DB::commit();

        return [
            'aprobacion_calidad' => $material ? (optional($material->aprobacion_calidad)->id > 0) : false,
            'data'               => $registro,
            'pendienteIngreso'   => $pendientesIngreso
        ];
    }

    private function setError(string $mensaje) {
        $this->mensajeError = $mensaje;
        return false;
    }

    public function matchQrConInterfaz(string $qr) {
        if ($this->interfaz->fijo) {
        } else {
            $data = explode($this->interfaz->delimitador, $qr);
        }

        $campos = explode("|", $this->interfaz->interfaz);

        $cod = $this->obtenerValor(Campos::CODIGO, $data);

        if (empty($cod)) {
            return null;
        }

        $cod = $this->procesaCodigoDeBarras($cod);

        $material = MaterialesPiezas::select('id', 'codigo', 'nombre', 'codigo_proveedor', 'proveedor_id', 'lote')->where('codigo_proveedor', $cod)
            ->where('proveedor_id', $this->proveedor->id)->first();

        if (!$material) {
            return null;
        }

        $valores = $this->obtenerValores($campos, $data);
        $valores['material']  = $material;
        $valores['campos_interfaz'] = $campos;

        if (in_array(Campos::LOTE->value, $campos) && empty($valores['lote'])) {
            return null;
        }

        if (in_array(Campos::CANTIDAD->value, $campos) && intval($valores['cantidad']) <= 0) {
            return null;
        }

        return $valores;
    }

    public function getRegistrosOperacion() {

        $data = RegistroIngresoRollo::with('material.aprobacion_calidad')->where('operacion', $this->codigoOperacion)->get();

        return $data ? $data->toArray() : [];
    }

    /**
     * De acuerdo al QR obtenido, busca la interfaz a la que corresponde
     */
    static function obtieneInterfazProveedorPorQR(string $qr) {
        $interfaces = InterfazProveedor::get();

        foreach ($interfaces as $interfaz) {
            if (empty($interfaz->proveedor_id) || empty($interfaz->delimitador)) {
                continue;
            }

            //Verifico si existe el delimitador en el qr
            $existeDelimitador = strpos($qr, $interfaz->delimitador);

            if ($existeDelimitador !== false) {
                //Verifico si cumple con la interfaz
                $rolloService = new RollosService($interfaz->proveedor_id, null, false);
                $valores = $rolloService->matchQrConInterfaz($qr);

                if ($valores) {
                    return $valores;
                }
            }
        }

        return null;
    }

    static function generarKanbanMaterial(int $materialId, $metros) {
        $material = MaterialesPiezas::where('id', $materialId)->first();

        $tKanban = new TKanban();
        $response = $tKanban->crearKanbanMaterial($material->codigo, $metros);

        if ($response) {

            $tKanban->cargarMM_SAP($response['kanban'], $metros, "M2");

            $kMaterial = TKanbanMaterial::where('ID', $response['kanban'])->first();

            $res = [
                'kanban'            => $response['kanban'],
                'material'          => $response['material'],
                'kanban_material'   => $kMaterial
            ];

            return $res;
        }

        return null;
    }

    private function existeMaterialEnRecepcionPendiente($datos) {
        // Log::alert($datos);
        $fecha = date('Y-m-d');

        //Verifico si el proveedor tiene una recepcion pendiente y en darsena
        $recepcionPendiente = Recepcion::select('packing_list')->where('proveedor_id', $this->proveedor->id)
            ->where('pendiente', true)->where('en_darsena', true)
            ->where('fecha', $fecha)
            ->first();

        if (!$recepcionPendiente) {
            Log::alert('No existe recepcion pendiente para el proveedor ' . $this->proveedor->id . ' en fecha ' . $fecha);
            return false;
        }

        // Log::alert($this->proveedor->id);
        // Log::alert($recepcionPendiente->packing_list);
        // Log::alert(RecepcionPackingList::where('codigo', $datos['codigo'])
        //     ->where('lote', $datos['lote'])
        //     ->where('cantidad', $datos['cantidad'])
        //     ->where('operacion', $recepcionPendiente->packing_list)
        //     ->where('proveedor_id', $this->proveedor->id)
        //     ->where('ingreso_id', null)
        //     ->toSql());

        //Busco el producto especifico
        $existeItemPendiente = RecepcionPackingList::where('codigo', $datos['codigo'])
            ->where('lote', $datos['lote'])
            ->when($datos['cantidad'] > 0, function ($q) use ($datos) {
                $q->where('cantidad', $datos['cantidad']);
            })
            ->where('operacion', $recepcionPendiente->packing_list)
            ->where('proveedor_id', $this->proveedor->id)
            ->where('ingreso_id', null)
            ->first();

        return $existeItemPendiente;
    }
}
