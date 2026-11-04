<?php

namespace App\Services;

use App\Models\FactoryConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Integracion con la tabla del control final: produccion.historicocontrolcalidad.
 *
 * Flujo:
 *   - Al generar un defecto -> el asiento queda con un OK + Mensaje='DEFECTO PENDIENTE':
 *       · si NO existe un OK, se INSERTA uno nuestro con el Usuario del operario de
 *         calidad que marco el defecto en la tablet (no un literal 'EOL').
 *       · si YA existe un OK (el del control real), no se puede insertar otro por el
 *         indice unico (Secuencia,FechaKanban,PosicionMano,Estado), asi que se MARCA ese
 *         OK poniendole Mensaje='DEFECTO PENDIENTE' (se conserva su Usuario real).
 *   - Cuando el asiento se re-escanea en el ultimo puesto, el PLC ve el OK y genera el NG
 *     (etiqueta repetida) -> OK + NG = el andon interlock se pone rojo.
 *   - Cuando calidad valida el retrabajo -> se limpia el asiento y queda el rastro en la
 *     NG del PLC (ver confirmarRetrabajo).
 *
 * Nunca cambia el Estado de una fila existente: el indice unico solo admite un OK y un
 * NG por asiento, asi que pasar nuestro OK a NG chocaria contra la NG del PLC. Al OK real
 * tampoco lo borra. Apagado por defecto (plcInterlockEnabled). Para probar local:
 * interlockConnection='sqlsrv'.
 */
class InterlockService
{
    private const TABLA = 'produccion.historicocontrolcalidad';

    public const MENSAJE = 'DEFECTO PENDIENTE';

    /** Mensaje que queda en la NG del PLC cuando calidad valida el retrabajo. */
    private const MENSAJE_VALIDADO = 'DEFECTO RETRABAJADO OK';

    /** Solo para filas viejas: antes se insertaba con este literal en vez del operario. */
    private const USUARIO_EOL = 'EOL';

    public static function conexion(): string
    {
        try {
            $row = FactoryConfig::query()->where('key', 'active')->first();
            return $row->data['settings']['interlockConnection'] ?? 'sqlsrvsar';
        } catch (\Throwable $e) {
            return 'sqlsrvsar';
        }
    }

