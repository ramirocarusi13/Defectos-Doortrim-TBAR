<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MapeaDefectoLegacy;
use App\Models\EolDefecto;
use App\Models\EolDefectoComponente;
use App\Models\EolDefectoEvento;
use App\Models\EpEtiqueta;
use App\Models\User;
use App\Services\InterlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EolDefectoController extends Controller
{
    use MapeaDefectoLegacy;

    /**
     * Registra un evento de trazabilidad del defecto (quien / cuando / que).
     * eventos: ABIERTO | RETRABAJADO | VALIDADO | RECHAZADO
     */
    private function registrarEvento(EolDefecto $defecto, string $evento, ?User $user, ?string $obs = null): void
    {
        EolDefectoEvento::create([
            'eol_defecto_id' => $defecto->id,
            'qr'             => $defecto->qr,
            'evento'         => $evento,
            'user_id'        => $user?->id,
            'user_nombre'    => $user?->name,
            'departamento'   => $user?->departamento,
            'turno'          => $user?->turno,
            // Puesto de control (calidad) donde ocurrio el evento; se copia del defecto.
            'puesto_id'      => $defecto->puesto_id,
            'puesto_nombre'  => $defecto->puesto_nombre,
            'observaciones'  => $obs,
            'created_at'     => now(),
        ]);
    }

    /**
     * Guarda los defectos marcados en fin de linea (uno por cada marca/circulo).
     * Trazabilidad: registra quien marco (calidad). La reparacion (produccion) se
     * completa luego con repararDefecto().
     */
    public function store(Request $request): JsonResponse
    {
        $circles = $request->input('circles', []);
        $qr      = $request->input('qr');
        $userId  = $request->input('user');
        $linea   = $request->input('linea');
        // Puesto de control (fijo por tablet). Acepta puesto_id/puesto_nombre o puestoId/puestoNombre.
        $puestoId     = $request->input('puesto_id', $request->input('puestoId'));
        $puestoNombre = $request->input('puesto_nombre', $request->input('puestoNombre'));
        // Flujo del defecto: 'EOL' (retrabajo clasico, default) o 'DOOR' (linea de
        // cambio de piezas DOOR/ARM_REST, no pasa por interlock JITMS).
        // Whitelist: cualquier valor fuera de EOL/DOOR cae a EOL. 'PRACTICA' NUNCA se
        // acepta desde el payload (se spoofearia el modo capacitacion): la decide el
        // servidor mas abajo segun el departamento del usuario marcador. input() puede
        // venir como array si el cliente manda algo raro; is_string evita el warning
        // del cast a string.
        $flujoInput = $request->input('flujo', 'EOL');
        $flujo = strtoupper(trim(is_string($flujoInput) ? $flujoInput : 'EOL'));
        if (!in_array($flujo, ['EOL', 'DOOR'], true)) {
            $flujo = 'EOL';
        }
        // El deploy TBAR (interlock encendido) no acepta DOOR: ese circuito corre en
        // un deploy aparte con el flag apagado y sin conexion a JITMS.
        if ($flujo === 'DOOR' && config('eol.interlock_enabled', true)) {
            $flujo = 'EOL';
        }

        if (empty($circles)) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'No hay defectos para guardar'], 422);
        }

        // Contexto de la pieza desde la etiqueta (si existe)
        $etiqueta = $qr ? EpEtiqueta::where('qr', $qr)->first() : null;
        $user     = $userId ? User::find($userId) : null;

        // MODO PRACTICA/CAPACITACION: lo decide el SERVIDOR por el departamento del
        // usuario que marca (nunca el payload). Sella flujo='PRACTICA' pisando lo que
        // haya llegado; el defecto sigue el flujo real completo (marcar/retrabajar/
        // validar) pero nunca toca JITMS (interlock, mas abajo) ni entra en la
        // analitica/reportes de produccion (ReporteQcController, DashboardQcController,
        // andon/reporteFmds/reporteDiario aca abajo).
        $esPractica = $user && strtoupper(trim((string) $user->departamento)) === 'CAPACITACION';
        if ($esPractica) {
            $flujo = 'PRACTICA';
        }

        $creados = [];

        DB::beginTransaction();
        try {
            foreach ($circles as $c) {
                $falla = $c['falla'] ?? [];

                // Caracteristica A/B/C del defecto (viene del catalogo de factory-config,
                // sellada al marcarlo): A = grave, exige GL para validar el retrabajo.
                // Whitelist estricta: cualquier valor fuera de A/B/C (o ausente) cae a 'C'.
                $caracteristicaInput = $falla['caracteristica'] ?? $c['caracteristica'] ?? 'C';
                $caracteristica = strtoupper(trim(is_string($caracteristicaInput) ? $caracteristicaInput : 'C'));
                if (!in_array($caracteristica, ['A', 'B', 'C'], true)) {
                    $caracteristica = 'C';
                }

                $defecto = EolDefecto::create([
                    'qr'                 => $qr,
                    'modelo'             => $etiqueta->modelo ?? null,
                    'codigo'             => $etiqueta->codigo ?? null,
                    'sd'                 => $etiqueta->modelo ?? null,
                    'secuencia'          => $etiqueta->secuencia ?? null,
                    'linea'              => $linea ?? ($etiqueta->linea ?? null),
                    'fabrica'            => $request->input('fabrica'),
                    'ubicacion'          => $etiqueta->ubicacion ?? null,
                    'lado'               => $etiqueta->lado ?? ($c['lado'] ?? null),
                    'tipo'               => $etiqueta->tipo ?? ($c['typeName'] ?? null),

                    'falla_id'           => isset($falla['id']) ? (string) $falla['id'] : null,
                    'falla_codigo'       => $falla['code'] ?? $falla['codigo'] ?? null,
                    'falla_nombre'       => $falla['name'] ?? $falla['nombre'] ?? null,
                    'cantidad'           => $c['cantidad'] ?? 1,
                    'es_critico'         => (bool) ($c['esCritico'] ?? false),
                    'es_leve'            => (bool) ($c['esLeve'] ?? false),
                    'observaciones'      => $c['obs'] ?? null,

                    'pos_x'              => $c['x'] ?? null,
                    'pos_y'              => $c['y'] ?? null,
                    'screen_x'           => $c['screenX'] ?? null,
                    'screen_y'           => $c['screenY'] ?? null,
                    'color'              => $c['color'] ?? null,
                    // Vista/foto del modelo donde se marco (para reproducir la marca
                    // en la misma foto en web y app).
                    'vista_id'           => isset($c['imageId']) ? (string) $c['imageId'] : null,
                    'vista_nombre'       => $c['viewName'] ?? null,

                    'estado'             => 'PENDIENTE',
                    'marcado_por'        => $user?->id,
                    'marcado_por_nombre' => $user?->name,
                    'turno'              => $user?->turno,
                    'marcado_at'         => now(),
                    // Puesto de control donde se marco (fijo por tablet).
                    'puesto_id'          => $puestoId,
                    'puesto_nombre'      => $puestoNombre,
                    // 'EOL' (retrabajo clasico) o 'DOOR' (linea de cambio de piezas).
                    'flujo'              => $flujo,
                    // A/B/C: sellado al marcar, no cambia despues aunque se edite el catalogo.
                    'caracteristica'     => $caracteristica,
                ]);

                // Trazabilidad: CALIDAD abrio el defecto
                $this->registrarEvento($defecto, 'ABIERTO', $user, $c['obs'] ?? null);

                $creados[] = $defecto->id;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('EolDefectoController::store : ' . $e->getMessage());
            return response()->json(['data' => [], 'error' => true, 'message' => 'No se pudo guardar el defecto'], 500);
        }

        // INTERLOCK: defecto generado -> carga la fila del asiento en historicocontrolcalidad
        // con Estado='OK' y Mensaje='DEFECTO PENDIENTE'. Solo si esta habilitado en /config.
        // Va con el nombre del operario de calidad logueado en la tablet: en JITMS tiene que
        // verse quien marco el defecto, no un literal 'EOL'.
        // Se salta por completo para el flujo DOOR (no se retrabaja en linea, se cambia la
        // pieza; ese deploy ademas no tiene conexion a JITMS), para el modo PRACTICA
        // (capacitacion: no puede tocar el asiento real en JITMS) y si el flag global
        // esta apagado.
        $cargado = false;
        if ($qr && !empty($creados) && !in_array($flujo, ['DOOR', 'PRACTICA'], true) && config('eol.interlock_enabled', true)) {
            $cargado = InterlockService::cargarPendiente($qr, $user?->name);
        }

        return response()->json([
            'data'    => ['ids' => $creados, 'total' => count($creados), 'interlockCargado' => $cargado],
            'error'   => false,
            'message' => 'Defecto(s) guardado(s)',
        ]);
    }

    /**
     * Registra la reparacion (produccion): quien reparo y cuando.
     */
    public function reparar(Request $request, $id): JsonResponse
    {
        $defecto = EolDefecto::find($id);

        if (!$defecto) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Defecto no encontrado'], 404);
        }

        if ($defecto->flujo === 'DOOR') {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Este defecto se cierra por el flujo door'], 409);
        }

        $userId = $request->input('user');
        $user   = $userId ? User::find($userId) : null;
        $estado = $request->input('estado', 'RETRABAJADO'); // RETRABAJADO | RECHAZADO | SCRAP

        $obs = $request->input('observaciones');

        // Componentes consumidos en el retrabajo (opcional): cada uno con codigo/
        // nombre/accion (CAMBIADO|SCRAP)/cantidad. Si no viene nada valido, no se
        // toca nada (comportamiento identico al actual).
        $resumenComponentes = $this->registrarComponentes($defecto, $request, $user);
        if ($resumenComponentes !== null) {
            $obs = trim(($obs ? $obs . ' | ' : '') . $resumenComponentes);
        }

        // Origen del defecto (opcional): puesto de trabajo / turno / operario que lo
        // genero, elegido por el retrabajador despues de los componentes. Sin
        // 'origen' valido no se toca nada (tablets viejas siguen funcionando).
        $origen = $this->normalizarOrigen($request->input('origen'));
        if ($origen !== null) {
            $obs = trim(($obs ? $obs . ' | ' : '') . $origen['resumen']);
        }

        $defecto->update(array_merge([
            'estado'                => $estado,
            'reparado_por'          => $user?->id,
            'reparado_por_nombre'   => $user?->name,
            'reparado_at'           => now(),
            'reparacion_obs'        => $obs !== null ? mb_substr($obs, 0, 500) : null,
        ], $origen !== null ? $this->camposOrigen($origen, $user) : []));

        // Trazabilidad: PRODUCCION retrabajo. NO libera el interlock todavia: el asiento
        // queda esperando que CALIDAD valide el retrabajo (validar / rechazar).
        $this->registrarEvento($defecto, $estado === 'RETRABAJADO' ? 'RETRABAJADO' : $estado, $user, $obs);

        return response()->json([
            'data'    => $defecto,
            'error'   => false,
            'message' => 'Retrabajo registrado, pendiente de validacion de calidad',
        ]);
    }

    /**
     * Valida el origen del defecto que manda el retrabajador:
     *   { puestoId, puestoCodigo, puestoNombre, sector, turno, operarioLegajo, operarioNombre }
     * Requiere al menos el puesto (codigo o id). El operario puede venir vacio
     * ("NO IDENTIFICADO"). Turno se normaliza a A/B/N (acepta AMARILLO/BLANCO/NARANJA).
     * Devuelve null si no vino nada usable.
     */
    private function normalizarOrigen($input): ?array
    {
        if (!is_array($input)) {
            return null;
        }

        $str = function ($key, int $max) use ($input): ?string {
            $v = $input[$key] ?? null;
            if (!is_string($v) && !is_numeric($v)) {
                return null;
            }
            $v = trim((string) $v);
            return $v === '' ? null : mb_substr($v, 0, $max);
        };

        $puestoCodigo = $str('puestoCodigo', 60);
        $puestoId     = $str('puestoId', 60);
        if ($puestoCodigo === null && $puestoId === null) {
            return null;
        }

        $turno = strtoupper((string) $str('turno', 20));
        $turno = ['AMARILLO' => 'A', 'BLANCO' => 'B', 'NARANJA' => 'N'][$turno] ?? $turno;

        $origen = [
            'puestoId'       => $puestoId ?? $puestoCodigo,
            'puestoCodigo'   => $puestoCodigo ?? $puestoId,
            'puestoNombre'   => $str('puestoNombre', 100),
            'sector'         => $str('sector', 40) !== null ? strtoupper($str('sector', 40)) : null,
            'turno'          => $turno !== '' ? $turno : null,
            'operarioLegajo' => $str('operarioLegajo', 60),
            'operarioNombre' => $str('operarioNombre', 150),
        ];

        $puesto = $origen['puestoCodigo'] . ($origen['puestoNombre'] ? ' ' . $origen['puestoNombre'] : '');
        $quien  = $origen['operarioNombre']
            ? $origen['operarioNombre'] . ($origen['operarioLegajo'] ? ' (' . $origen['operarioLegajo'] . ')' : '')
            : 'NO IDENTIFICADO';
        $origen['resumen'] = 'Origen: ' . $puesto
            . ($origen['turno'] ? ' · turno ' . $origen['turno'] : '')
            . ' · ' . $quien;

        return $origen;
    }

    /** Columnas de eol_defectos que guardan el origen (trazabilidad "quien lo genero"). */
    private function camposOrigen(array $origen, ?User $user): array
    {
        $operacionNombre = trim($origen['puestoCodigo'] . ($origen['puestoNombre'] ? ' - ' . $origen['puestoNombre'] : ''));

        return [
            'operacion_id'      => $origen['puestoId'],
            'operacion_nombre'  => mb_substr($operacionNombre, 0, 150),
            'sector_generacion' => $origen['sector'],
            'turno_generacion'  => $origen['turno'],
            'operario_id'       => $origen['operarioLegajo'],
            'operario_nombre'   => $origen['operarioNombre'],
            'autorizante_id'    => $user?->id,
            'asignado_at'       => now(),
        ];
    }

    /**
     * Guarda en eol_defecto_componentes los componentes que produccion declara como
     * consumidos al retrabajar (opcional). Devuelve un resumen legible ("Componentes:
     * 2x FUNDA (cambio), 1x CIERRE (scrap)") para sumar a las observaciones del evento,
     * o null si no vino nada valido (sin filas, comportamiento identico al actual).
     */
    private function registrarComponentes(EolDefecto $defecto, Request $request, ?User $user): ?string
    {
        $input = $request->input('componentes');
        if (!is_array($input) || empty($input)) {
            return null;
        }

        // Maximo 30 items por request.
        $items = array_slice($input, 0, 30);
        $now   = now();

        $filas  = [];
        $partes = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            // codigo requerido; item sin codigo se ignora.
            $codigoInput = $item['codigo'] ?? null;
            $codigo = is_string($codigoInput) || is_numeric($codigoInput) ? trim((string) $codigoInput) : '';
            if ($codigo === '') {
                continue;
            }
            $codigo = mb_substr($codigo, 0, 60);

            $nombreInput = $item['nombre'] ?? null;
            $nombre = is_string($nombreInput) ? trim($nombreInput) : null;
            $nombre = ($nombre !== null && $nombre !== '') ? mb_substr($nombre, 0, 150) : null;

            // accion: whitelist DESARMADO/CAMBIADO/SCRAP (normalizado upper/trim);
            // cualquier otro valor (o ausente) cae a CAMBIADO. DESARMADO = se
            // desarmo y reparo la pieza existente (no se reemplazo); CAMBIADO = se
            // reemplazo por una nueva (formulario papel FR 04-13-05.1: check vs x).
            $accionInput = $item['accion'] ?? null;
            $accion = strtoupper(trim(is_string($accionInput) ? $accionInput : ''));
            if (!in_array($accion, ['DESARMADO', 'CAMBIADO', 'SCRAP'], true)) {
                $accion = 'CAMBIADO';
            }

            // cantidad: entero 1..99, default 1.
            $cantidadInput = $item['cantidad'] ?? 1;
            $cantidad = is_numeric($cantidadInput) ? (int) $cantidadInput : 1;
            $cantidad = max(1, min(99, $cantidad));

            $filas[] = [
                'eol_defecto_id'    => $defecto->id,
                'qr'                => $defecto->qr,
                'componente_codigo' => $codigo,
                'componente_nombre' => $nombre,
                'accion'            => $accion,
                'cantidad'          => $cantidad,
                'user_id'           => $user?->id,
                'user_nombre'       => $user?->name,
                'turno'             => $user?->turno,
                'created_at'        => $now,
            ];

            $etiquetaAccion = $accion === 'SCRAP' ? 'scrap' : ($accion === 'DESARMADO' ? 'desarme' : 'cambio');
            $partes[] = $cantidad . 'x ' . $codigo . ' (' . $etiquetaAccion . ')';
        }

        if (empty($filas)) {
            return null;
        }

        EolDefectoComponente::insert($filas);

        return 'Componentes: ' . implode(', ', $partes);
    }

    /**
     * CALIDAD VALIDA el retrabajo (control final del retrabajo).
     *  - accion = 'validar'  -> VALIDADO. Si la funda ya no tiene defectos abiertos,
     *                           libera el interlock (saca la fila OK / DEFECTO PENDIENTE).
     *  - accion = 'rechazar' -> reabre el defecto (PENDIENTE), suma una reapertura.
     *                           El interlock sigue retenido.
     * Todo queda en el historial de eventos (quien / cuando / obs).
     */
    public function validar(Request $request, $id): JsonResponse
    {
        $defecto = EolDefecto::find($id);
        if (!$defecto) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Defecto no encontrado'], 404);
        }

        if ($defecto->flujo === 'DOOR') {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Este defecto se cierra por el flujo door'], 409);
        }

        $userId = $request->input('user');
        $user   = $userId ? User::find($userId) : null;
        $accion = strtolower($request->input('accion', 'validar')); // validar | rechazar
        $obs    = $request->input('observaciones');

        if ($accion === 'rechazar') {
            // Vuelve a empezar: reabre el defecto.
            $defecto->update([
                'estado'       => 'PENDIENTE',
                'reaperturas'  => (int) ($defecto->reaperturas ?? 0) + 1,
                // limpio la reparacion previa porque hay que rehacerla
                'reparado_por'        => null,
                'reparado_por_nombre' => null,
                'reparado_at'         => null,
            ]);
            $this->registrarEvento($defecto, 'RECHAZADO', $user, $obs);

            return response()->json([
                'data'    => $defecto,
                'error'   => false,
                'message' => 'Retrabajo rechazado, defecto reabierto',
            ]);
        }

        // VALIDAR (OK). Defectos tipo A son graves: no se pueden dar por validados sin
        // que un GL (rol >= 30) escanee su codigo. B/C validan como siempre.
        // Si el que valida YA es GL o superior, autoriza el mismo sin escaneo extra.
        $glNombre = null;
        $gl       = null;
        if ($defecto->caracteristica === 'A') {
            if ($user && (int) $user->rol >= 30) {
                $gl = $user;
            } else {
                $glCodigo = $request->input('gl_codigo');
                if (!$glCodigo || !is_string($glCodigo) || trim($glCodigo) === '') {
                    return response()->json([
                        'data'    => ['requiereGl' => true],
                        'error'   => true,
                        'message' => 'Este defecto es tipo A: se necesita la autorizacion de un GL',
                    ], 422);
                }

                $gl = User::where('cod_autorizacion', trim($glCodigo))->first();
                if (!$gl || (int) $gl->rol < 30) {
                    return response()->json([
                        'data'    => ['requiereGl' => true],
                        'error'   => true,
                        'message' => 'El codigo escaneado no corresponde a un GL o superior',
                    ], 403);
                }
            }

            $glNombre = $gl->name;
        }

        $updateData = [
            'estado'              => 'VALIDADO',
            'validado_por'        => $user?->id,
            'validado_por_nombre' => $user?->name,
            'validado_at'         => now(),
        ];
        if ($glNombre !== null) {
            $updateData['validado_gl_por']        = $gl->id;
            $updateData['validado_gl_por_nombre'] = $glNombre;
        }
        $defecto->update($updateData);

        $obsEvento = $glNombre !== null
            ? trim(($obs ? $obs . ' | ' : '') . "Autorizo GL: $glNombre")
            : $obs;
        $this->registrarEvento($defecto, 'VALIDADO', $user, $obsEvento);

        // Interlock: si la funda ya no tiene NINGUN defecto abierto (PENDIENTE/RETRABAJADO),
        // se libera (calidad valido todo). Se salta para defectos del flujo DOOR y del modo
        // PRACTICA (ninguno de los dos paso por interlock al marcarse) y si el flag global
        // esta apagado.
        $liberado = false;
        $quedanAbiertos = EolDefecto::where('qr', $defecto->qr)
            ->whereIn('estado', ['PENDIENTE', 'RETRABAJADO'])
            ->exists();
        if ($defecto->qr && !$quedanAbiertos && !in_array($defecto->flujo, ['DOOR', 'PRACTICA'], true) && config('eol.interlock_enabled', true)) {
            // El nombre del inspector queda en el Mensaje de la NG del PLC
            // ('DEFECTO RETRABAJADO OK - <operario>'): rastro del ciclo cerrado en JITMS.
            $liberado = InterlockService::confirmarRetrabajo($defecto->qr, $user?->name);
        }

        return response()->json([
            'data'    => $defecto,
            'error'   => false,
            'message' => 'Retrabajo validado por calidad',
            'interlockLiberado' => $liberado,
            'quedanAbiertos'    => $quedanAbiertos,
        ]);
    }

    /**
     * Historial de eventos (trazabilidad) de un defecto, o de todos los de un QR.
     */
    /**
     * Ficha completa de UN defecto (boton "TRAZABILIDAD" del detalle en la tablet):
     * quien lo marco y donde, retrabajo, componentes, ORIGEN (puesto / turno /
     * operario que lo genero), validacion de calidad y la linea de tiempo de eventos.
     */
    public function trazabilidad($id): JsonResponse
    {
        $d = EolDefecto::find($id);
        if (!$d) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Defecto no encontrado'], 404);
        }

        $fmt = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y H:i') : null;
        $turnoLabel = fn ($t) => $t ? ($t . (['A' => ' (AMARILLO)', 'B' => ' (BLANCO)', 'N' => ' (NARANJA)'][strtoupper($t)] ?? '')) : null;

        $componentes = EolDefectoComponente::where('eol_defecto_id', $d->id)->orderBy('id')->get()
            ->map(fn ($c) => [
                'codigo'   => $c->componente_codigo,
                'nombre'   => $c->componente_nombre,
                'accion'   => $c->accion,
                'cantidad' => (int) $c->cantidad,
                'por'      => $c->user_nombre,
                'fecha'    => $fmt($c->created_at),
            ])->values();

        $eventos = EolDefectoEvento::where('eol_defecto_id', $d->id)->orderBy('created_at')->orderBy('id')->get()
            ->map(fn ($e) => [
                'evento'        => $e->evento,
                'user'          => $e->user_nombre,
                'departamento'  => $e->departamento,
                'turno'         => $e->turno,
                'observaciones' => $e->observaciones,
                'fecha'         => $fmt($e->created_at),
            ])->values();

        $tieneOrigen = $d->asignado_at !== null || $d->operacion_nombre !== null;

        return response()->json([
            'data' => [
                'id'             => $d->id,
                'qr'             => $d->qr,
                'modelo'         => $d->modelo,
                'tipo'           => $d->tipo,
                'lado'           => $d->lado,
                'secuencia'      => $d->secuencia,
                'falla'          => trim(($d->falla_codigo ? $d->falla_codigo . ' - ' : '') . ($d->falla_nombre ?? '')),
                'caracteristica' => $d->caracteristica ?? null,
                'estado'         => $d->estado,
                'reaperturas'    => (int) ($d->reaperturas ?? 0),
                'marcado' => [
                    'por'    => $d->marcado_por_nombre,
                    'turno'  => $turnoLabel($d->turno),
                    'puesto' => $d->puesto_nombre,
                    'fecha'  => $fmt($d->marcado_at),
                    'obs'    => $d->observaciones,
                ],
                'retrabajo' => $d->reparado_at ? [
                    'por'   => $d->reparado_por_nombre,
                    'fecha' => $fmt($d->reparado_at),
                    'obs'   => $d->reparacion_obs,
                ] : null,
                'componentes' => $componentes,
                'origen' => $tieneOrigen ? [
                    'puesto'       => $d->operacion_nombre,
                    'sector'       => $d->sector_generacion ?? null,
                    'turno'        => $turnoLabel($d->turno_generacion),
                    'legajo'       => $d->operario_id,
                    'operario'     => $d->operario_nombre,
                    'declaradoPor' => $d->autorizante_id ? User::where('id', $d->autorizante_id)->value('name') : null,
                    'fecha'        => $fmt($d->asignado_at),
                ] : null,
                'validacion' => $d->validado_at ? [
                    'por'   => $d->validado_por_nombre,
                    'fecha' => $fmt($d->validado_at),
                ] : null,
                'eventos' => $eventos,
            ],
            'error' => false,
        ]);
    }

    public function eventos(Request $request, $id = null): JsonResponse
    {
        $q = EolDefectoEvento::query()->orderBy('created_at')->orderBy('id');

        if ($id) {
            $q->where('eol_defecto_id', $id);
        } elseif ($request->filled('qr')) {
            $q->where('qr', $request->query('qr'));
        }

        $eventos = $q->limit(1000)->get()->map(fn ($e) => [
            'id'           => $e->id,
            'evento'       => $e->evento,
            'user'         => $e->user_nombre,
            'departamento' => $e->departamento,
            'turno'        => $e->turno,
            'observaciones' => $e->observaciones,
            'fecha'        => optional($e->created_at)->format('Y-m-d H:i'),
        ]);

        return response()->json(['data' => $eventos, 'error' => false]);
    }

    /**
     * ANDON: fundas/asientos con retrabajos PENDIENTES (retenidos en el PLC).
     * Para mostrar en una pantalla en la ruta/linea. Agrupa por QR.
     */
    public function andon(Request $request): JsonResponse
    {
        $query = EolDefecto::where('estado', 'PENDIENTE');
        if ($request->filled('flujo')) {
            $query->where('flujo', strtoupper($request->query('flujo')));
        } else {
            // Sin flujo explicito: el andon de produccion no muestra los defectos del
            // modo practica/capacitacion.
            $query->where(fn ($q) => $q->whereNull('flujo')->orWhere('flujo', '<>', 'PRACTICA'));
        }
        $pendientes = $query->orderBy('marcado_at')
            ->limit(500)
            ->get();

        $fundas = $pendientes->groupBy('qr')->map(function ($grupo) {
            $primero = $grupo->first();
            return [
                'qr'          => $primero->qr,
                'modelo'      => $primero->modelo,
                'lado'        => $primero->lado,
                'secuencia'   => $primero->secuencia,
                'linea'       => $primero->linea,
                'cantidad'    => $grupo->count(),
                'marcado_at'  => optional($primero->marcado_at)->format('Y-m-d H:i'),
                'minutos'     => $primero->marcado_at ? (int) $primero->marcado_at->diffInMinutes(now(), true) : null,
                'defectos'    => $grupo->map(fn ($d) => [
                    'falla_codigo' => $d->falla_codigo,
                    'falla_nombre' => $d->falla_nombre,
                    'tipo'         => $d->tipo,
                    'es_leve'      => (bool) $d->es_leve,
                    'marcado_por'  => $d->marcado_por_nombre,
                ])->values(),
            ];
        })->sortByDesc('minutos')->values();

        return response()->json([
            'data' => [
                'total'             => $fundas->count(),
                'totalDefectos'     => $pendientes->count(),
                'fundas'            => $fundas,
                'interlockActivo'   => InterlockService::habilitado(),
                'fechaActualizacion' => now()->format('H:i:s'),
            ],
            'error' => false,
        ]);
    }

    /**
     * ANDON INTERLOCK: lee de historicocontrolcalidad las filas con Mensaje='DEFECTO
     * PENDIENTE' (las que cargo EOL) para mostrarlas en una pantalla roja de alerta.
     * Enriquece con el defecto de eol_defectos por QR (modelo/falla).
     */
    public function andonInterlock(Request $request): JsonResponse
    {
        // Defectos ABIERTOS en nuestra base (pendiente de retrabajo o de validacion de calidad).
        $pendientes = EolDefecto::whereIn('estado', ['PENDIENTE', 'RETRABAJADO'])
            ->whereNotNull('qr')
            ->orderBy('marcado_at')
            ->get();

        // De esos, los que en historicocontrolcalidad tienen OK + NG (se escanearon en
        // el ultimo puesto y el PLC genero la etiqueta repetida) -> alerta roja.
        $qrsAlerta = array_flip(InterlockService::qrsConOkYNg(
            $pendientes->pluck('qr')->unique()->values()->all()
        ));

        $asientos = $pendientes
            ->filter(fn ($d) => isset($qrsAlerta[$d->qr]))
            ->groupBy('qr')
            ->map(function ($g) {
                $d = $g->first();
                return [
                    'qr'          => $d->qr,
                    'modelo'      => $d->modelo,
                    'sd'          => $d->modelo,
                    'posicion'    => trim(($d->tipo ? $d->tipo . ' ' : '') . ($d->lado ?? '')),
                    'secuencia'   => $d->secuencia,
                    'fecha'       => optional($d->marcado_at)->format('Y-m-d'),
                    'falla'       => trim(($d->falla_codigo ? $d->falla_codigo . ' - ' : '') . $d->falla_nombre),
                    'marcado_por' => $d->marcado_por_nombre,
                    'cantidad'    => $g->count(),
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'total'              => $asientos->count(),
                'asientos'           => $asientos,
                'interlockActivo'    => InterlockService::habilitado(),
                'fechaActualizacion' => now()->format('H:i:s'),
            ],
            'error' => false,
        ]);
    }

    /**
     * Lista defectos para trazabilidad / tablero de produccion.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EolDefecto::query()->orderByDesc('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }
        if ($request->filled('linea')) {
            $query->where('linea', $request->query('linea'));
        }
        if ($request->filled('qr')) {
            $query->where('qr', $request->query('qr'));
        }
        if ($request->filled('flujo')) {
            $query->where('flujo', strtoupper($request->query('flujo')));
        }

        return response()->json(['data' => $query->limit(500)->get(), 'error' => false]);
    }

    /**
     * Asigna quien GENERO el defecto (operario / operacion / turno / linea) — trazabilidad.
     */
    public function asignarOperador(Request $request, $id): JsonResponse
    {
        $defecto = EolDefecto::find($id);

        if (!$defecto) {
            return response()->json(['data' => [], 'error' => true, 'message' => 'Defecto no encontrado'], 404);
        }

        $defecto->update([
            'linea_generacion'  => $request->input('linea'),
            'es_sublinea'       => (bool) $request->input('sublinea', false),
            'turno_generacion'  => $request->input('turno'),
            'operacion_id'      => $request->input('operacion') !== null ? (string) $request->input('operacion') : null,
            'operacion_nombre'  => $request->input('operacionNombre'),
            'operario_id'       => $request->input('operario') !== null ? (string) $request->input('operario') : null,
            'operario_nombre'   => $request->input('operarioNombre'),
            'autorizante_id'    => $request->input('userAutorizante'),
            'asignado_at'       => now(),
        ]);

        return response()->json(['data' => $defecto, 'error' => false, 'message' => 'Operario asignado']);
    }

    /**
     * Devuelve los defectos de una etiqueta (por QR) con la forma que esperan las
     * pantallas de Fin de Linea y Retrabajo (compatibilidad con la UI legacy).
     */
    public function reporte(Request $request): JsonResponse
    {
        $qr = $request->input('qr') ?? $request->query('qr');
        $qr = $qr ? str_replace("'", "-", trim($qr)) : null;

        if (!$qr) {
            return response()->json(['data' => [], 'error' => false]);
        }

        $defectos = EolDefecto::where('qr', $qr)->orderBy('id')->get()
            ->map(fn ($d) => $this->mapToLegacy($d))
            ->all();

        return response()->json(['data' => $defectos, 'error' => false]);
    }

    /**
     * Reporte tipo FMDS para defectos EOL: enfocado en QUE defecto, QUE operario lo
     * encontro y TURNO. Filtrable por rango de fechas. (Sin operacion: aun no esta
     * definida.)
     */
    public function reporteFmds(Request $request): JsonResponse
    {
        $desde = $request->input('fechaDesde'); // 'Y-m-d'
        $hasta = $request->input('fechaHasta');
        $turno = $request->input('turno');

        // Reporte de produccion: nunca incluye el modo practica/capacitacion.
        $q = EolDefecto::query()->where(fn ($qq) => $qq->whereNull('flujo')->orWhere('flujo', '<>', 'PRACTICA'));
        if ($desde) {
            $q->whereDate('marcado_at', '>=', $desde);
        }
        if ($hasta) {
            $q->whereDate('marcado_at', '<=', $hasta);
        }
        if ($turno) {
            $q->where('turno', $turno);
        }

        $rows = $q->orderByDesc('marcado_at')->limit(5000)->get();

        $defectos = $rows->map(fn ($d) => [
            'id'           => $d->id,
            'qr'           => $d->qr,
            'falla_codigo' => $d->falla_codigo,
            'falla_nombre' => $d->falla_nombre,
            'operario'     => $d->marcado_por_nombre,
            'turno'        => $d->turno,
            'puesto'       => $d->puesto_nombre,
            'modelo'       => $d->modelo,
            'lado'         => $d->lado,
            'tipo'         => $d->tipo,
            'estado'       => $d->estado === 'REPARADO' ? 'RETRABAJADO' : ($d->estado ?: 'PENDIENTE'),
            'es_leve'      => (bool) $d->es_leve,
            'es_critico'   => (bool) $d->es_critico,
            'marcado_at'   => optional($d->marcado_at)->format('Y-m-d H:i'),
        ])->values();

        $agrupar = fn ($keyFn, $vacio) => $rows
            ->groupBy(fn ($d) => $keyFn($d) ?: $vacio)
            ->map(fn ($g, $k) => ['nombre' => $k, 'cantidad' => $g->count()])
            ->sortByDesc('cantidad')
            ->values();

        return response()->json([
            'data' => [
                'total'       => $rows->count(),
                'leves'       => $rows->where('es_leve', true)->count(),
                'criticos'    => $rows->where('es_critico', true)->count(),
                'pendientes'  => $rows->where('estado', 'PENDIENTE')->count(),
                'defectos'    => $defectos,
                'topFallas'   => $agrupar(fn ($d) => $d->falla_nombre, 'SIN DEFINIR'),
                'porOperario' => $agrupar(fn ($d) => $d->marcado_por_nombre, 'SIN OPERARIO'),
                'porPuesto'   => $agrupar(fn ($d) => $d->puesto_nombre, 'SIN PUESTO'),
                'porTurno'    => $agrupar(fn ($d) => $d->turno, 'S/T'),
                'fechaActualizacion' => now()->toISOString(),
            ],
            'error' => false,
        ]);
    }

    /**
     * Reporte DIARIO: cuantos defectos de cada tipo salieron cada dia.
     * Devuelve una matriz fallas x dias para armar la tabla y el grafico.
     */
    public function reporteDiario(Request $request): JsonResponse
    {
        $desde = $request->input('fechaDesde'); // 'Y-m-d'
        $hasta = $request->input('fechaHasta');
        $turno = $request->input('turno');

        // Reporte de produccion: nunca incluye el modo practica/capacitacion.
        $q = EolDefecto::query()->where(fn ($qq) => $qq->whereNull('flujo')->orWhere('flujo', '<>', 'PRACTICA'));
        if ($desde) {
            $q->whereDate('marcado_at', '>=', $desde);
        }
        if ($hasta) {
            $q->whereDate('marcado_at', '<=', $hasta);
        }
        if ($turno) {
            $q->where('turno', $turno);
        }

        $rows = $q->orderBy('marcado_at')->limit(20000)->get();

        $dia = fn ($d) => optional($d->marcado_at)->format('Y-m-d') ?: 'sin-fecha';

        // Lista de dias del rango (si esta definido); si no, los dias presentes.
        $dias = [];
        if ($desde && $hasta) {
            $cursor = \Carbon\Carbon::parse($desde)->startOfDay();
            $fin    = \Carbon\Carbon::parse($hasta)->startOfDay();
            while ($cursor->lte($fin)) {
                $dias[] = $cursor->format('Y-m-d');
                $cursor->addDay();
            }
        } else {
            $dias = $rows->map($dia)->unique()->sort()->values()->all();
        }

        // Agrupo por tipo de defecto y cuento por dia.
        $porFalla = $rows
            ->groupBy(fn ($d) => ($d->falla_codigo ? $d->falla_codigo . ' - ' : '') . ($d->falla_nombre ?: 'SIN DEFINIR'))
            ->map(function ($grupo, $nombre) use ($dias, $dia) {
                $conteoDia = $grupo->groupBy($dia)->map->count();
                $porDia = [];
                foreach ($dias as $f) {
                    $porDia[$f] = (int) ($conteoDia[$f] ?? 0);
                }
                return [
                    'falla'    => $nombre,
                    'codigo'   => $grupo->first()->falla_codigo,
                    'nombre'   => $grupo->first()->falla_nombre,
                    'total'    => $grupo->count(),
                    'porDia'   => $porDia,
                ];
            })
            ->sortByDesc('total')
            ->values();

        // Totales por dia (todas las fallas juntas).
        $totalesPorDia = [];
        foreach ($dias as $f) {
            $totalesPorDia[$f] = (int) $rows->filter(fn ($d) => $dia($d) === $f)->count();
        }

        return response()->json([
            'data' => [
                'dias'          => $dias,
                'fallas'        => $porFalla,
                'totalesPorDia' => $totalesPorDia,
                'total'         => $rows->count(),
                'fechaActualizacion' => now()->toISOString(),
            ],
            'error' => false,
        ]);
    }

}
