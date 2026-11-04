<?php

namespace App\Services;

use App\Models\EolDoor;
use App\Models\EolDefecto;
use App\Models\EolDefectoEvento;
use App\Models\EolPieza;
use App\Models\EolPiezaEvento;
use App\Models\FactoryConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Logica de negocio de la linea de door trims (cambio de piezas PANEL / ARM_REST /
 * GARNISH, que juntas forman el set del door trim).
 *
 * El defecto no se retrabaja en el asiento: la pieza defectuosa se cambia por
 * una buena del mini buffer. La defectuosa queda en el buffer, la retoma un
 * retrabajador, se repara, reingresa (curado / secado del adhesivado) y
 * CALIDAD valida antes de que vuelva a estar DISPONIBLE.
 *
 * Ciclo de la pieza:  DISPONIBLE -> EN_ASIENTO -> DEFECTUOSA -> EN_RETRABAJO
 *                      -> PEND_VALIDACION -> DISPONIBLE (+ SCRAP / BAJA)
 * Ciclo del defecto:   PENDIENTE -> CAMBIADO -> RETRABAJADO -> VALIDADO (+ SCRAP)
 *
 * Los errores de negocio (pieza no encontrada / estado invalido / conflicto)
 * se señalizan con \RuntimeException($mensaje, $httpStatus): el controller los
 * atrapa y arma la respuesta {data,error,message} con ese codigo HTTP.
 */
class DoorService
{
    private const DEFAULTS = [
        'requiereValidacionCalidad'      => true,
        'minutosSecado'                  => 240,
        'permitirAltaAutomaticaRetirada' => true,
        'maxCiclosRetrabajo'             => 3,
        // Requisitos del reemplazo: mismo modelo y mismo lado que la pieza que sale
        // (o que el door trim, si la que sale no estaba registrada).
        'exigirMismoModelo'              => true,
        'exigirMismoLado'                => true,
    ];

    /** Piezas que forman el set de un door trim. 'DOOR' es el nombre viejo de PANEL. */
    public const TIPOS_PIEZA = ['PANEL', 'ARM_REST', 'GARNISH'];

    public static function normalizarTipo(?string $tipo): ?string
    {
        $t = strtoupper(trim((string) $tipo));
        $t = str_replace([' ', '-'], '_', $t);
        if ($t === '') {
            return null;
        }
        return match ($t) {
            'DOOR', 'PANEL', 'DOOR_PANEL' => 'PANEL',
            'ARMREST', 'ARM_REST'         => 'ARM_REST',
            'GARNISH', 'GARNISCH'         => 'GARNISH',
            default                       => $t,
        };
    }

    public static function normalizarLado(?string $lado): ?string
    {
        $l = strtoupper(trim((string) $lado));
        if ($l === '') {
            return null;
        }
        return match ($l) {
            'LH', 'L', 'IZQ', 'IZQUIERDO', 'IZQUIERDA', 'LEFT'  => 'LH',
            'RH', 'R', 'DER', 'DERECHO', 'DERECHA', 'RIGHT'     => 'RH',
            default                                             => $l,
        };
    }

    public static function normalizarModelo(?string $modelo): ?string
    {
        $m = strtoupper(trim((string) $modelo));
        return $m === '' ? null : $m;
    }

    /**
     * Requisitos que tiene que cumplir la pieza de reemplazo frente a la pieza que
     * sale y al door trim. Solo se compara un dato cuando los dos lados lo tienen
     * cargado. Lanza \RuntimeException(422) con el motivo para mostrar en la tablet.
     */
    private static function exigirCompatibilidad(EolPieza $reemplazo, ?EolPieza $defectuosa, array $payload, array $settings): void
    {
        $tipoReemplazo = self::normalizarTipo($reemplazo->tipo_pieza);
        $tipoElegido   = self::normalizarTipo($payload['tipo_pieza'] ?? null);
        if ($tipoElegido && $tipoReemplazo && $tipoElegido !== $tipoReemplazo) {
            throw new \RuntimeException("La pieza de reemplazo es {$tipoReemplazo} y se eligio cambiar {$tipoElegido}", 422);
        }
        if ($defectuosa) {
            $tipoDefectuosa = self::normalizarTipo($defectuosa->tipo_pieza);
            if ($tipoDefectuosa && $tipoReemplazo && $tipoDefectuosa !== $tipoReemplazo) {
                throw new \RuntimeException("La pieza de reemplazo es {$tipoReemplazo} y la que sale es {$tipoDefectuosa}", 422);
            }
            if ($tipoElegido && $tipoDefectuosa && $tipoElegido !== $tipoDefectuosa) {
                throw new \RuntimeException("La pieza escaneada como defectuosa es {$tipoDefectuosa} y se eligio cambiar {$tipoElegido}", 422);
            }
        }

        $comparar = [];
        if ($settings['exigirMismoModelo'] ?? true) {
            $comparar['modelo'] = [[self::class, 'normalizarModelo'], 'modelo'];
        }
        if ($settings['exigirMismoLado'] ?? true) {
            $comparar['lado'] = [[self::class, 'normalizarLado'], 'lado'];
        }
        foreach ($comparar as $campo => [$norm, $etiqueta]) {
            $valorReemplazo = $norm($reemplazo->{$campo});
            if (!$valorReemplazo) {
                continue;
            }
            // Se compara contra la pieza que sale (mismo maestro, mismo formato). Si la
            // que sale no esta registrada: el LADO se toma del door trim y el MODELO del
            // numero de parte de su QR ("codigo", ej 74220-0K140), no del SD.
            $valorRef = $defectuosa ? $norm($defectuosa->{$campo}) : null;
            $quien = 'la pieza que sale';
            $prefijo = false;
            if (!$valorRef) {
                $valorRef = $norm($campo === 'lado' ? ($payload['lado'] ?? null) : ($payload['codigo'] ?? null));
                $quien = 'el door trim';
                // El maestro puede tener el codigo con sufijo (74220-0K140-C0).
                $prefijo = $campo === 'modelo';
            }
            $coincide = $prefijo
                ? (str_starts_with($valorReemplazo, (string) $valorRef) || str_starts_with((string) $valorRef, $valorReemplazo))
                : $valorRef === $valorReemplazo;
            if ($valorRef && !$coincide) {
                throw new \RuntimeException(
                    "La pieza de reemplazo es de {$etiqueta} {$valorReemplazo} y {$quien} es {$valorRef}",
                    422
                );
            }
        }
    }

    /** Settings de la linea de door, desde factory-config (settings.door). */
    public static function settings(): array
    {
        try {
            $row = FactoryConfig::query()->where('key', 'active')->first();
            $door = $row->data['settings']['door'] ?? [];
            return array_replace(self::DEFAULTS, is_array($door) ? $door : []);
        } catch (\Throwable $e) {
            return self::DEFAULTS;
        }
    }

