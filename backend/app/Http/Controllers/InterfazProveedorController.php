<?php

namespace App\Http\Controllers;

use App\Models\InterfazProveedor;
use App\Models\Proveedores;
use App\Services\RollosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InterfazProveedorController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        //
    }

    public function verificarCodigo(Request $request) {
        $request->validate([
            'codigo_escaneado' => 'required|string',
        ], [
            'codigo_escaneado.required' => 'El codigo escaneado es obligatorio.',
        ]);

        $qr = $this->normalizarCodigoEscaneado($request->codigo_escaneado);
        $proveedorId = $this->obtenerProveedorIdDesdeQr($qr);

        if ($proveedorId <= 0) {
            return $this->setResponse([], 'No se pudo identificar el proveedor por el codigo inicial escaneado.', true);
        }

        $proveedor = Proveedores::with('interface')->find($proveedorId);

        if (!$proveedor) {
            return $this->setResponse([], 'El proveedor indicado por el codigo no existe.', true);
        }

        if (!$proveedor->interface) {
            return $this->setResponse([], 'El proveedor no tiene una interfaz configurada.', true);
        }

        $rolloService = new RollosService($proveedorId, null, false);
        $datos = $rolloService->matchQrConInterfaz($qr);

        if (!$datos || empty($datos['material'])) {
            return $this->setResponse([], 'No se encontro informacion del material para el codigo escaneado.', true);
        }

        $camposInterfaz = $datos['campos_interfaz'] ?? [];
        $valoresEtiqueta = $this->armarDatosEtiqueta($camposInterfaz, $qr, $proveedor->interface->delimitador);
        $material = $datos['material'];

        return $this->setResponse([
            'proveedor' => [
                'id' => $proveedor->id,
                'nombre' => $proveedor->nombre,
            ],
            'material' => [
                'id' => $material->id,
                'codigo' => $material->codigo,
                'nombre' => $material->nombre,
                'codigo_proveedor' => $material->codigo_proveedor,
                'lote' => $material->lote,
                'um' => $material->um,
                'tipo' => $material->tipo,
            ],
            'datos' => [
                'codigo' => $datos['codigo'] ?? null,
                'lote' => $datos['lote'] ?? null,
                'mt_bruto' => $datos['mt_bruto'] ?? null,
                'cantidad' => $datos['cantidad'] ?? null,
                'peso_bruto' => $datos['pe_bruto'] ?? null,
                'peso_liquido' => $datos['pe_liquido'] ?? null,
                'otros' => $datos['otros'] ?? null,
                'fallas' => $datos['fallas'] ?? null,
                'unidad' => $datos['unidad'] ?? null,
            ],
            'etiqueta' => $valoresEtiqueta,
            'interfaz' => [
                'id' => $proveedor->interface->id,
                'interfaz' => $proveedor->interface->interfaz,
                'delimitador' => $proveedor->interface->delimitador,
                'campos' => $camposInterfaz,
            ],
            'codigo_escaneado' => $qr,
        ]);
    }

    private function normalizarCodigoEscaneado(string $codigo): string {
        return trim(str_replace(["|"], ["]"], $codigo));
    }

    private function obtenerProveedorIdDesdeQr(string $qr): int {
        $qr = trim($qr);
        $delimitadores = InterfazProveedor::whereNotNull('delimitador')
            ->pluck('delimitador')
            ->filter()
            ->unique();

        foreach ($delimitadores as $delimitador) {
            $posicionDelimitador = strpos($qr, $delimitador);

            if ($posicionDelimitador === false || $posicionDelimitador === 0) {
                continue;
            }

            $codigoProveedor = substr($qr, 0, $posicionDelimitador);

            if (ctype_digit($codigoProveedor)) {
                return intval($codigoProveedor);
            }
        }

        $primerCaracter = substr($qr, 0, 1);

        return ctype_digit($primerCaracter) ? intval($primerCaracter) : 0;
    }

    private function armarDatosEtiqueta(array $campos, string $qr, ?string $delimitador): array {
        $valores = $delimitador ? explode($delimitador, $qr) : [$qr];
        $labels = [
            'ID' => 'Proveedor',
            'COD' => 'Codigo material',
            'LOTE' => 'Lote',
            'MB' => 'Metros bruto',
            'ML' => 'Cantidad',
            'PB' => 'Peso bruto',
            'PL' => 'Peso liquido',
            'OTRO' => 'Otros',
            'O' => 'Omitir',
            'FL' => 'Fallas',
            'UD' => 'Unidad',
            'RM' => 'Remito',
            'SC' => 'Secuencia',
        ];

        $datos = [];

        foreach ($campos as $index => $campo) {
            if ($campo === 'O') {
                continue;
            }

            $datos[] = [
                'campo' => $campo,
                'etiqueta' => $labels[$campo] ?? $campo,
                'valor' => $valores[$index] ?? null,
            ];
        }

        return $datos;
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\InterfazProveedor  $interfazProveedor
     * @return \Illuminate\Http\Response
     */
    public function show(InterfazProveedor $interfazProveedor) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\InterfazProveedor  $interfazProveedor
     * @return \Illuminate\Http\Response
     */
    public function edit(InterfazProveedor $interfazProveedor) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\InterfazProveedor  $interfazProveedor
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, InterfazProveedor $interfazProveedor) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\InterfazProveedor  $interfazProveedor
     * @return \Illuminate\Http\Response
     */
    public function destroy(InterfazProveedor $interfazProveedor) {
        //
    }
}