    public static function habilitado(): bool
    {
        try {
            $row = FactoryConfig::query()->where('key', 'active')->first();
            return (bool) ($row->data['settings']['plcInterlockEnabled'] ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Nombre que va a la columna Usuario de JITMS (varchar(50)). Sin operario logueado
     * cae al literal historico para no dejar la fila sin duenio.
     */
    private static function normalizarUsuario(?string $usuario): string
    {
        $u = trim((string) $usuario);
        return $u === '' ? self::USUARIO_EOL : mb_substr($u, 0, 50);
    }

    /**
     * Esta fila de JITMS, es inequivocamente la que insertamos nosotros?
     *
     * Es la condicion que habilita el UNICO borrado del servicio. Tienen que darse las
     * cuatro cosas a la vez: el asiento correcto, Estado OK, nuestro Mensaje (que ningun
     * otro sistema escribe) y el operario con el que la creamos. Si falla cualquiera,
     * la fila se trata como ajena.
     */
    private static function esFilaPropia($fila, string $qr, string $usuario): bool
    {
        return $fila !== null
            && (string) $fila->CodigoEscaneado === $qr
            && strtoupper((string) $fila->Estado) === 'OK'
            && (string) ($fila->Mensaje ?? '') === self::MENSAJE
            && (string) ($fila->Usuario ?? '') === $usuario;
    }

    /**
     * Deja registrado en EOL_TBAR el id exacto de la fila de JITMS que tocamos, para
     * poder revertir despues POR ID y no por filtros de texto.
     *
     * Es lo que garantiza que la limpieza no pueda alcanzar una fila de otro sistema:
     * si no quedo registrada aca, no se toca.
     */
    private static function registrarMarca(string $qr, $fila, string $accion, bool $yaRevertido = false): void
    {
        try {
            DB::connection('sqlsrv')->table('eol_interlock_marcas')->insert([
                'qr'               => $qr,
                'jitms_id'         => $fila->IdHistoricoControlCalidad,
                'accion'           => $accion,
                'estado_fila'      => $fila->Estado ?? null,
                // MARCA y NG pisan el Mensaje de una fila ajena -> hay que poder restaurarlo.
                'mensaje_original' => in_array($accion, ['MARCA', 'NG'], true) ? (string) ($fila->Mensaje ?? '') : null,
                'usuario_fila'     => $fila->Usuario ?? null,
                // La marca de la NG se escribe DURANTE la reversion, no queda pendiente:
                // nace cerrada para que no la levante un confirmarRetrabajo posterior.
                'revertido_at'     => $yaRevertido ? now() : null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('InterlockService::registrarMarca : ' . $e->getMessage());
        }
    }

    /** Llave del asiento (Secuencia, FechaKanban, SD, PosicionMano) desde interlock / historico / etiqueta. */
    public static function resolverClave(string $qr): ?array
    {
        try {
            $con = DB::connection(self::conexion());

            $il = $con->table('produccion.interlock')->where('Kanban', $qr)->orderByDesc('Id')->first();
            if ($il) {
                return ['Secuencia' => $il->Secuencia, 'FechaKanban' => $il->FechaKanban, 'SD' => $il->SD, 'PosicionMano' => trim('FR ' . ($il->Mano ?? ''))];
            }

            $hc = $con->table(self::TABLA)->where('CodigoEscaneado', $qr)->orderByDesc('IdHistoricoControlCalidad')->first();
            if ($hc) {
                return ['Secuencia' => $hc->Secuencia, 'FechaKanban' => $hc->FechaKanban, 'SD' => $hc->SD, 'PosicionMano' => $hc->PosicionMano];
            }

            $etq = \App\Models\EpEtiqueta::where('qr', $qr)->first();
            $fecha = self::fechaDesdeQr($qr);
            if ($etq && $fecha) {
                $lado = strtoupper($etq->lado ?? '');
                return [
                    'Secuencia'    => (int) ltrim((string) ($etq->secuencia ?? '0'), '0') ?: ($etq->secuencia ?? null),
                    'FechaKanban'  => $fecha,
                    'SD'           => strtoupper($etq->modelo ?? ''),
                    'PosicionMano' => in_array($lado, ['LH', 'RH'], true) ? "FR {$lado}" : 'FR',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('InterlockService::resolverClave : ' . $e->getMessage());
        }
        return null;
    }

    private static function fechaDesdeQr(string $qr): ?string
    {
        if (!preg_match('/^(\d{2})(\d{2})(\d{2})/', $qr, $m)) {
            return null;
        }
        return "20{$m[1]}-{$m[2]}-{$m[3]}";
    }

    /**
     * DEFECTO GENERADO: deja el asiento con un OK marcado Mensaje='DEFECTO PENDIENTE'.
     *   - Si NO existe un OK -> inserta el nuestro con Usuario = el operario de calidad
     *     logueado en la tablet, para que en JITMS se vea quien lo marco.
     *   - Si YA existe un OK (control real) -> lo MARCA poniendole el mensaje (no se puede
     *     insertar otro OK por el indice unico). Conserva el Usuario real.
     * Idempotente: si el OK ya esta marcado, no hace nada.
     *
     * @param string|null $usuario Nombre del operario de calidad. Sin usuario cae a 'EOL'.
     */
    public static function cargarPendiente(string $qr, ?string $usuario = null): bool
    {
        if (!self::habilitado()) {
            return false;
        }

        $usuario = self::normalizarUsuario($usuario);

        $clave = self::resolverClave($qr);
        if (!$clave) {
            Log::warning("InterlockService: no se pudo resolver la llave del QR $qr.");
            return false;
        }

        try {
            $con = DB::connection(self::conexion());

            // Si ya hay un OK para este asiento (real o nuestro), NO podemos insertar otro:
            // el indice unico (Secuencia, FechaKanban, PosicionMano, Estado) no permite dos
            // OK. En ese caso MARCAMOS ese OK con Mensaje='DEFECTO PENDIENTE' (conservando su
            // Usuario real). Al re-escanearse en el final, el PLC vera el OK y generara la NG
            // (etiqueta repetida) -> OK + NG = andon rojo.
            $okExistente = $con->table(self::TABLA)
                ->where('CodigoEscaneado', $qr)
                ->where('Estado', 'OK')
                ->first();
            if ($okExistente) {
                if (($okExistente->Mensaje ?? '') !== self::MENSAJE) {
                    // Fila AJENA: solo le pisamos el Mensaje. Guardamos el id y el mensaje
                    // original para poder restaurarlo tal cual al validar (antes se dejaba
                    // en '' y se perdia lo que hubiera).
                    self::registrarMarca($qr, $okExistente, 'MARCA');

                    $con->table(self::TABLA)
                        ->where('IdHistoricoControlCalidad', $okExistente->IdHistoricoControlCalidad)
                        ->update(['Mensaje' => self::MENSAJE]);
                    Log::info("InterlockService: marcado OK existente (id {$okExistente->IdHistoricoControlCalidad}) como DEFECTO PENDIENTE para $qr");
                } else {
                    Log::info("InterlockService: el OK de $qr ya estaba marcado DEFECTO PENDIENTE");
                }
                return true;
            }

            // No hay OK -> pre-escribimos el nuestro: cuando el asiento llegue al ultimo
            // puesto y se escanee, el PLC encontrara este OK y escribira la NG (repetida).
            $fila = [
                'Secuencia'       => $clave['Secuencia'],
                'FechaKanban'     => $clave['FechaKanban'],
                'FechaEscaneado'  => now(),
                'SD'              => $clave['SD'],
                'PosicionMano'    => $clave['PosicionMano'],
                'CodigoEscaneado' => $qr,
                'Estado'          => 'OK',
                'Mensaje'         => self::MENSAJE,
                'Usuario'         => $usuario,
            ];

            // insertGetId devuelve el IDENTITY de la fila que ACABAMOS de insertar
            // (SCOPE_IDENTITY, a prueba de triggers). Antes se re-buscaba por
            // CodigoEscaneado+Estado, que podia traer otra fila del mismo asiento.
            $nuevoId = $con->table(self::TABLA)->insertGetId($fila, 'IdHistoricoControlCalidad');

            // Antes de habilitar el borrado futuro, releemos la fila por ese id y
            // comprobamos que es la nuestra. Si no coincide, NO se registra la marca:
            // sin marca, confirmarRetrabajo no borra nada. Es preferible dejar el asiento
            // marcado a guardar un id que despues borre una fila ajena.
            $nuestra = $nuevoId
                ? $con->table(self::TABLA)->where('IdHistoricoControlCalidad', $nuevoId)->first()
                : null;

            if (!self::esFilaPropia($nuestra, $qr, $usuario)) {
                Log::error("InterlockService: no se pudo confirmar la identidad de la fila insertada para $qr (id " . var_export($nuevoId, true) . "). Queda sin marca: no se borrara automaticamente.");
                return true;
            }

            self::registrarMarca($qr, $nuestra, 'INSERT');

            Log::info("InterlockService: cargado DEFECTO PENDIENTE (OK) para $qr por $usuario (id {$nuestra->IdHistoricoControlCalidad})");
            return true;
        } catch (\Throwable $e) {
            Log::error('InterlockService::cargarPendiente : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * CALIDAD VALIDA el retrabajo: revierte SOLO lo que escribio este sistema.
     *
     *   - Fila OK que INSERTAMOS nosotros -> se borra POR ID (verificando ademas
     *     Mensaje='DEFECTO PENDIENTE' y el Usuario que quedo registrado al insertarla).
     *   - Fila OK ajena a la que solo le pisamos el Mensaje -> se RESTAURA el mensaje
     *     original POR ID. Nunca se borra.
     *   - NG del PLC -> NO se borra ni cambia de Estado; se le pisa el Mensaje con
     *     'DEFECTO RETRABAJADO OK - <operario>' para dejar el rastro del ciclo en JITMS.
     *
     * Por que el rastro va en la NG y no pasando nuestro OK a NG:
     *   El indice unico (Secuencia,FechaKanban,PosicionMano,Estado) admite UNA sola NG por
     *   asiento, y para cuando calidad valida esa NG ya la escribio el PLC (es lo que
     *   dispara el andon rojo). Un UPDATE Estado='NG' sobre nuestro OK chocaria contra
     *   ella y reventaria la validacion. Cambiando solo el Mensaje no se toca el indice.
     *
     * Por que no se borra la NG (cambio 2026-07-22):
     *   La NG no la escribe EOL, la genera el PLC del proveedor. En produccion hay
     *   ~13.000 NG que son RECHAZOS DE CALIDAD de operarios reales, y el mensaje
     *   'Asiento previamente escaneado' tampoco sirve para distinguirlas: ya existen
     *   filas asi escritas por operarios antes de que EOL estuviera instalado.
     *   Borrar por CodigoEscaneado destruia historial de calidad ajeno.
     *
     *   Ademas no hace falta: se verifico en el historico de produccion que el PLC
     *   escribe un OK nuevo aunque exista una NG previa (2.154 casos reales). O sea,
     *   con borrar nuestro OK alcanza para que el asiento saque etiqueta valida al
     *   re-escanearse.
     *
     * La trazabilidad queda en eol_defectos + eol_defecto_eventos.
     */
    public static function confirmarRetrabajo(string $qr, ?string $usuario = null): bool
    {
        if (!self::habilitado()) {
            return false;
        }

        $usuario = self::normalizarUsuario($usuario);

        try {
            $con = DB::connection(self::conexion());
            $afectadas = 0;

            $marcas = DB::connection('sqlsrv')->table('eol_interlock_marcas')
                ->where('qr', $qr)
                ->whereNull('revertido_at')
                ->whereIn('accion', ['INSERT', 'MARCA'])
                ->get();

            // Id de la fila nuestra: la NG del PLC que nos interesa es la que se genero
            // DESPUES de ella. Una NG anterior es un rechazo ajeno y no se toca.
            $idPropio = null;

            foreach ($marcas as $m) {
                if ($m->accion === 'INSERT') {
                    // EL UNICO DELETE DEL SERVICIO. Va por clave primaria y ademas repite
                    // las cuatro condiciones que definen "fila nuestra": mismo asiento,
                    // Estado OK, nuestro Mensaje y el operario que la creo. Si alguien
                    // cambio la fila en el medio, el WHERE no matchea y no se borra nada.
                    $afectadas += $con->table(self::TABLA)
                        ->where('IdHistoricoControlCalidad', $m->jitms_id)
                        ->where('CodigoEscaneado', $qr)
                        ->where('Estado', 'OK')
                        ->where('Mensaje', self::MENSAJE)
                        ->when($m->usuario_fila, fn ($q) => $q->where('Usuario', $m->usuario_fila))
                        ->delete();
                } elseif ($m->accion === 'MARCA') {
                    // Fila ajena: NO se borra, se le devuelve el mensaje que tenia. Solo si
                    // sigue teniendo el nuestro (si otro sistema ya la reescribio, se deja).
                    $afectadas += $con->table(self::TABLA)
                        ->where('IdHistoricoControlCalidad', $m->jitms_id)
                        ->where('CodigoEscaneado', $qr)
                        ->where('Mensaje', self::MENSAJE)
                        ->update(['Mensaje' => $m->mensaje_original ?? '']);
                }

                $idPropio = max((int) $idPropio, (int) $m->jitms_id);

                DB::connection('sqlsrv')->table('eol_interlock_marcas')
                    ->where('id', $m->id)->update(['revertido_at' => now()]);
            }

            // Rastro del ciclo cerrado en JITMS, sobre la NG que genero el PLC.
            if ($idPropio !== null) {
                self::marcarNgValidada($con, $qr, $idPropio, $usuario);
            }

            // Sin marca registrada NO se toca nada. Antes habia aca un DELETE por filtro
            // (CodigoEscaneado + Usuario='EOL' + Mensaje) para defectos abiertos previos a
            // la auditoria. Se saco: es el unico borrado que no iba por id, y contra la
            // tabla de produccion cualquier fila que casualmente coincida con el filtro se
            // perderia. Preferimos dejar el asiento marcado y que se limpie a mano.
            if ($marcas->isEmpty()) {
                Log::warning("InterlockService: $qr no tiene marcas registradas, NO se revirtio nada. Revisar a mano si quedo un OK 'DEFECTO PENDIENTE' colgado.");
            }

            Log::info("InterlockService: retrabajo validado por $usuario, $qr -> revertido interlock ($afectadas fila/s). NG conservada.");
            return $afectadas > 0;
        } catch (\Throwable $e) {
            Log::error('InterlockService::confirmarRetrabajo : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deja el rastro del ciclo cerrado en la NG que genero el PLC.
     *
     * No se puede pasar nuestro OK a NG: el indice unico admite una sola NG por asiento y
     * esa ya la ocupa el PLC. Asi que el "hubo defecto y se retrabajo OK" va en el Mensaje
     * de esa NG, que no toca el indice y no borra nada.
     *
     * Solo alcanza la NG POSTERIOR a nuestra fila (por id): una NG mas vieja es un rechazo
     * de calidad ajeno y no se toca. El mensaje original queda guardado en la auditoria
     * por si hay que restaurarlo.
     */
    private static function marcarNgValidada($con, string $qr, int $idPropio, string $usuario): void
    {
        try {
            $ng = $con->table(self::TABLA)
                ->where('CodigoEscaneado', $qr)
                ->where('Estado', 'NG')
                ->where('IdHistoricoControlCalidad', '>', $idPropio)
                ->orderByDesc('IdHistoricoControlCalidad')
                ->first();

            if (!$ng) {
                Log::info("InterlockService: $qr sin NG posterior del PLC, no hay donde dejar el rastro.");
                return;
            }

            $mensaje = mb_substr(self::MENSAJE_VALIDADO . ' - ' . $usuario, 0, 255);
            if ((string) ($ng->Mensaje ?? '') === $mensaje) {
                return; // idempotente: ya la marcamos en una validacion anterior
            }

            self::registrarMarca($qr, $ng, 'NG', true);

            $con->table(self::TABLA)
                ->where('IdHistoricoControlCalidad', $ng->IdHistoricoControlCalidad)
                ->update(['Mensaje' => $mensaje]);

            Log::info("InterlockService: NG {$ng->IdHistoricoControlCalidad} de $qr marcada '$mensaje'");
        } catch (\Throwable $e) {
            Log::error('InterlockService::marcarNgValidada : ' . $e->getMessage());
        }
    }

    /**
     * De una lista de QRs, devuelve los que en historicocontrolcalidad tienen
     * AL MENOS un OK Y al menos un NG (mismo CodigoEscaneado). Eso indica que el
     * asiento se escaneo en el ultimo puesto y el PLC genero la etiqueta repetida
     * (NG) porque ya existia el OK -> es el disparador del andon interlock.
     */
    public static function qrsConOkYNg(array $qrs): array
    {
        if (empty($qrs)) {
            return [];
        }
        try {
            $rows = DB::connection(self::conexion())
                ->table(self::TABLA)
                ->whereIn('CodigoEscaneado', $qrs)
                ->whereIn('Estado', ['OK', 'NG'])
                ->get(['CodigoEscaneado', 'Estado']);

            $porQr = [];
            foreach ($rows as $r) {
                $porQr[$r->CodigoEscaneado][strtoupper($r->Estado)] = true;
            }

            return array_values(array_keys(array_filter(
                $porQr,
                fn ($estados) => isset($estados['OK']) && isset($estados['NG'])
            )));
        } catch (\Throwable $e) {
            Log::warning('InterlockService::qrsConOkYNg : ' . $e->getMessage());
            return [];
        }
    }
}