    // =====================================================================
    // DOOR: pieza defectuosa <-> pieza de reemplazo del buffer
    // =====================================================================

    /**
     * @param array $payload {qr, pieza_defectuosa_qr, pieza_reemplazo_qr, defecto_ids?,
     *                        buffer_id, buffer_nombre, user, puesto_id, puesto_nombre,
     *                        observaciones?, client_uuid?, modelo?, lado?, codigo?, secuencia?}
     * @return array {door, piezaDefectuosa, piezaReemplazo, defectosActualizados, altaAutomatica, idempotent}
     */
    public static function cambiar(array $payload): array
    {
        $qr                = trim((string) ($payload['qr'] ?? ''));
        $piezaDefectuosaQr = trim((string) ($payload['pieza_defectuosa_qr'] ?? ''));
        $piezaReemplazoQr  = trim((string) ($payload['pieza_reemplazo_qr'] ?? ''));
        $bufferId          = $payload['buffer_id'] ?? null;
        $bufferNombre      = $payload['buffer_nombre'] ?? null;
        $puestoId          = $payload['puesto_id'] ?? null;
        $puestoNombre      = $payload['puesto_nombre'] ?? null;
        $obs               = $payload['observaciones'] ?? null;
        $clientUuid        = $payload['client_uuid'] ?? null;
        $userId            = $payload['user'] ?? null;
        $user              = $userId ? User::find($userId) : null;

        if ($piezaDefectuosaQr === '' || $piezaReemplazoQr === '') {
            throw new \RuntimeException('Falta el QR de la pieza defectuosa o de la pieza de reemplazo', 422);
        }

        // Lo que dicen los QR (formato de la app de camaras): tipo, SD, mano,
        // posicion y curado. null si el QR no tiene ese formato (piezas viejas/demo).
        $infoSale  = DoorQrService::interpretar($piezaDefectuosaQr);
        $infoEntra = DoorQrService::interpretar($piezaReemplazoQr);

        // El QR del garnish es solo el SD: todos los de un modelo son iguales, asi
        // que no se sigue pieza por pieza. Solo se controla que sea el mismo SD.
        if (($infoSale['tipo'] ?? null) === 'GARNISH' || ($infoEntra['tipo'] ?? null) === 'GARNISH') {
            return self::cambiarGarnish($payload, $infoSale, $infoEntra, $user);
        }

        if ($piezaDefectuosaQr === $piezaReemplazoQr) {
            throw new \RuntimeException('La pieza defectuosa y la de reemplazo no pueden ser la misma', 422);
        }

        // Idempotencia: si ya se proceso este client_uuid (reintento de red / resubmit
        // desde la app offline-first), devuelvo el cambio existente sin crear nada nuevo.
        // Este pre-check es best-effort (esta fuera de la transaccion); la idempotencia
        // real bajo carrera la da crearDoorConIdempotencia() mas abajo.
        if ($clientUuid) {
            $existente = EolDoor::where('client_uuid', $clientUuid)->first();
            if ($existente) {
                return self::resultadoIdempotente($existente);
            }
        }

        $settings = self::settings();

        try {
            return DB::transaction(function () use (
                $qr, $piezaDefectuosaQr, $piezaReemplazoQr, $bufferId, $bufferNombre,
                $puestoId, $puestoNombre, $obs, $clientUuid, $user, $settings, $payload,
                $infoSale, $infoEntra
            ) {
            // Lock de ambas piezas: evita que dos tablets cambien la misma pieza a la vez.
            $reemplazo = EolPieza::where('pieza_qr', $piezaReemplazoQr)->lockForUpdate()->first();
            if (!$reemplazo && $infoEntra) {
                // Pieza del buffer que nunca se dio de alta: el QR alcanza para saber
                // que es y cuando termina de curar.
                $reemplazo = self::altaDesdeQr($piezaReemplazoQr, $infoEntra, $user, $bufferId, $bufferNombre,
                    'Alta automatica al usarse como reemplazo (datos del QR)');
            }
            if (!$reemplazo) {
                throw new \RuntimeException('La pieza de reemplazo no existe en el sistema', 404);
            }
            if ($reemplazo->estado !== 'DISPONIBLE') {
                throw new \RuntimeException("La pieza de reemplazo no esta disponible (estado actual: {$reemplazo->estado})", 409);
            }
            if ($reemplazo->disponible_desde && now()->lt($reemplazo->disponible_desde)) {
                throw new \RuntimeException('La pieza de reemplazo todavia esta curando (faltan ' . self::faltan($reemplazo->disponible_desde) . ')', 409);
            }
            if ($infoEntra && $infoEntra['curado_ok'] === false) {
                throw new \RuntimeException('La pieza de reemplazo todavia esta curando (faltan ' . self::faltan($infoEntra['curado_hasta']) . ')', 409);
            }

            $defectuosa = EolPieza::where('pieza_qr', $piezaDefectuosaQr)->lockForUpdate()->first();

            // Mismo tipo / modelo / lado que la pieza que sale y que el door trim. Si
            // los dos QR son de la app de camaras se compara lo que dicen los QR.
            if ($infoSale && $infoEntra) {
                self::exigirCompatibilidadQr($infoSale, $infoEntra, $payload, $settings);
            } else {
                self::exigirCompatibilidad($reemplazo, $defectuosa, $payload, $settings);
            }

            $altaAutomatica = false;
            $estadoDesdeDefectuosa = $defectuosa?->estado;

            if (!$defectuosa) {
                if (!($settings['permitirAltaAutomaticaRetirada'] ?? true)) {
                    throw new \RuntimeException('La pieza defectuosa no esta registrada en el sistema', 404);
                }
                $defectuosa = EolPieza::create([
                    'pieza_qr'         => $piezaDefectuosaQr,
                    'tipo_pieza'       => $infoSale['tipo'] ?? self::normalizarTipo($payload['tipo_pieza'] ?? null) ?? $reemplazo->tipo_pieza,
                    // Del QR (SD / numero de parte / mano); si no, igual que la que entra.
                    'modelo'           => $infoSale['sd'] ?? $reemplazo->modelo ?? ($payload['modelo'] ?? null),
                    'codigo'           => $infoSale['codigo'] ?? $payload['codigo'] ?? null,
                    'lado'             => $infoSale['mano'] ?? $payload['lado'] ?? $reemplazo->lado,
                    'estado'           => 'DISPONIBLE', // transitorio: se pisa mas abajo a DEFECTUOSA
                    'linea'            => $reemplazo->linea,
                    'alta_por'         => $user?->id,
                    'alta_por_nombre'  => $user?->name,
                    'alta_at'          => now(),
                    'ultimo_evento_at' => now(),
                ]);
                $altaAutomatica = true;
                $estadoDesdeDefectuosa = null;
                self::registrarEventoPieza($defectuosa, 'ALTA', $user, [
                    'estado_desde'  => null,
                    'estado_hasta'  => 'DISPONIBLE',
                    'puesto_id'     => $puestoId,
                    'puesto_nombre' => $puestoNombre,
                    'observaciones' => 'Alta automatica al retirarse defectuosa en un cambio de pieza',
                ]);
            } else {
                // Whitelist de estados de origen validos para cambiar: la pieza tiene que
                // estar montada (EN_ASIENTO) o recien detectada como suelta (DISPONIBLE,
                // caso raro pero posible). Cualquier otro estado (ya DEFECTUOSA, EN_RETRABAJO,
                // PEND_VALIDACION, SCRAP, BAJA) es un doble cambio / dato inconsistente.
                if (!in_array($defectuosa->estado, ['EN_ASIENTO', 'DISPONIBLE'], true)) {
                    throw new \RuntimeException(
                        "La pieza defectuosa no esta en un estado valido para cambiar (estado actual: {$defectuosa->estado})",
                        409
                    );
                }
            }

            // Update condicional del reemplazo: ademas del lock, verifico filas afectadas
            // (defensa extra si algo cambio el estado justo antes del lock).
            $afectadas = DB::table('eol_piezas')
                ->where('id', $reemplazo->id)
                ->where('estado', 'DISPONIBLE')
                ->update([
                    'estado'            => 'EN_ASIENTO',
                    'buffer_id'         => null,
                    'buffer_nombre'     => null,
                    'ultimo_evento_at'  => now(),
                    'updated_at'        => now(),
                ]);
            if ($afectadas !== 1) {
                throw new \RuntimeException('La pieza de reemplazo dejo de estar disponible, reintenta', 409);
            }
            $reemplazo->refresh();

            // Update condicional de la defectuosa: mismo criterio que el reemplazo, con
            // whitelist de estados de origen (defiende contra un doble cambio concurrente).
            $afectadasDefectuosa = DB::table('eol_piezas')
                ->where('id', $defectuosa->id)
                ->whereIn('estado', ['EN_ASIENTO', 'DISPONIBLE'])
                ->update([
                    'estado'            => 'DEFECTUOSA',
                    'buffer_id'         => $bufferId,
                    'buffer_nombre'     => $bufferNombre,
                    'ciclos_cambio'     => DB::raw('ciclos_cambio + 1'),
                    'ultimo_evento_at'  => now(),
                    'updated_at'        => now(),
                ]);
            if ($afectadasDefectuosa !== 1) {
                throw new \RuntimeException('La pieza defectuosa dejo de estar en un estado valido para cambiar, reintenta', 409);
            }
            $defectuosa->refresh();

            $defectos = self::defectosDelCambio($payload, $qr);

            $door = self::crearDoorConIdempotencia([
                'qr'                        => $qr ?: null,
                'modelo'                    => $defectuosa->modelo,
                'secuencia'                 => $payload['secuencia'] ?? null,
                'linea'                     => $defectuosa->linea ?? $reemplazo->linea,
                'lado'                      => $defectuosa->lado,
                'tipo_pieza'                => $reemplazo->tipo_pieza,
                'pieza_retirada_id'         => $defectuosa->id,
                'pieza_retirada_qr'         => $defectuosa->pieza_qr,
                'pieza_entregada_id'        => $reemplazo->id,
                'pieza_entregada_qr'        => $reemplazo->pieza_qr,
                'buffer_id'                 => $bufferId,
                'buffer_nombre'             => $bufferNombre,
                'defectos_count'            => $defectos->count(),
                'puesto_id'                 => $puestoId,
                'puesto_nombre'             => $puestoNombre,
                'cambiado_por'              => $user?->id,
                'cambiado_por_nombre'       => $user?->name,
                'turno'                     => $user?->turno,
                'cambiado_at'               => now(),
                'alta_automatica_retirada'  => $altaAutomatica,
                'client_uuid'               => $clientUuid,
                'observaciones'             => $obs,
            ]);

            DB::table('eol_piezas')->where('id', $defectuosa->id)->update([
                'defecto_id_actual' => $defectos->first()?->id,
                'door_id_actual'    => $door->id,
            ]);
            $defectuosa->refresh();

            foreach ($defectos as $defecto) {
                $defecto->update([
                    'estado'              => 'CAMBIADO',
                    'door_id'             => $door->id,
                    'pieza_id'            => $defectuosa->id,
                    'pieza_qr'            => $defectuosa->pieza_qr,
                    'cambiado_por'        => $user?->id,
                    'cambiado_por_nombre' => $user?->name,
                    'cambiado_at'         => now(),
                ]);
                self::registrarEventoDefecto($defecto, 'CAMBIADO', $user, $puestoId, $puestoNombre, $obs);
            }

            self::registrarEventoPieza($reemplazo, 'ENTREGADA', $user, [
                'estado_desde'  => 'DISPONIBLE',
                'estado_hasta'  => 'EN_ASIENTO',
                'door_id'       => $door->id,
                'buffer_id'     => $bufferId,
                'puesto_id'     => $puestoId,
                'puesto_nombre' => $puestoNombre,
                'observaciones' => $obs,
            ]);
            self::registrarEventoPieza($defectuosa, 'RETIRADA_DEFECTUOSA', $user, [
                'estado_desde'  => $estadoDesdeDefectuosa,
                'estado_hasta'  => 'DEFECTUOSA',
                'door_id'       => $door->id,
                'defecto_id'    => $defectos->first()?->id,
                'buffer_id'     => $bufferId,
                'puesto_id'     => $puestoId,
                'puesto_nombre' => $puestoNombre,
                'observaciones' => $obs,
            ]);

            return [
                'door'                 => $door->fresh(),
                'piezaDefectuosa'      => $defectuosa->fresh(),
                'piezaReemplazo'       => $reemplazo->fresh(),
                'defectosActualizados' => $defectos->pluck('id')->all(),
                'altaAutomatica'       => $altaAutomatica,
                'idempotent'           => false,
            ];
            });
        } catch (DoorIdempotenteException $e) {
            // Carrera de client_uuid: otra transaccion concurrente ya creo el door
            // (EolDoor::create violo la unicidad). Esta transaccion se aborto entera
            // (rollback de los cambios de piezas que hizo este intento perdedor) y
            // devolvemos el door ganador, ya commiteado.
            $existente = EolDoor::findOrFail($e->doorId);
            return self::resultadoIdempotente($existente);
        }
    }

