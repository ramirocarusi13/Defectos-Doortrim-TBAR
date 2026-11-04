<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MapeaDefectoLegacy;
use App\Models\EolDoor;
use App\Models\EolDefecto;
use App\Models\EolPieza;
use App\Models\EolPiezaEvento;
use App\Models\User;
use App\Services\DoorQrService;
use App\Services\DoorService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Linea de door (cambio de piezas DOOR / ARM_REST): maestro de piezas serializadas
 * (eol_piezas), cambios fisicos (eol_doors) y el ciclo de retrabajo/validacion
 * de cada pieza en el mini buffer. Ver DoorService para la logica de negocio.
 */
class EolDoorController extends Controller
{
    use MapeaDefectoLegacy;


    // =====================================================================
    // Maestro de piezas
    // =====================================================================

    /**
     * Alta de piezas (individual o lote). Idempotente por pieza_qr:
     *  - QR nuevo -> se crea.
     *  - QR existente en BAJA/SCRAP -> se reactiva (vuelve a DISPONIBLE).
     *  - QR existente y activo -> se rechaza (no se pisa una pieza en uso).
     */
    public function altaPiezas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'piezas'                 => 'required_without:pieza_qr|array|min:1|max:500',
            'piezas.*.pieza_qr'      => 'required_with:piezas|string|max:120',
            'piezas.*.tipo_pieza'    => 'required_with:piezas|string|max:30',
            'piezas.*.modelo'        => 'nullable|string|max:150',
            'piezas.*.codigo'        => 'nullable|string|max:60',
            'piezas.*.lado'          => 'nullable|string|max:30',
            // Alta individual (compat: los mismos campos sueltos en vez de 'piezas')
            'pieza_qr'                => 'required_without:piezas|string|max:120',
            'tipo_pieza'              => 'required_without:piezas|string|max:30',
            'modelo'                  => 'nullable|string|max:150',
            'codigo'                  => 'nullable|string|max:60',
            'lado'                    => 'nullable|string|max:30',
            'buffer_id'               => 'nullable|string|max:60',
            'buffer_nombre'           => 'nullable|string|max:120',
            'linea'                   => 'nullable|string|max:60',
            'user'                    => 'nullable',
        ]);

        $piezas = $data['piezas'] ?? [[
            'pieza_qr'   => $data['pieza_qr'],
            'tipo_pieza' => $data['tipo_pieza'],
            'modelo'     => $data['modelo'] ?? null,
            'codigo'     => $data['codigo'] ?? null,
            'lado'       => $data['lado'] ?? null,
        ]];

        $bufferId     = $data['buffer_id'] ?? null;
        $bufferNombre = $data['buffer_nombre'] ?? null;
        $linea        = $data['linea'] ?? null;
        $user         = !empty($data['user']) ? User::find($data['user']) : null;

        $creadas = [];
        $reactivadas = [];
        $rechazadas = [];

        DB::transaction(function () use ($piezas, $bufferId, $bufferNombre, $linea, $user, &$creadas, &$reactivadas, &$rechazadas) {
            foreach ($piezas as $p) {
                $qr = trim((string) ($p['pieza_qr'] ?? ''));
                if ($qr === '') {
                    $rechazadas[] = ['pieza_qr' => $qr, 'motivo' => 'QR vacio'];
                    continue;
                }

                $existente = EolPieza::where('pieza_qr', $qr)->lockForUpdate()->first();

                $info = DoorQrService::interpretar($qr);
                if ($info && $info['tipo'] === 'GARNISH') {
                    // QR = solo el SD: no identifica una pieza, no va al maestro.
                    $rechazadas[] = ['pieza_qr' => $qr, 'motivo' => 'El garnish no se registra pieza por pieza (su QR es solo el SD)'];
                    continue;
                }
                if (!$existente && $info) {
                    $creadas[] = DoorService::altaDesdeQr($qr, $info, $user, $bufferId, $bufferNombre, 'Alta en el buffer (datos del QR)')->pieza_qr;
                    continue;
                }

                if (!$existente) {
                    $pieza = EolPieza::create([
                        'pieza_qr'         => $qr,
                        'tipo_pieza'       => DoorService::normalizarTipo($p['tipo_pieza'] ?? null) ?? '',
                        'modelo'           => $p['modelo'] ?? null,
                        'codigo'           => $p['codigo'] ?? null,
                        'lado'             => $p['lado'] ?? null,
                        'estado'           => 'DISPONIBLE',
                        'buffer_id'        => $bufferId,
                        'buffer_nombre'    => $bufferNombre,
                        'linea'            => $linea,
                        'alta_por'         => $user?->id,
                        'alta_por_nombre'  => $user?->name,
                        'alta_at'          => now(),
                        'ultimo_evento_at' => now(),
                    ]);
                    DoorService::registrarEventoPieza($pieza, 'ALTA', $user, [
                        'estado_desde' => null,
                        'estado_hasta' => 'DISPONIBLE',
                        'buffer_id'    => $bufferId,
                    ]);
                    $creadas[] = $pieza->pieza_qr;
                    continue;
                }

                if (in_array($existente->estado, ['BAJA', 'SCRAP'], true)) {
                    $estadoDesde = $existente->estado;
                    $existente->update([
                        'estado'            => 'DISPONIBLE',
                        'buffer_id'         => $bufferId,
                        'buffer_nombre'     => $bufferNombre,
                        'linea'             => $linea ?? $existente->linea,
                        'disponible_desde'  => null,
                        'ultimo_evento_at'  => now(),
                        // Pieza reactivada: no debe arrastrar el rastro de su ciclo anterior.
                        'defecto_id_actual' => null,
                        'door_id_actual'    => null,
                        'tomado_por'        => null,
                        'tomado_por_nombre' => null,
                        'tomado_at'         => null,
                    ]);
                    DoorService::registrarEventoPieza($existente, 'AJUSTE_MANUAL', $user, [
                        'estado_desde'  => $estadoDesde,
                        'estado_hasta'  => 'DISPONIBLE',
                        'buffer_id'     => $bufferId,
                        'observaciones' => 'Reactivada por alta de piezas',
                    ]);
                    $reactivadas[] = $existente->pieza_qr;
                    continue;
                }

                $rechazadas[] = ['pieza_qr' => $qr, 'motivo' => "Ya existe activa (estado {$existente->estado})"];
            }
        });

        return response()->json([
            'data' => [
                'creadas'     => $creadas,
                'reactivadas' => $reactivadas,
                'rechazadas'  => $rechazadas,
            ],
            'error'   => false,
            'message' => 'Alta de piezas procesada',
        ]);
    }

    /** Listado del maestro de piezas, con filtros. */
    public function piezas(Request $request): JsonResponse
    {
        $q = EolPieza::query();

        // 'antiguedad' -> respeta el FIFO del buffer (la que espera hace mas tiempo,
        // primero); default intacto: ultimas modificadas primero.
        if ($request->query('orden') === 'antiguedad') {
            $q->orderBy('ultimo_evento_at')->orderBy('id');
        } else {
            $q->orderByDesc('id');
        }

        if ($request->filled('buffer_id')) {
            $q->where('buffer_id', $request->query('buffer_id'));
        }
        if ($request->filled('estado')) {
            $q->where('estado', strtoupper($request->query('estado')));
        }
        if ($request->filled('tipo_pieza')) {
            $q->whereIn('tipo_pieza', self::tiposEquivalentes($request->query('tipo_pieza')));
        }
        if ($request->filled('modelo')) {
            $q->where('modelo', $request->query('modelo'));
        }
        if ($request->filled('q')) {
            $texto = $request->query('q');
            $q->where(function ($w) use ($texto) {
                $w->where('pieza_qr', 'like', "%{$texto}%")
                    ->orWhere('codigo', 'like', "%{$texto}%")
                    ->orWhere('modelo', 'like', "%{$texto}%");
            });
        }

        // per_page configurable (tope 500 para no reventar la pantalla ni la query).
        $perPage = (int) $request->query('per_page', 50);
        if ($perPage < 1) {
            $perPage = 50;
        }
        $perPage = min($perPage, 500);

        return response()->json(['data' => $q->paginate($perPage), 'error' => false]);
    }

    /** Ficha de una pieza puntual: datos + defecto abierto + ultimo cambio + ultimos 20 eventos. */
    public function ficha(string $qr): JsonResponse
    {
        $pieza = EolPieza::where('pieza_qr', $qr)->first();
        if (!$pieza) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Pieza no encontrada'], 404);
        }

        $defectoAbierto = $pieza->defecto_id_actual ? EolDefecto::find($pieza->defecto_id_actual) : null;
        $ultimoDoor = EolDoor::where(function ($w) use ($pieza) {
            $w->where('pieza_retirada_id', $pieza->id)->orWhere('pieza_entregada_id', $pieza->id);
        })->orderByDesc('id')->first();

        $eventos = EolPiezaEvento::where('pieza_id', $pieza->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(20)->get();

        return response()->json([
            'data' => [
                'pieza'          => $pieza,
                'defectoAbierto' => $defectoAbierto,
                'ultimoDoor'     => $ultimoDoor,
                'eventos'        => $eventos,
            ],
            'error' => false,
        ]);
    }

    /** Historial completo de eventos de una pieza. */
    public function eventosPieza(string $qr): JsonResponse
    {
        $pieza = EolPieza::where('pieza_qr', $qr)->first();
        if (!$pieza) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Pieza no encontrada'], 404);
        }

        $eventos = EolPiezaEvento::where('pieza_id', $pieza->id)
            ->orderBy('created_at')->orderBy('id')
            ->limit(1000)->get();

        return response()->json(['data' => $eventos, 'error' => false]);
    }

    // =====================================================================
    // Buffer (stock)
    // =====================================================================

    public function bufferStock(Request $request): JsonResponse
    {
        $bufferId = $request->query('buffer_id');
        $tipoPieza = $request->query('tipo_pieza');

        $base = EolPieza::query();
        if ($bufferId) {
            $base->where('buffer_id', $bufferId);
        }
        if ($tipoPieza) {
            $base->whereIn('tipo_pieza', self::tiposEquivalentes($tipoPieza));
        }

        // Agregados SQL en vez de get() + filtrado PHP sobre TODAS las filas.
        $totales = (clone $base)->selectRaw(
            "SUM(CASE WHEN estado = 'DISPONIBLE' AND (disponible_desde IS NULL OR disponible_desde <= ?) THEN 1 ELSE 0 END) AS disponibles,"
            . " SUM(CASE WHEN estado = 'DISPONIBLE' AND disponible_desde IS NOT NULL AND disponible_desde > ? THEN 1 ELSE 0 END) AS curando,"
            . " SUM(CASE WHEN estado = 'DEFECTUOSA' THEN 1 ELSE 0 END) AS defectuosas,"
            . " SUM(CASE WHEN estado = 'EN_RETRABAJO' THEN 1 ELSE 0 END) AS enRetrabajo,"
            . " SUM(CASE WHEN estado = 'PEND_VALIDACION' THEN 1 ELSE 0 END) AS pendValidacion",
            [now(), now()]
        )->first();

        $porTipoModelo = (clone $base)
            ->selectRaw(
                "tipo_pieza, modelo, COUNT(*) AS total,"
                . " SUM(CASE WHEN estado = 'DISPONIBLE' AND (disponible_desde IS NULL OR disponible_desde <= ?) THEN 1 ELSE 0 END) AS disponibles,"
                . " SUM(CASE WHEN estado = 'DEFECTUOSA' THEN 1 ELSE 0 END) AS defectuosas",
                [now()]
            )
            ->groupBy('tipo_pieza', 'modelo')
            ->get()
            ->map(fn ($r) => [
                'tipo_pieza'  => $r->tipo_pieza ?: 'SIN_TIPO',
                'modelo'      => $r->modelo ?: 'SIN_MODELO',
                'total'       => (int) $r->total,
                'disponibles' => (int) $r->disponibles,
                'defectuosas' => (int) $r->defectuosas,
            ])
            ->values();

        $masAntiguaDefectuosaAt = (clone $base)->where('estado', 'DEFECTUOSA')->min('updated_at');

        return response()->json([
            'data' => [
                'disponibles'    => (int) ($totales->disponibles ?? 0),
                'curando'        => (int) ($totales->curando ?? 0),
                'defectuosas'    => (int) ($totales->defectuosas ?? 0),
                'enRetrabajo'    => (int) ($totales->enRetrabajo ?? 0),
                'pendValidacion' => (int) ($totales->pendValidacion ?? 0),
                'porTipoModelo'  => $porTipoModelo,
                'masAntiguaDefectuosaMin' => $masAntiguaDefectuosaAt
                    ? (int) \Carbon\Carbon::parse($masAntiguaDefectuosaAt)->diffInMinutes(now(), true)
                    : null,
                'fechaActualizacion' => now()->format('H:i:s'),
            ],
            'error' => false,
        ]);
    }

    // =====================================================================
    // Contexto del door (escaneo)
    // =====================================================================

    /**
     * Resuelve el QR escaneado: puede ser una pieza (existe en eol_piezas) o un
     * asiento. Devuelve los defectos abiertos flujo=DOOR de ese QR, sugerencias
     * de reemplazo y el stock del buffer.
     */
    public function contexto(Request $request): JsonResponse
    {
        $qr = trim((string) $request->query('qr', ''));
        if ($qr === '') {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Falta el QR'], 422);
        }

        $pieza = EolPieza::where('pieza_qr', $qr)->first();
        $esPieza = (bool) $pieza;

        // El QR del asiento: si escanearon la pieza, el asiento es el que tenia
        // montado (defecto_id_actual -> qr del defecto); si no, es el QR mismo.
        $qrAsiento = $esPieza
            ? optional(EolDefecto::find($pieza->defecto_id_actual))->qr
            : $qr;

        $defectos = $qrAsiento
            ? EolDefecto::where('qr', $qrAsiento)
                ->where('flujo', 'DOOR')
                ->where('estado', 'PENDIENTE')
                ->orderBy('id')
                ->get()
            : collect();

        // tipo_pieza para filtrar sugerencias/stock: SOLO si vino explicito por query
        // param o si el QR escaneado resulto ser una pieza (su propio tipo_pieza).
        // eol_defectos.tipo es BC/CS (posicion en el asiento), NUNCA DOOR/ARM_REST:
        // usarlo como fallback siempre daba 0 sugerencias.
        $tipoPieza = $pieza?->tipo_pieza ?: null;
        if ($request->filled('tipo_pieza')) {
            $tipoPieza = strtoupper((string) $request->query('tipo_pieza'));
        }

        $sugerencias = EolPieza::query()
            ->where('estado', 'DISPONIBLE')
            ->where(function ($w) {
                $w->whereNull('disponible_desde')->orWhere('disponible_desde', '<=', now());
            })
            ->when($tipoPieza, fn ($w) => $w->whereIn('tipo_pieza', self::tiposEquivalentes($tipoPieza)))
            ->orderBy('disponible_desde')
            ->orderBy('updated_at')
            ->limit(20)
            ->get();

        $bufferId = $pieza?->buffer_id ?? $sugerencias->first()?->buffer_id;

        return response()->json([
            'data' => [
                'qr'           => $qr,
                'esPieza'      => $esPieza,
                'pieza'        => $pieza,
                'qrAsiento'    => $qrAsiento,
                // Mismo mapeo que EolDefectoController::reporte() (falla:{}, tipo:{},
                // lado:{}, x, y, color, imagen, vista...): la app consume un solo shape
                // sin importar si el defecto es flujo EOL o DOOR.
                'defectos'     => $defectos->map(fn ($d) => $this->mapToLegacy($d))->values(),
                'sugerencias'  => $sugerencias,
                'stock'        => $this->stockResumen($bufferId, $tipoPieza),
            ],
            'error' => false,
        ]);
    }

    /** PANEL tambien matchea las piezas viejas cargadas como DOOR. */
    private static function tiposEquivalentes(?string $tipo): array
    {
        $t = DoorService::normalizarTipo($tipo);
        if ($t === null) {
            return [];
        }
        return $t === 'PANEL' ? ['PANEL', 'DOOR'] : [$t];
    }

    private function stockResumen(?string $bufferId, ?string $tipoPieza): array
    {
        // Agregado en SQL en vez de traer todas las filas y filtrar en PHP.
        $base = EolPieza::query();
        if ($bufferId) {
            $base->where('buffer_id', $bufferId);
        }
        if ($tipoPieza) {
            $base->whereIn('tipo_pieza', self::tiposEquivalentes($tipoPieza));
        }

        $row = $base->selectRaw(
            "SUM(CASE WHEN estado = 'DISPONIBLE' AND (disponible_desde IS NULL OR disponible_desde <= ?) THEN 1 ELSE 0 END) AS disponibles,"
            . " SUM(CASE WHEN estado = 'DISPONIBLE' AND disponible_desde IS NOT NULL AND disponible_desde > ? THEN 1 ELSE 0 END) AS curando,"
            . " SUM(CASE WHEN estado = 'DEFECTUOSA' THEN 1 ELSE 0 END) AS defectuosas",
            [now(), now()]
        )->first();

        return [
            'disponibles' => (int) ($row->disponibles ?? 0),
            'curando'     => (int) ($row->curando ?? 0),
            'defectuosas' => (int) ($row->defectuosas ?? 0),
        ];
    }

    // =====================================================================
    // Door (cambio de pieza)
    // =====================================================================

    public function crearDoor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr'                   => 'nullable|string|max:120',
            'pieza_defectuosa_qr'  => 'required|string|max:120',
            'pieza_reemplazo_qr'   => 'required|string|max:120',
            'defecto_ids'          => 'nullable|array',
            'defecto_ids.*'        => 'integer',
            'buffer_id'            => 'nullable|string|max:60',
            'buffer_nombre'        => 'nullable|string|max:120',
            'user'                 => 'nullable',
            'puesto_id'            => 'nullable|string|max:60',
            'puesto_nombre'        => 'nullable|string|max:120',
            'observaciones'        => 'nullable|string|max:500',
            'client_uuid'          => 'nullable|string|max:64',
            'modelo'               => 'nullable|string|max:150',
            'lado'                 => 'nullable|string|max:30',
            'codigo'               => 'nullable|string|max:60',
            'secuencia'            => 'nullable|string|max:60',
            'tipo_pieza'           => 'nullable|string|max:30',
        ]);

        try {
            $resultado = DoorService::cambiar($data);
        } catch (QueryException $e) {
            // QueryException extends PDOException extends RuntimeException: si no se
            // atrapa ANTES que \RuntimeException, cae en ese catch y se le devuelve al
            // cliente $e->getMessage() con el SQL crudo. Nunca.
            Log::error('EolDoorController::crearDoor (SQL) : ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => true, 'message' => 'Error de base de datos'], 500);
        } catch (\RuntimeException $e) {
            return $this->errorDesdeRuntime($e);
        } catch (\Throwable $e) {
            Log::error('EolDoorController::crearDoor : ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => true, 'message' => 'No se pudo registrar el cambio de pieza'], 500);
        }

        return response()->json([
            'data'    => $resultado,
            'error'   => false,
            'message' => $resultado['idempotent'] ? 'Cambio de pieza ya registrado (idempotente)' : 'Cambio de pieza registrado',
        ]);
    }

    /**
     * Tablet de door trims, todo en un paso: guarda las marcas del defecto y hace el
     * cambio de pieza en la misma transaccion (si el reemplazo no cumple, no queda nada).
     */
    public function marcarYCambiar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr'                   => 'required|string|max:120',
            'tipo_pieza'           => 'required|string|max:30',
            'circles'              => 'required|array|min:1',
            'circles.*'            => 'array',
            'pieza_defectuosa_qr'  => 'required|string|max:120',
            'pieza_reemplazo_qr'   => 'required|string|max:120',
            'buffer_id'            => 'nullable|string|max:60',
            'buffer_nombre'        => 'nullable|string|max:120',
            'user'                 => 'nullable',
            'puesto_id'            => 'nullable|string|max:60',
            'puesto_nombre'        => 'nullable|string|max:120',
            'observaciones'        => 'nullable|string|max:500',
            'client_uuid'          => 'nullable|string|max:64',
            'modelo'               => 'nullable|string|max:150',
            'lado'                 => 'nullable|string|max:30',
            'codigo'               => 'nullable|string|max:60',
            'secuencia'            => 'nullable|string|max:60',
            'linea'                => 'nullable|string|max:60',
        ]);

        try {
            $resultado = DoorService::marcarYCambiar($data);
        } catch (QueryException $e) {
            Log::error('EolDoorController::marcarYCambiar (SQL) : ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => true, 'message' => 'Error de base de datos'], 500);
        } catch (\RuntimeException $e) {
            return $this->errorDesdeRuntime($e);
        } catch (\Throwable $e) {
            Log::error('EolDoorController::marcarYCambiar : ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => true, 'message' => 'No se pudo registrar el cambio de pieza'], 500);
        }

        return response()->json([
            'data'    => $resultado,
            'error'   => false,
            'message' => $resultado['idempotent'] ? 'Cambio de pieza ya registrado (idempotente)' : 'Defecto y cambio de pieza registrados',
        ]);
    }

    /**
     * Defecto retrabajado en el momento, sin cambio de pieza: guarda las marcas ya
     * como RETRABAJADO y no toca el buffer.
     */
    public function marcarRetrabajo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr'            => 'required|string|max:120',
            'tipo_pieza'    => 'required|string|max:30',
            'circles'       => 'required|array|min:1',
            'circles.*'     => 'array',
            'user'          => 'nullable',
            'puesto_id'     => 'nullable|string|max:60',
            'puesto_nombre' => 'nullable|string|max:120',
            'modelo'        => 'nullable|string|max:150',
            'lado'          => 'nullable|string|max:30',
            'codigo'        => 'nullable|string|max:60',
            'secuencia'     => 'nullable|string|max:60',
            'linea'         => 'nullable|string|max:60',
        ]);

        try {
            $resultado = DoorService::marcarRetrabajo($data);
        } catch (QueryException $e) {
            Log::error('EolDoorController::marcarRetrabajo (SQL) : ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => true, 'message' => 'Error de base de datos'], 500);
        } catch (\RuntimeException $e) {
            return $this->errorDesdeRuntime($e);
        } catch (\Throwable $e) {
            Log::error('EolDoorController::marcarRetrabajo : ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => true, 'message' => 'No se pudo guardar el retrabajo'], 500);
        }

        return response()->json(['data' => $resultado, 'error' => false, 'message' => 'Defecto retrabajado guardado']);
    }

    /**
     * Defectos mandados a retrabajo (sin cambio de pieza) que esperan al
     * retrabajador. Opcional ?qr= para ver solo los de un door trim.
     */
    public function retrabajos(Request $request): JsonResponse
    {
        $q = EolDefecto::query()
            ->where('flujo', 'DOOR')
            ->where('estado', 'PENDIENTE')
            ->orderBy('marcado_at')
            ->orderBy('id');
        if ($request->filled('qr')) {
            $q->where('qr', trim((string) $request->query('qr')));
        }

        return response()->json(['data' => $this->conCambio($q->limit(300)->get()), 'error' => false]);
    }

    /**
     * Defectos que produccion ya resolvio (RETRABAJADO o CAMBIADO) y esperan que
     * Calidad valide el door trim. Opcional ?qr=.
     */
    public function validaciones(Request $request): JsonResponse
    {
        $q = EolDefecto::query()
            ->where('flujo', 'DOOR')
            ->whereIn('estado', ['RETRABAJADO', 'CAMBIADO'])
            ->orderBy('id');
        if ($request->filled('qr')) {
            // El QR puede ser el de la pieza con el defecto o el de la pieza que la
            // reemplazo (Calidad reinspecciona escaneando la pieza nueva).
            $qr = trim((string) $request->query('qr'));
            $doorIds = EolDoor::where('pieza_entregada_qr', $qr)->pluck('id');
            $q->where(function ($w) use ($qr, $doorIds) {
                $w->where('qr', $qr);
                if ($doorIds->isNotEmpty()) {
                    $w->orWhereIn('door_id', $doorIds);
                }
            });
        }

        return response()->json(['data' => $this->conCambio($q->limit(300)->get()), 'error' => false]);
    }

    /** Calidad valida o rechaza lo que hizo produccion. accion = validar | rechazar. */
    public function validarDefecto(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'user'          => 'nullable',
            'accion'        => 'required|in:validar,rechazar',
            'observaciones' => 'nullable|string|max:500',
        ]);
        $user = !empty($data['user']) ? User::find($data['user']) : null;

        try {
            $defecto = DoorService::validarDefecto($id, $user, $data['accion'], $data['observaciones'] ?? null);
        } catch (QueryException $e) {
            Log::error('EolDoorController::validarDefecto (SQL) : ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => true, 'message' => 'Error de base de datos'], 500);
        } catch (\RuntimeException $e) {
            return $this->errorDesdeRuntime($e);
        }

        return response()->json(['data' => $this->mapToLegacy($defecto), 'error' => false, 'message' => 'Defecto ' . ($data['accion'] === 'validar' ? 'validado' : 'rechazado')]);
    }

    /** Forma legacy del defecto + que hizo produccion (cambio de pieza, si hubo). */
    private function conCambio($defectos): array
    {
        $doors = EolDoor::whereIn('id', $defectos->pluck('door_id')->filter()->unique()->values())->get()->keyBy('id');
        return $defectos->map(function ($d) use ($doors) {
            $door = $d->door_id ? $doors->get($d->door_id) : null;
            return $this->mapToLegacy($d) + [
                'cambio' => $door ? [
                    'sale'  => $door->pieza_retirada_qr,
                    'entra' => $door->pieza_entregada_qr,
                    'por'   => $door->cambiado_por_nombre,
                    'fecha' => optional($door->cambiado_at)->format('Y-m-d H:i'),
                ] : null,
                'reaperturas' => (int) ($d->reaperturas ?? 0),
                // Numero de parte del door trim (para chequear el reemplazo).
                'codigo'      => $d->codigo,
            ];
        })->values()->all();
    }

    /** El retrabajador cierra el defecto: queda RETRABAJADO con su usuario. */
    public function retrabajar(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'user'          => 'nullable',
            'observaciones' => 'nullable|string|max:500',
        ]);
        $user = !empty($data['user']) ? \App\Models\User::find($data['user']) : null;

        try {
            $defecto = DoorService::retrabajar($id, $user, $data['observaciones'] ?? null);
        } catch (QueryException $e) {
            Log::error('EolDoorController::retrabajar (SQL) : ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => true, 'message' => 'Error de base de datos'], 500);
        } catch (\RuntimeException $e) {
            return $this->errorDesdeRuntime($e);
        }

        return response()->json(['data' => $this->mapToLegacy($defecto), 'error' => false, 'message' => 'Defecto retrabajado']);
    }

    /**
     * Catalogo de defectos de door trims por pieza (PANEL, ARM REST con/sin cover,
     * GARNISH). Sale de la planilla de Calidad via importar-defectos-doortrim.php.
     */
    public function defectos(): JsonResponse
    {
        $ruta = resource_path('data/defectos-doortrim.json');
        $catalogo = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
        if (!is_array($catalogo) || empty($catalogo['piezas'])) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'No hay lista de defectos de door trim cargada'], 404);
        }
        return response()->json(['data' => $catalogo, 'error' => false]);
    }

    /**
     * Que pieza es un QR de door trim (formato de la app de camaras, sin JITMS):
     * tipo, SD, mano, posicion, numero de parte de /config y curado.
     */
    public function interpretarQr(Request $request): JsonResponse
    {
        $qr = trim((string) $request->input('qr', ''));
        $datos = DoorQrService::interpretar($qr);
        if (!$datos) {
            return response()->json(['data' => null, 'error' => true, 'message' => 'El QR no es de una pieza de door trim'], 404);
        }
        return response()->json(['data' => $datos + ['qr' => strtoupper($qr)], 'error' => false]);
    }

    /**
     * QR que dejo el control final en [TB-PLC].[dbo].[tblDoorControlFinal] (una fila
     * por TIPO). La tablet de Calidad lo consulta cada 2 s y abre sola la pieza
     * cuando aparece un QR nuevo. Solo lectura.
     */
    public function controlFinal(): JsonResponse
    {
        $tabla = (string) config('door.control_final_tabla');
        if (!preg_match('/^[\[\]\w.\- ]+$/', $tabla)) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Tabla de control final mal configurada'], 500);
        }
        try {
            $filas = DB::connection('tbl')->select("SELECT TIPO, QR, RH, LH FROM {$tabla} WITH (NOLOCK)");
        } catch (\Throwable $e) {
            Log::warning('controlFinal: no se pudo leer', ['error' => $e->getMessage()]);
            return response()->json(['data' => [], 'error' => true, 'message' => 'No se pudo leer el control final'], 503);
        }
        $data = array_map(fn ($f) => [
            'tipo' => DoorService::normalizarTipo((string) $f->TIPO),
            'qr'   => trim((string) ($f->QR ?? '')) ?: null,
            'rh'   => (string) ($f->RH ?? ''),
            'lh'   => (string) ($f->LH ?? ''),
        ], $filas);
        return response()->json(['data' => $data, 'error' => false]);
    }

    public function listarDoors(Request $request): JsonResponse
    {
        $q = EolDoor::query()->orderByDesc('id');

        // Rangos sargables (>= desde 00:00, < hasta+1dia) en vez de whereDate(), que
        // envuelve la columna en una funcion y no puede usar el indice de cambiado_at.
        if ($request->filled('desde')) {
            $q->where('cambiado_at', '>=', \Carbon\Carbon::parse($request->query('desde'))->startOfDay());
        }
        if ($request->filled('hasta')) {
            $q->where('cambiado_at', '<', \Carbon\Carbon::parse($request->query('hasta'))->addDay()->startOfDay());
        }
        if ($request->filled('linea')) {
            $q->where('linea', $request->query('linea'));
        }
        if ($request->filled('buffer_id')) {
            $q->where('buffer_id', $request->query('buffer_id'));
        }

        return response()->json(['data' => $q->paginate(50), 'error' => false]);
    }

    // =====================================================================
    // Ciclo de la pieza (tomar / reingresar / validar / scrap)
    // =====================================================================

    public function tomarPieza(Request $request, string $qr): JsonResponse
    {
        $data = $request->validate([
            'user'          => 'nullable',
            'observaciones' => 'nullable|string|max:500',
            'puesto_id'     => 'nullable|string|max:60',
            'puesto_nombre' => 'nullable|string|max:120',
        ]);

        return $this->ejecutar(function () use ($data, $qr) {
            $user = !empty($data['user']) ? User::find($data['user']) : null;
            $pieza = DoorService::tomar($qr, $user, $data['observaciones'] ?? null, [
                'id' => $data['puesto_id'] ?? null, 'nombre' => $data['puesto_nombre'] ?? null,
            ]);
            return ['data' => $pieza, 'message' => 'Pieza tomada para retrabajo'];
        });
    }

    public function reingresarPieza(Request $request, string $qr): JsonResponse
    {
        $data = $request->validate([
            'user'           => 'nullable',
            'resultado'      => 'required|string|in:OK,SCRAP,ok,scrap',
            'observaciones'  => 'nullable|string|max:500',
            'minutosSecado'  => 'nullable|integer|min:1',
            'puesto_id'      => 'nullable|string|max:60',
            'puesto_nombre'  => 'nullable|string|max:120',
            // Fuerza el reingreso OK aunque la pieza ya haya alcanzado el maximo de
            // ciclos de retrabajo (settings.door.maxCiclosRetrabajo).
            'forzar'         => 'nullable|boolean',
        ]);

        return $this->ejecutar(function () use ($data, $qr) {
            $user = !empty($data['user']) ? User::find($data['user']) : null;
            $res = DoorService::reingresar(
                $qr,
                $user,
                $data['resultado'],
                $data['observaciones'] ?? null,
                $data['minutosSecado'] ?? null,
                ['id' => $data['puesto_id'] ?? null, 'nombre' => $data['puesto_nombre'] ?? null],
                (bool) ($data['forzar'] ?? false)
            );
            return ['data' => $res, 'message' => 'Reingreso registrado'];
        });
    }

    public function validarPieza(Request $request, string $qr): JsonResponse
    {
        $data = $request->validate([
            'user'          => 'nullable',
            'accion'        => 'required|string|in:validar,rechazar',
            'observaciones' => 'nullable|string|max:500',
            'puesto_id'     => 'nullable|string|max:60',
            'puesto_nombre' => 'nullable|string|max:120',
        ]);

        return $this->ejecutar(function () use ($data, $qr) {
            $user = !empty($data['user']) ? User::find($data['user']) : null;
            $res = DoorService::validarPieza(
                $qr,
                $user,
                $data['accion'],
                $data['observaciones'] ?? null,
                ['id' => $data['puesto_id'] ?? null, 'nombre' => $data['puesto_nombre'] ?? null]
            );
            return ['data' => $res, 'message' => $data['accion'] === 'validar' ? 'Pieza validada, disponible tras el secado' : 'Reingreso rechazado, pieza vuelve a retrabajo'];
        });
    }

    public function scrapPieza(Request $request, string $qr): JsonResponse
    {
        $data = $request->validate([
            'user'          => 'nullable',
            'observaciones' => 'nullable|string|max:500',
            'puesto_id'     => 'nullable|string|max:60',
            'puesto_nombre' => 'nullable|string|max:120',
        ]);

        return $this->ejecutar(function () use ($data, $qr) {
            $user = !empty($data['user']) ? User::find($data['user']) : null;
            $res = DoorService::scrapPieza(
                $qr,
                $user,
                $data['observaciones'] ?? null,
                ['id' => $data['puesto_id'] ?? null, 'nombre' => $data['puesto_nombre'] ?? null]
            );
            return ['data' => $res, 'message' => 'Pieza dada de baja (SCRAP)'];
        });
    }

    /** Envuelve las acciones del ciclo de pieza con el manejo de errores comun. */
    private function ejecutar(\Closure $accion): JsonResponse
    {
        try {
            $r = $accion();
        } catch (QueryException $e) {
            // Igual que en crearDoor(): QueryException hereda de RuntimeException, hay
            // que atraparla ANTES para no filtrar el SQL crudo en la respuesta.
            Log::error('EolDoorController (SQL): ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => true, 'message' => 'Error de base de datos'], 500);
        } catch (\RuntimeException $e) {
            return $this->errorDesdeRuntime($e);
        } catch (\Throwable $e) {
            Log::error('EolDoorController: ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => true, 'message' => 'No se pudo procesar la accion'], 500);
        }

        return response()->json(['data' => $r['data'], 'error' => false, 'message' => $r['message']]);
    }

    /** Traduce un \RuntimeException de negocio (DoorService) a la respuesta JSON con su codigo HTTP. */
    private function errorDesdeRuntime(\RuntimeException $e): JsonResponse
    {
        $code = (int) $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 422;

        return response()->json(['data' => [], 'error' => true, 'message' => $e->getMessage()], $status);
    }

    // =====================================================================
    // Andon del buffer (TV)
    // =====================================================================

    public function andonBuffer(Request $request): JsonResponse
    {
        $bufferId = $request->query('buffer_id');

        $base = EolPieza::query();
        if ($bufferId) {
            $base->where('buffer_id', $bufferId);
        }

        // Cada estado se consulta aparte (filtro sargable por 'estado', indexado) en
        // vez de traer TODA la tabla de piezas y filtrar en PHP.
        $disponiblesCount = (clone $base)
            ->where('estado', 'DISPONIBLE')
            ->where(function ($w) {
                $w->whereNull('disponible_desde')->orWhere('disponible_desde', '<=', now());
            })
            ->count();

        $curando = (clone $base)
            ->where('estado', 'DISPONIBLE')
            ->whereNotNull('disponible_desde')
            ->where('disponible_desde', '>', now())
            ->get()
            ->map(fn ($p) => [
                'pieza_qr'        => $p->pieza_qr,
                'tipo_pieza'      => $p->tipo_pieza,
                'modelo'          => $p->modelo,
                'minutosRestantes' => max(0, (int) now()->diffInMinutes($p->disponible_desde, false)),
            ])->values();

        $defectuosas = (clone $base)
            ->where('estado', 'DEFECTUOSA')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($p) => [
                'pieza_qr'   => $p->pieza_qr,
                'tipo_pieza' => $p->tipo_pieza,
                'modelo'     => $p->modelo,
                'minutos'    => $p->updated_at ? (int) $p->updated_at->diffInMinutes(now(), true) : null,
            ])->values();

        $enRetrabajo = (clone $base)
            ->where('estado', 'EN_RETRABAJO')
            ->get()
            ->map(fn ($p) => [
                'pieza_qr'   => $p->pieza_qr,
                'tipo_pieza' => $p->tipo_pieza,
                'modelo'     => $p->modelo,
                'tomado_por' => $p->tomado_por_nombre,
                'minutos'    => $p->tomado_at ? (int) $p->tomado_at->diffInMinutes(now(), true) : null,
            ])->values();

        $pendValidacion = (clone $base)
            ->where('estado', 'PEND_VALIDACION')
            ->get()
            ->map(fn ($p) => [
                'pieza_qr'   => $p->pieza_qr,
                'tipo_pieza' => $p->tipo_pieza,
                'modelo'     => $p->modelo,
                'minutos'    => $p->updated_at ? (int) $p->updated_at->diffInMinutes(now(), true) : null,
            ])->values();

        // Stock vs minimo configurado en factory-config (settings.buffers), si existe.
        $minimo = null;
        try {
            $row = \App\Models\FactoryConfig::query()->where('key', 'active')->first();
            $buffers = $row->data['buffers'] ?? [];
            $cfgBuffer = collect($buffers)->firstWhere('id', $bufferId);
            $minimo = $cfgBuffer['minimoAlerta'] ?? null;
        } catch (\Throwable $e) {
            $minimo = null;
        }

        return response()->json([
            'data' => [
                'bufferId'       => $bufferId,
                'disponibles'    => $disponiblesCount,
                'minimoAlerta'   => $minimo,
                'bajoMinimo'     => $minimo !== null ? $disponiblesCount < $minimo : null,
                'curando'        => $curando,
                'defectuosas'    => $defectuosas,
                'enRetrabajo'    => $enRetrabajo,
                'pendValidacion' => $pendValidacion,
                'fechaActualizacion' => now()->format('H:i:s'),
            ],
            'error' => false,
        ]);
    }
}