    /**
     * Flujo de la tablet de door trims en un solo paso: el operario marca el defecto
     * sobre la foto, elige la pieza del set (PANEL / ARM_REST / GARNISH), escanea la
     * que sale y la del buffer. Todo va en una transaccion: si el reemplazo no cumple
     * los requisitos, tampoco quedan guardadas las marcas y la tablet puede probar
     * con otra pieza sin dejar defectos sueltos.
     *
     * @param array $payload lo mismo que cambiar() + {circles: [...], linea?, tipo_pieza}
     */
    public static function marcarYCambiar(array $payload): array
    {
        $circles = $payload['circles'] ?? [];
        if (!is_array($circles) || empty($circles)) {
            throw new \RuntimeException('Marca al menos un defecto en la foto', 422);
        }
        $tipoPieza = self::normalizarTipo($payload['tipo_pieza'] ?? null);
        if (!in_array($tipoPieza, self::TIPOS_PIEZA, true)) {
            throw new \RuntimeException('Elegi que pieza se cambia: PANEL, ARM REST o GARNISH', 422);
        }
        $qr = trim((string) ($payload['qr'] ?? ''));
        if ($qr === '') {
            throw new \RuntimeException('Falta el QR del door trim', 422);
        }

        // Reintento con el mismo client_uuid: devuelve el cambio ya hecho sin volver
        // a crear defectos.
        $clientUuid = $payload['client_uuid'] ?? null;
        if ($clientUuid && ($existente = EolDoor::where('client_uuid', $clientUuid)->first())) {
            return self::resultadoIdempotente($existente);
        }

        $user = !empty($payload['user']) ? User::find($payload['user']) : null;

        try {
            return DB::transaction(function () use ($payload, $circles, $tipoPieza, $qr, $user) {
                $ids = self::crearDefectosDoor($payload, $circles, $tipoPieza, $qr, $user);

                $resultado = self::cambiar(array_merge($payload, [
                    'qr'          => $qr,
                    'tipo_pieza'  => $tipoPieza,
                    'defecto_ids' => $ids,
                ]));
                // cambiar() resolvio un reintento concurrente con el mismo client_uuid:
                // se descartan los defectos recien creados y se devuelve el cambio ganador.
                if ($resultado['idempotent']) {
                    throw new DoorIdempotenteException((int) $resultado['door']->id);
                }
                return $resultado;
            });
        } catch (DoorIdempotenteException $e) {
            return self::resultadoIdempotente(EolDoor::findOrFail($e->doorId));
        }
    }

    /**
     * Calidad marca el defecto y listo: queda PENDIENTE para produccion, que decide
     * si lo retrabaja ahi (retrabajar()) o cambia la pieza del buffer (cambiar() con
     * defecto_ids). Despues Calidad lo valida (validarDefecto()).
     *
     * @param array $payload {qr, tipo_pieza, circles, user, puesto_*, modelo?, lado?, ...}
     * @return array {defectos: ids}
     */
    public static function marcarRetrabajo(array $payload): array
    {
        $circles = $payload['circles'] ?? [];
        if (!is_array($circles) || empty($circles)) {
            throw new \RuntimeException('Marca al menos un defecto en la foto', 422);
        }
        $tipoPieza = self::normalizarTipo($payload['tipo_pieza'] ?? null);
        if (!in_array($tipoPieza, self::TIPOS_PIEZA, true)) {
            throw new \RuntimeException('Falta la pieza del door trim (PANEL, ARM REST o GARNISH)', 422);
        }
        $qr = trim((string) ($payload['qr'] ?? ''));
        if ($qr === '') {
            throw new \RuntimeException('Falta el QR del door trim', 422);
        }
        $user = !empty($payload['user']) ? User::find($payload['user']) : null;

        return DB::transaction(function () use ($payload, $circles, $tipoPieza, $qr, $user) {
            return ['defectos' => self::crearDefectosDoor($payload, $circles, $tipoPieza, $qr, $user)];
        });
    }

    /**
     * El retrabajador cierra un defecto mandado a retrabajo (sin cambio de pieza):
     * queda RETRABAJADO con su usuario y la pieza sigue en el door trim.
     */
    public static function retrabajar(int $defectoId, ?User $user, ?string $obs = null): EolDefecto
    {
        return DB::transaction(function () use ($defectoId, $user, $obs) {
            $defecto = EolDefecto::where('id', $defectoId)->lockForUpdate()->first();
            if (!$defecto || $defecto->flujo !== 'DOOR') {
                throw new \RuntimeException('Defecto no encontrado', 404);
            }
            if ($defecto->estado !== 'PENDIENTE') {
                throw new \RuntimeException("Ese defecto ya no esta para retrabajar (estado: {$defecto->estado})", 409);
            }
            $defecto->update([
                'estado'              => 'RETRABAJADO',
                'reparado_por'        => $user?->id,
                'reparado_por_nombre' => $user?->name,
                'reparado_at'         => now(),
            ]);
            self::registrarEventoDefecto($defecto, 'RETRABAJADO', $user, null, null, $obs ?: 'Retrabajado en linea, sin cambio de pieza');
            return $defecto->fresh();
        });
    }

    /**
     * Calidad valida el door trim cuando vuelve de produccion (retrabajado o con la
     * pieza cambiada). validar -> VALIDADO. rechazar -> vuelve a PENDIENTE para que
     * produccion lo resuelva de nuevo (suma una reapertura).
     */
    public static function validarDefecto(int $defectoId, ?User $user, string $accion, ?string $obs = null): EolDefecto
    {
        return DB::transaction(function () use ($defectoId, $user, $accion, $obs) {
            $defecto = EolDefecto::where('id', $defectoId)->lockForUpdate()->first();
            if (!$defecto || $defecto->flujo !== 'DOOR') {
                throw new \RuntimeException('Defecto no encontrado', 404);
            }
            if (!in_array($defecto->estado, ['RETRABAJADO', 'CAMBIADO'], true)) {
                throw new \RuntimeException("Ese defecto no esta para validar (estado: {$defecto->estado})", 409);
            }

            if ($accion === 'validar') {
                $defecto->update([
                    'estado'              => 'VALIDADO',
                    'validado_por'        => $user?->id,
                    'validado_por_nombre' => $user?->name,
                    'validado_at'         => now(),
                ]);
                self::registrarEventoDefecto($defecto, 'VALIDADO', $user, null, null, $obs);
                return $defecto->fresh();
            }
            if ($accion !== 'rechazar') {
                throw new \RuntimeException("Accion invalida: {$accion} (esperado validar o rechazar)", 422);
            }

            $defecto->update([
                'estado'              => 'PENDIENTE',
                'reaperturas'         => (int) ($defecto->reaperturas ?? 0) + 1,
                'reparado_por'        => null,
                'reparado_por_nombre' => null,
                'reparado_at'         => null,
            ]);
            self::registrarEventoDefecto($defecto, 'RECHAZADO', $user, null, null, $obs);
            return $defecto->fresh();
        });
    }

    /** Crea los defectos flujo DOOR (estado PENDIENTE) a partir de las marcas de la tablet. */
    private static function crearDefectosDoor(array $payload, array $circles, string $tipoPieza, string $qr, ?User $user): array
    {
        $ids = [];
        foreach ($circles as $c) {
            $falla = is_array($c['falla'] ?? null) ? $c['falla'] : [];
            $caract = strtoupper(trim((string) ($falla['caracteristica'] ?? $c['caracteristica'] ?? 'C')));
            $defecto = EolDefecto::create([
                'qr'                 => $qr,
                'modelo'             => $payload['modelo'] ?? null,
                'codigo'             => $payload['codigo'] ?? null,
                'sd'                 => $payload['modelo'] ?? null,
                'secuencia'          => $payload['secuencia'] ?? null,
                'linea'              => $payload['linea'] ?? null,
                'lado'               => $payload['lado'] ?? ($c['lado'] ?? null),
                // En door el "tipo" del defecto es la pieza (PANEL / ARM_REST / GARNISH):
                // la foto se ubica por vista_id.
                'tipo'               => $tipoPieza,
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
                'vista_id'           => isset($c['imageId']) ? (string) $c['imageId'] : null,
                'vista_nombre'       => $c['viewName'] ?? null,
                'estado'             => 'PENDIENTE',
                'marcado_por'        => $user?->id,
                // Sin login: el nombre que dejo el puesto en tblDoorMain.usuario.
                'marcado_por_nombre' => $user?->name ?? ($payload['usuario_nombre'] ?? null),
                'turno'              => $user?->turno,
                'marcado_at'         => now(),
                'puesto_id'          => $payload['puesto_id'] ?? null,
                'puesto_nombre'      => $payload['puesto_nombre'] ?? null,
                'flujo'              => 'DOOR',
                'caracteristica'     => in_array($caract, ['A', 'B', 'C'], true) ? $caract : 'C',
            ]);
            self::registrarEventoDefecto($defecto, 'ABIERTO', $user, null, null, $c['obs'] ?? null);
            $ids[] = $defecto->id;
        }
        return $ids;
    }

    /** Arma la respuesta de cambiar() para un door ya existente (idempotencia). */
    /**
     * Defectos que resuelve el cambio: los ids explicitos del payload (si vino la
     * clave, aunque sea vacia) o, por defecto, todos los PENDIENTE flujo=DOOR del QR.
     */
    private static function defectosDelCambio(array $payload, string $qr)
    {
        if (array_key_exists('defecto_ids', $payload) && is_array($payload['defecto_ids'])) {
            $defectoIds = array_values(array_map('intval', $payload['defecto_ids']));
            if (empty($defectoIds)) {
                throw new \RuntimeException('defecto_ids no puede ser un arreglo vacio', 422);
            }
            $defectos = EolDefecto::whereIn('id', $defectoIds)
                ->where('flujo', 'DOOR')
                ->where('estado', 'PENDIENTE')
                ->when($qr !== '', fn ($w) => $w->where('qr', $qr))
                ->get();
            if (count($defectoIds) !== $defectos->count()) {
                throw new \RuntimeException('Hay defectos que no corresponden a este asiento o ya no estan pendientes', 409);
            }
        } else {
            $defectos = EolDefecto::where('qr', $qr)
                ->where('flujo', 'DOOR')
                ->where('estado', 'PENDIENTE')
                ->get();
        }

        if ($defectos->isEmpty()) {
            throw new \RuntimeException('El cambio de pieza no tiene ningun defecto asociado', 422);
        }
        return $defectos;
    }

    /** Alta de una pieza en el maestro con lo que dice su QR (app de camaras). */
    public static function altaDesdeQr(string $piezaQr, array $info, ?User $user, $bufferId, $bufferNombre, string $obs): EolPieza
    {
        $pieza = EolPieza::create([
            'pieza_qr'         => $piezaQr,
            'tipo_pieza'       => $info['tipo'],
            'modelo'           => $info['sd'],
            'codigo'           => $info['codigo'] ?? null,
            'lado'             => $info['mano'] ?? null,
            'estado'           => 'DISPONIBLE',
            'buffer_id'        => $bufferId,
            'buffer_nombre'    => $bufferNombre,
            'disponible_desde' => $info['curado_hasta'] ?? null,
            'alta_por'         => $user?->id,
            'alta_por_nombre'  => $user?->name,
            'alta_at'          => now(),
            'ultimo_evento_at' => now(),
        ]);
        self::registrarEventoPieza($pieza, 'ALTA', $user, [
            'estado_desde'  => null,
            'estado_hasta'  => 'DISPONIBLE',
            'buffer_id'     => $bufferId,
            'observaciones' => $obs,
        ]);
        return $pieza;
    }

    /** "1 h 20 min" hasta esa fecha. */
    private static function faltan($hasta): string
    {
        $min = max(1, (int) ceil(now()->diffInMinutes(\Carbon\Carbon::parse($hasta), false)));
        return $min >= 60 ? intdiv($min, 60) . ' h ' . ($min % 60) . ' min' : "{$min} min";
    }

    /**
     * Requisitos del reemplazo comparando los dos QR de la app de camaras: mismo tipo
     * de pieza, mismo SD, misma posicion (FR/RR) y misma mano (LH/RH).
     */
    private static function exigirCompatibilidadQr(array $sale, array $entra, array $payload, array $settings): void
    {
        $tipoElegido = self::normalizarTipo($payload['tipo_pieza'] ?? null);
        if ($entra['tipo'] !== $sale['tipo']) {
            throw new \RuntimeException("La pieza de reemplazo es {$entra['tipo']} y la que sale es {$sale['tipo']}", 422);
        }
        if ($tipoElegido && $tipoElegido !== $entra['tipo']) {
            throw new \RuntimeException("La pieza de reemplazo es {$entra['tipo']} y se eligio cambiar {$tipoElegido}", 422);
        }
        $campos = [];
        if ($settings['exigirMismoModelo'] ?? true) {
            $campos['sd'] = 'modelo (SD)';
        }
        if ($settings['exigirMismoLado'] ?? true) {
            $campos['posicion'] = 'posicion';
            $campos['mano'] = 'mano';
        }
        foreach ($campos as $campo => $etiqueta) {
            if (($entra[$campo] ?? null) && ($sale[$campo] ?? null) && $entra[$campo] !== $sale[$campo]) {
                throw new \RuntimeException("La pieza de reemplazo es de {$etiqueta} {$entra[$campo]} y la que sale es {$sale[$campo]}", 422);
            }
        }
    }

    /**
     * Cambio de garnish. Su QR es solo el SD (todos los garnish de un modelo tienen
     * el mismo), asi que no se sigue la pieza en el maestro: se controla que la que
     * entra sea garnish del mismo SD y queda registrado "garnish <SD> cambiado".
     */
    private static function cambiarGarnish(array $payload, ?array $sale, ?array $entra, ?User $user): array
    {
        $qr       = trim((string) ($payload['qr'] ?? ''));
        $qrSale   = trim((string) ($payload['pieza_defectuosa_qr'] ?? ''));
        $qrEntra  = trim((string) ($payload['pieza_reemplazo_qr'] ?? ''));
        $clientUuid = $payload['client_uuid'] ?? null;

        if (($entra['tipo'] ?? null) !== 'GARNISH') {
            throw new \RuntimeException('La pieza de reemplazo no es un garnish', 422);
        }
        if ($sale && $sale['tipo'] !== 'GARNISH') {
            throw new \RuntimeException("La pieza de reemplazo es GARNISH y la que sale es {$sale['tipo']}", 422);
        }
        if ($sale && $sale['sd'] !== $entra['sd']) {
            throw new \RuntimeException("El garnish de reemplazo es {$entra['sd']} y el que sale es {$sale['sd']}", 422);
        }

        if ($clientUuid && ($existente = EolDoor::where('client_uuid', $clientUuid)->first())) {
            return self::resultadoIdempotente($existente);
        }

        try {
            return DB::transaction(function () use ($payload, $qr, $qrSale, $qrEntra, $entra, $user, $clientUuid) {
                $defectos = self::defectosDelCambio($payload, $qr);
                $puestoId = $payload['puesto_id'] ?? null;
                $puestoNombre = $payload['puesto_nombre'] ?? null;
                $obs = $payload['observaciones'] ?? null;

                $door = self::crearDoorConIdempotencia([
                    'qr'                  => $qr ?: null,
                    'modelo'              => $entra['sd'],
                    'secuencia'           => $payload['secuencia'] ?? null,
                    'lado'                => $payload['lado'] ?? null,
                    'tipo_pieza'          => 'GARNISH',
                    'pieza_retirada_qr'   => $qrSale,
                    'pieza_entregada_qr'  => $qrEntra,
                    'buffer_id'           => $payload['buffer_id'] ?? null,
                    'buffer_nombre'       => $payload['buffer_nombre'] ?? null,
                    'defectos_count'      => $defectos->count(),
                    'puesto_id'           => $puestoId,
                    'puesto_nombre'       => $puestoNombre,
                    'cambiado_por'        => $user?->id,
                    'cambiado_por_nombre' => $user?->name,
                    'turno'               => $user?->turno,
                    'cambiado_at'         => now(),
                    'alta_automatica_retirada' => false,
                    'client_uuid'         => $clientUuid,
                    'observaciones'       => trim("Garnish {$entra['sd']} cambiado por otro {$entra['sd']}. " . ($obs ?? '')),
                ]);

                foreach ($defectos as $defecto) {
                    $defecto->update([
                        'estado'              => 'CAMBIADO',
                        'door_id'             => $door->id,
                        'pieza_qr'            => $qrSale,
                        'cambiado_por'        => $user?->id,
                        'cambiado_por_nombre' => $user?->name,
                        'cambiado_at'         => now(),
                    ]);
                    self::registrarEventoDefecto($defecto, 'CAMBIADO', $user, $puestoId, $puestoNombre, $obs);
                }

                return [
                    'door'                 => $door->fresh(),
                    'piezaDefectuosa'      => null,
                    'piezaReemplazo'       => null,
                    'defectosActualizados' => $defectos->pluck('id')->all(),
                    'altaAutomatica'       => false,
                    'idempotent'           => false,
                ];
            });
        } catch (DoorIdempotenteException $e) {
            return self::resultadoIdempotente(EolDoor::findOrFail($e->doorId));
        }
    }

    private static function resultadoIdempotente(EolDoor $existente): array
    {
        return [
            'door'                 => $existente,
            'piezaDefectuosa'      => EolPieza::find($existente->pieza_retirada_id),
            'piezaReemplazo'       => EolPieza::find($existente->pieza_entregada_id),
            'defectosActualizados' => EolDefecto::where('door_id', $existente->id)->pluck('id')->all(),
            'altaAutomatica'       => (bool) $existente->alta_automatica_retirada,
            'idempotent'           => true,
        ];
    }

    /**
     * Crea el EolDoor; si la unicidad de client_uuid salta por una carrera (dos
     * requests concurrentes con el mismo client_uuid pasaron el pre-check antes de
     * que el primero commitee), lanza DoorIdempotenteException con el id del door
     * ganador para que cambiar() lo resuelva FUERA de la transaccion (rollback total
     * de este intento, sin duplicar consumo de piezas).
     */
    private static function crearDoorConIdempotencia(array $attrs): EolDoor
    {
        try {
            return EolDoor::create($attrs);
        } catch (\Illuminate\Database\QueryException $e) {
            $clientUuid = $attrs['client_uuid'] ?? null;
            if ($clientUuid) {
                $existente = EolDoor::where('client_uuid', $clientUuid)->first();
                if ($existente) {
                    throw new DoorIdempotenteException($existente->id);
                }
            }
            throw $e;
        }
    }

    // =====================================================================
    // CICLO DE RETRABAJO DE LA PIEZA (en el buffer)
    // =====================================================================

    /** El retrabajador toma una pieza DEFECTUOSA del buffer. */
    public static function tomar(string $qr, ?User $user, ?string $obs = null, array $puesto = []): EolPieza
    {
        return DB::transaction(function () use ($qr, $user, $obs, $puesto) {
            $pieza = self::piezaBloqueada($qr);

            if ($pieza->estado !== 'DEFECTUOSA') {
                throw new \RuntimeException("La pieza no esta DEFECTUOSA (estado actual: {$pieza->estado})", 409);
            }

            $pieza->update([
                'estado'            => 'EN_RETRABAJO',
                'tomado_por'        => $user?->id,
                'tomado_por_nombre' => $user?->name,
                'tomado_at'         => now(),
                'ultimo_evento_at'  => now(),
            ]);

            self::registrarEventoPieza($pieza, 'TOMADA_RETRABAJO', $user, [
                'estado_desde'  => 'DEFECTUOSA',
                'estado_hasta'  => 'EN_RETRABAJO',
                'puesto_id'     => $puesto['id'] ?? null,
                'puesto_nombre' => $puesto['nombre'] ?? null,
                'observaciones' => $obs,
            ]);

            return $pieza->fresh();
        });
    }

    /**
     * Reingresa la pieza ya retrabajada. resultado='OK' arranca el curado (o pasa a
     * validacion, segun settings.door.requiereValidacionCalidad); 'SCRAP' la da de baja.
     *
     * @return array {pieza, defecto}
     */
    public static function reingresar(
        string $qr,
        ?User $user,
        string $resultado,
        ?string $obs = null,
        ?int $minutosSecado = null,
        array $puesto = [],
        bool $forzar = false
    ): array {
        $resultado = strtoupper(trim($resultado));
        $settings = self::settings();

        return DB::transaction(function () use ($qr, $user, $resultado, $obs, $minutosSecado, $puesto, $settings, $forzar) {
            $pieza = self::piezaBloqueada($qr);

            if ($pieza->estado !== 'EN_RETRABAJO') {
                throw new \RuntimeException("La pieza no esta EN_RETRABAJO (estado actual: {$pieza->estado})", 409);
            }

            // Conjunto completo de defectos de ESTE door (puede haber mas de uno por
            // asiento), no solo el defecto_id_actual: todos viajan juntos en el ciclo.
            $defectos = $pieza->door_id_actual
                ? EolDefecto::where('door_id', $pieza->door_id_actual)->whereIn('estado', ['CAMBIADO', 'RETRABAJADO'])->get()
                : collect();

            if ($resultado === 'SCRAP') {
                $pieza->update([
                    'estado'            => 'SCRAP',
                    'ultimo_evento_at'  => now(),
                    'defecto_id_actual' => null,
                    'door_id_actual'    => null,
                    'tomado_por'        => null,
                    'tomado_por_nombre' => null,
                    'tomado_at'         => null,
                ]);
                self::registrarEventoPieza($pieza, 'SCRAP', $user, [
                    'estado_desde'  => 'EN_RETRABAJO',
                    'estado_hasta'  => 'SCRAP',
                    'defecto_id'    => $defectos->first()?->id,
                    'puesto_id'     => $puesto['id'] ?? null,
                    'puesto_nombre' => $puesto['nombre'] ?? null,
                    'observaciones' => $obs,
                ]);

                foreach ($defectos as $defecto) {
                    $defecto->update(['estado' => 'SCRAP']);
                    self::registrarEventoDefecto($defecto, 'SCRAP', $user, $puesto['id'] ?? null, $puesto['nombre'] ?? null, $obs);
                }

                return ['pieza' => $pieza->fresh(), 'defecto' => $defectos->first()?->fresh(), 'defectos' => $defectos->fresh()->values()];
            }

            if ($resultado !== 'OK') {
                throw new \RuntimeException("Resultado invalido: {$resultado} (esperado OK o SCRAP)", 422);
            }

            $maxCiclos = (int) ($settings['maxCiclosRetrabajo'] ?? 3);
            if (!$forzar && $maxCiclos > 0 && (int) $pieza->ciclos_retrabajo + 1 > $maxCiclos) {
                throw new \RuntimeException(
                    "La pieza ya alcanzo el maximo de ciclos de retrabajo ({$maxCiclos}), se sugiere darla de baja (SCRAP)",
                    409
                );
            }

            $requiereValidacion = (bool) ($settings['requiereValidacionCalidad'] ?? true);
            $estadoFinal = $requiereValidacion ? 'PEND_VALIDACION' : 'DISPONIBLE';
            $minutos = $minutosSecado ?? (int) ($settings['minutosSecado'] ?? 240);

            $pieza->update([
                'estado'            => $estadoFinal,
                'ciclos_retrabajo'  => (int) $pieza->ciclos_retrabajo + 1,
                'disponible_desde'  => $requiereValidacion ? null : now()->addMinutes($minutos),
                'ultimo_evento_at'  => now(),
            ]);

            self::registrarEventoPieza($pieza, 'RETRABAJADA', $user, [
                'estado_desde'  => 'EN_RETRABAJO',
                'estado_hasta'  => $estadoFinal,
                'defecto_id'    => $defectos->first()?->id,
                'puesto_id'     => $puesto['id'] ?? null,
                'puesto_nombre' => $puesto['nombre'] ?? null,
                'observaciones' => $obs,
            ]);
            self::registrarEventoPieza($pieza, $requiereValidacion ? 'PEND_VALIDACION' : 'REINGRESO_BUFFER', $user, [
                'estado_desde'  => 'EN_RETRABAJO',
                'estado_hasta'  => $estadoFinal,
                'defecto_id'    => $defectos->first()?->id,
                'puesto_id'     => $puesto['id'] ?? null,
                'puesto_nombre' => $puesto['nombre'] ?? null,
            ]);

            foreach ($defectos as $defecto) {
                $defecto->update([
                    'estado'              => 'RETRABAJADO',
                    'reparado_por'        => $user?->id,
                    'reparado_por_nombre' => $user?->name,
                    'reparado_at'         => now(),
                    'reparacion_obs'      => $obs,
                ]);
                self::registrarEventoDefecto($defecto, 'RETRABAJADO', $user, $puesto['id'] ?? null, $puesto['nombre'] ?? null, $obs);
            }

            return ['pieza' => $pieza->fresh(), 'defecto' => $defectos->first()?->fresh(), 'defectos' => $defectos->fresh()->values()];
        });
    }

    /**
     * CALIDAD valida (o rechaza) el reingreso de una pieza PEND_VALIDACION.
     *
     * @return array {pieza, defecto}
     */
    public static function validarPieza(string $qr, ?User $user, string $accion, ?string $obs = null, array $puesto = []): array
    {
        $accion = strtolower(trim($accion));
        $settings = self::settings();

        return DB::transaction(function () use ($qr, $user, $accion, $obs, $puesto, $settings) {
            $pieza = self::piezaBloqueada($qr);

            if ($pieza->estado !== 'PEND_VALIDACION') {
                throw new \RuntimeException("La pieza no esta PEND_VALIDACION (estado actual: {$pieza->estado})", 409);
            }

            // Conjunto completo de defectos de ESTE door, no solo defecto_id_actual.
            $defectos = $pieza->door_id_actual
                ? EolDefecto::where('door_id', $pieza->door_id_actual)->whereIn('estado', ['CAMBIADO', 'RETRABAJADO'])->get()
                : collect();

            if ($accion === 'rechazar') {
                $pieza->update(['estado' => 'DEFECTUOSA', 'ultimo_evento_at' => now()]);
                self::registrarEventoPieza($pieza, 'RECHAZADA', $user, [
                    'estado_desde'  => 'PEND_VALIDACION',
                    'estado_hasta'  => 'DEFECTUOSA',
                    'defecto_id'    => $defectos->first()?->id,
                    'puesto_id'     => $puesto['id'] ?? null,
                    'puesto_nombre' => $puesto['nombre'] ?? null,
                    'observaciones' => $obs,
                ]);

                // Mismo criterio que EolDefectoController::validar (rechazar): vuelve a
                // CAMBIADO (hay que retrabajarla de nuevo), suma una reapertura y limpia
                // la reparacion previa. Se aplica a TODOS los defectos de este door.
                foreach ($defectos as $defecto) {
                    $defecto->update([
                        'estado'              => 'CAMBIADO',
                        'reaperturas'         => (int) ($defecto->reaperturas ?? 0) + 1,
                        'reparado_por'        => null,
                        'reparado_por_nombre' => null,
                        'reparado_at'         => null,
                    ]);
                    self::registrarEventoDefecto($defecto, 'RECHAZADO', $user, $puesto['id'] ?? null, $puesto['nombre'] ?? null, $obs);
                }

                return ['pieza' => $pieza->fresh(), 'defecto' => $defectos->first()?->fresh(), 'defectos' => $defectos->fresh()->values()];
            }

            if ($accion !== 'validar') {
                throw new \RuntimeException("Accion invalida: {$accion} (esperado validar o rechazar)", 422);
            }

            $minutos = (int) ($settings['minutosSecado'] ?? 240);
            $pieza->update([
                'estado'            => 'DISPONIBLE',
                'disponible_desde'  => now()->addMinutes($minutos),
                'defecto_id_actual' => null,
                'door_id_actual'    => null,
                'ultimo_evento_at'  => now(),
            ]);
            self::registrarEventoPieza($pieza, 'VALIDADA', $user, [
                'estado_desde'  => 'PEND_VALIDACION',
                'estado_hasta'  => 'DISPONIBLE',
                'defecto_id'    => $defectos->first()?->id,
                'puesto_id'     => $puesto['id'] ?? null,
                'puesto_nombre' => $puesto['nombre'] ?? null,
                'observaciones' => $obs,
            ]);

            foreach ($defectos as $defecto) {
                $defecto->update([
                    'estado'              => 'VALIDADO',
                    'validado_por'        => $user?->id,
                    'validado_por_nombre' => $user?->name,
                    'validado_at'         => now(),
                ]);
                self::registrarEventoDefecto($defecto, 'VALIDADO', $user, $puesto['id'] ?? null, $puesto['nombre'] ?? null, $obs);
            }

            return ['pieza' => $pieza->fresh(), 'defecto' => $defectos->first()?->fresh(), 'defectos' => $defectos->fresh()->values()];
        });
    }

    /** Da de baja directa (SCRAP) una pieza DEFECTUOSA / EN_RETRABAJO / PEND_VALIDACION. */
    public static function scrapPieza(string $qr, ?User $user, ?string $obs = null, array $puesto = []): array
    {
        return DB::transaction(function () use ($qr, $user, $obs, $puesto) {
            $pieza = self::piezaBloqueada($qr);

            if (in_array($pieza->estado, ['SCRAP', 'BAJA', 'DISPONIBLE', 'EN_ASIENTO'], true)) {
                throw new \RuntimeException("No se puede pasar a SCRAP una pieza {$pieza->estado}", 409);
            }

            // Conjunto completo de defectos de ESTE door, no solo defecto_id_actual.
            $defectos = $pieza->door_id_actual
                ? EolDefecto::where('door_id', $pieza->door_id_actual)->whereIn('estado', ['CAMBIADO', 'RETRABAJADO'])->get()
                : collect();
            $estadoDesde = $pieza->estado;

            $pieza->update([
                'estado'            => 'SCRAP',
                'ultimo_evento_at'  => now(),
                // Libera el "slot" de retrabajo/validacion: la pieza queda dada de baja,
                // no debe seguir apuntando a un defecto/door/retrabajador activo.
                'defecto_id_actual' => null,
                'door_id_actual'    => null,
                'tomado_por'        => null,
                'tomado_por_nombre' => null,
                'tomado_at'         => null,
            ]);
            self::registrarEventoPieza($pieza, 'SCRAP', $user, [
                'estado_desde'  => $estadoDesde,
                'estado_hasta'  => 'SCRAP',
                'defecto_id'    => $defectos->first()?->id,
                'puesto_id'     => $puesto['id'] ?? null,
                'puesto_nombre' => $puesto['nombre'] ?? null,
                'observaciones' => $obs,
            ]);

            foreach ($defectos as $defecto) {
                $defecto->update(['estado' => 'SCRAP']);
                self::registrarEventoDefecto($defecto, 'SCRAP', $user, $puesto['id'] ?? null, $puesto['nombre'] ?? null, $obs);
            }

            return ['pieza' => $pieza->fresh(), 'defecto' => $defectos->first()?->fresh(), 'defectos' => $defectos->fresh()->values()];
        });
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private static function piezaBloqueada(string $qr): EolPieza
    {
        $pieza = EolPieza::where('pieza_qr', $qr)->lockForUpdate()->first();
        if (!$pieza) {
            throw new \RuntimeException('Pieza no encontrada', 404);
        }
        return $pieza;
    }

    /** Registra un evento de trazabilidad de la pieza (quien / cuando / que). */
    public static function registrarEventoPieza(EolPieza $pieza, string $evento, ?User $user, array $extra = []): void
    {
        EolPiezaEvento::create(array_merge([
            'pieza_id'      => $pieza->id,
            'pieza_qr'      => $pieza->pieza_qr,
            'evento'        => $evento,
            'defecto_id'    => null,
            'door_id'       => null,
            'estado_desde'  => null,
            'estado_hasta'  => $pieza->estado,
            'buffer_id'     => $pieza->buffer_id,
            'user_id'       => $user?->id,
            'user_nombre'   => $user?->name,
            'departamento'  => $user?->departamento,
            'turno'         => $user?->turno,
            'puesto_id'     => null,
            'puesto_nombre' => null,
            'observaciones' => null,
            'created_at'    => now(),
        ], $extra));
    }

    /** Registra un evento de trazabilidad del defecto (mismo patron que EolDefectoController). */
    private static function registrarEventoDefecto(
        EolDefecto $defecto,
        string $evento,
        ?User $user,
        $puestoId = null,
        $puestoNombre = null,
        ?string $obs = null
    ): void {
        EolDefectoEvento::create([
            'eol_defecto_id' => $defecto->id,
            'qr'             => $defecto->qr,
            'evento'         => $evento,
            'user_id'        => $user?->id,
            'user_nombre'    => $user?->name,
            'departamento'   => $user?->departamento,
            'turno'          => $user?->turno,
            'puesto_id'      => $puestoId ?? $defecto->puesto_id,
            'puesto_nombre'  => $puestoNombre ?? $defecto->puesto_nombre,
            'observaciones'  => $obs,
            'created_at'     => now(),
        ]);
    }
}

/**
 * Señal interna de DoorService::crearDoorConIdempotencia(): el EolDoor::create
 * violo la unicidad de client_uuid por una carrera concurrente. Nunca sale de
 * DoorService (se atrapa dentro de cambiar()).
 */
class DoorIdempotenteException extends \RuntimeException
{
    public function __construct(public readonly int $doorId)
    {
        parent::__construct('Cambio de pieza idempotente detectado por carrera de client_uuid');
    }
}
