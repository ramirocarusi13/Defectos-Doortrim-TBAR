<?php
/**
 * PRUEBA LOCAL de humo del flujo door de piezas (DOOR/ARM_REST), end-to-end,
 * contra la DB local (EOL_TBAR en este entorno; EOL_DOOR en el deploy real).
 *
 *   1) alta de 2 piezas (defectuosa "ya montada" + reemplazo DISPONIBLE)
 *   2) se marca un defecto flujo=DOOR en el asiento (directo por modelo, sin HTTP)
 *   3) DoorService::cambiar()      -> reemplazo EN_ASIENTO, defectuosa DEFECTUOSA,
 *                                      defecto CAMBIADO
 *   4) DoorService::tomar()        -> defectuosa EN_RETRABAJO
 *   5) DoorService::reingresar()   -> OK -> PEND_VALIDACION (requiere validacion por
 *                                      default), defecto RETRABAJADO
 *   6) DoorService::validarPieza() -> validar -> DISPONIBLE con disponible_desde futuro
 *                                      (secado), defecto VALIDADO
 *   7) doble cambio con el mismo client_uuid no duplica (idempotencia)
 *   8) cambiar con la pieza de reemplazo NO disponible -> error, sin efectos
 *   9) eol_interlock_marcas NO crece en ningun momento (flujo DOOR no toca JITMS)
 *  10) cambio con 2 defectos: cierre completo de AMBOS via reingresar + validar
 *  11) re-cambio de una pieza ya DEFECTUOSA -> rechazado 409 (whitelist de estado de origen)
 *  12) defecto_ids de otro QR o flujo EOL -> rechazado 409, sin efectos
 *  13) rechazo en validacion: pieza vuelve a DEFECTUOSA, defecto a CAMBIADO, reaperturas++
 *  14) scrap directo (EN_RETRABAJO -> SCRAP), defecto SCRAP, slot de la pieza liberado
 *
 * Al final borra todos los datos que creo (por id).
 *
 *   php door-local-test.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EolDoor;
use App\Models\EolDefecto;
use App\Models\EolDefectoEvento;
use App\Models\EolPieza;
use App\Models\EolPiezaEvento;
use App\Models\User;
use App\Services\DoorService;
use Illuminate\Support\Facades\DB;

function ok($b) { return $b ? "\e[32mOK\e[0m" : "\e[31mFALLA\e[0m"; }

$fallos = 0;
function chequear(&$fallos, string $desc, bool $cond) {
    echo $cond ? "PASO $desc  [" . ok(true) . "]\n" : "PASO $desc  [" . ok(false) . "]\n";
    if (!$cond) $fallos++;
}

echo "=============================================================\n";
echo "  PRUEBA LOCAL FLUJO DOOR DE PIEZAS (DOOR/ARM_REST)\n";
echo "=============================================================\n";

$QR_ASIENTO   = 'TEST-DOOR-260827-SEAT-0001';
$QR_DEFECT    = 'TEST-DOOR-260827-PZA-DEF-0001';
$QR_REEMP     = 'TEST-DOOR-260827-PZA-REP-0001';
$QR_OCUPADA   = 'TEST-DOOR-260827-PZA-OCUP-0001';
$QR_DEFECT_2  = 'TEST-DOOR-260827-PZA-DEF-0002'; // no existe -> dispara la validacion de "no disponible" antes de llegar a crearla
$BUFFER_ID    = 'buffer-test-1';
$BUFFER_NOM   = 'Buffer Test 1';
$TIPO         = 'DOOR';

// -- QRs de los escenarios 10-14 (cada uno con su propio asiento/piezas) --
$QR_ASIENTO_A  = 'TEST-DOOR-260827-SEAT-A';   // 10) 2 defectos, cierre completo
$QR_DEFECT_A   = 'TEST-DOOR-260827-PZA-DEF-A';
$QR_REEMP_A    = 'TEST-DOOR-260827-PZA-REP-A';
$QR_REEMP_B    = 'TEST-DOOR-260827-PZA-REP-B'; // 11) reemplazo para el re-cambio rechazado

$QR_ASIENTO_C  = 'TEST-DOOR-260827-SEAT-C';    // 12) defecto_ids invalidos
$QR_ASIENTO_OTRO = 'TEST-DOOR-260827-SEAT-OTRO';
$QR_DEFECT_C   = 'TEST-DOOR-260827-PZA-DEF-C';
$QR_REEMP_C    = 'TEST-DOOR-260827-PZA-REP-C';

$QR_ASIENTO_D  = 'TEST-DOOR-260827-SEAT-D';    // 13) rechazo en validacion
$QR_DEFECT_D   = 'TEST-DOOR-260827-PZA-DEF-D';
$QR_REEMP_D    = 'TEST-DOOR-260827-PZA-REP-D';

$QR_ASIENTO_E  = 'TEST-DOOR-260827-SEAT-E';    // 14) scrap directo
$QR_DEFECT_E   = 'TEST-DOOR-260827-PZA-DEF-E';
$QR_REEMP_E    = 'TEST-DOOR-260827-PZA-REP-E';

$TODOS_LOS_QR_ASIENTO = [$QR_ASIENTO, $QR_ASIENTO_A, $QR_ASIENTO_C, $QR_ASIENTO_OTRO, $QR_ASIENTO_D, $QR_ASIENTO_E];

// Limpieza defensiva de corridas anteriores (por si el test se corto a mitad).
function limpiar($qrsPiezas) {
    $piezas = EolPieza::whereIn('pieza_qr', $qrsPiezas)->get();
    $piezaIds = $piezas->pluck('id')->all();
    if (!empty($piezaIds)) {
        EolPiezaEvento::whereIn('pieza_id', $piezaIds)->delete();
    }
    EolDoor::whereIn('pieza_retirada_qr', $qrsPiezas)->orWhereIn('pieza_entregada_qr', $qrsPiezas)->delete();
    $defectos = EolDefecto::whereIn('qr', $GLOBALS['TODOS_LOS_QR_ASIENTO'])->where('flujo', 'DOOR')->get();
    $defectosEol = EolDefecto::whereIn('qr', $GLOBALS['TODOS_LOS_QR_ASIENTO'])->where('flujo', 'EOL')->get();
    $defectos = $defectos->merge($defectosEol);
    if ($defectos->isNotEmpty()) {
        EolDefectoEvento::whereIn('eol_defecto_id', $defectos->pluck('id'))->delete();
        EolDefecto::whereIn('id', $defectos->pluck('id'))->delete();
    }
    EolPieza::whereIn('pieza_qr', $qrsPiezas)->delete();
}

$todosLosQr = [
    $QR_DEFECT, $QR_REEMP, $QR_OCUPADA, $QR_DEFECT_2,
    $QR_DEFECT_A, $QR_REEMP_A, $QR_REEMP_B,
    $QR_DEFECT_C, $QR_REEMP_C,
    $QR_DEFECT_D, $QR_REEMP_D,
    $QR_DEFECT_E, $QR_REEMP_E,
];
limpiar($todosLosQr);

$marcasInterlockAntes = DB::connection('sqlsrv')->table('eol_interlock_marcas')->count();

$creadoPiezaIds = [];
$creadoDefectoIds = [];
$creadoDoorIds = [];

try {
    $userCalidad = User::query()->first();       // marca el defecto
    $userRetrabajador = User::query()->first();   // toma / reingresa la pieza
    $userInspector = User::query()->first();      // valida

    // ------------------------------------------------------------------
    // 1) ALTA de las 2 piezas
    // ------------------------------------------------------------------
    echo "\n-- 1) alta de piezas --\n";

    $defectuosa = EolPieza::create([
        'pieza_qr'         => $QR_DEFECT,
        'tipo_pieza'       => $TIPO,
        'modelo'           => 'MODELO-TEST',
        'lado'             => 'LH',
        'estado'           => 'EN_ASIENTO', // ya estaba montada cuando se detecto el defecto
        'linea'            => 'EOL-TEST',
        'alta_por'         => $userCalidad?->id,
        'alta_por_nombre'  => $userCalidad?->name,
        'alta_at'          => now(),
        'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $defectuosa->id;

    $reemplazo = EolPieza::create([
        'pieza_qr'         => $QR_REEMP,
        'tipo_pieza'       => $TIPO,
        'modelo'           => 'MODELO-TEST',
        'lado'             => 'LH',
        'estado'           => 'DISPONIBLE',
        'buffer_id'        => $BUFFER_ID,
        'buffer_nombre'    => $BUFFER_NOM,
        'linea'            => 'EOL-TEST',
        'disponible_desde' => null, // ya curo, esta lista
        'alta_por'         => $userCalidad?->id,
        'alta_por_nombre'  => $userCalidad?->name,
        'alta_at'          => now(),
        'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $reemplazo->id;

    $ocupada = EolPieza::create([
        'pieza_qr'         => $QR_OCUPADA,
        'tipo_pieza'       => $TIPO,
        'modelo'           => 'MODELO-TEST',
        'estado'           => 'EN_ASIENTO', // NO disponible, para el test de error
        'linea'            => 'EOL-TEST',
        'alta_at'          => now(),
        'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $ocupada->id;

    chequear($fallos, 'alta · 3 piezas creadas', EolPieza::whereIn('id', $creadoPiezaIds)->count() === 3);

    // ------------------------------------------------------------------
    // 2) Marcar el defecto (directo por modelo, replica EolDefectoController::store)
    // ------------------------------------------------------------------
    echo "\n-- 2) marcar defecto flujo=DOOR (directo por modelo) --\n";

    $defecto = EolDefecto::create([
        'qr'                 => $QR_ASIENTO,
        'modelo'             => 'MODELO-TEST',
        'linea'              => 'EOL-TEST',
        'lado'               => 'LH',
        'tipo'               => $TIPO,
        'falla_codigo'       => 'ADH',
        'falla_nombre'       => 'Adhesivado defectuoso',
        'cantidad'           => 1,
        'estado'             => 'PENDIENTE',
        'flujo'              => 'DOOR',
        'marcado_por'        => $userCalidad?->id,
        'marcado_por_nombre' => $userCalidad?->name,
        'marcado_at'         => now(),
    ]);
    $creadoDefectoIds[] = $defecto->id;

    EolDefectoEvento::create([
        'eol_defecto_id' => $defecto->id,
        'qr'             => $defecto->qr,
        'evento'         => 'ABIERTO',
        'user_id'        => $userCalidad?->id,
        'user_nombre'    => $userCalidad?->name,
        'created_at'     => now(),
    ]);

    chequear($fallos, 'defecto · creado PENDIENTE flujo DOOR', $defecto->estado === 'PENDIENTE' && $defecto->flujo === 'DOOR');

    // ------------------------------------------------------------------
    // 3) CAMBIAR
    // ------------------------------------------------------------------
    echo "\n-- 3) cambiar --\n";

    $CLIENT_UUID = 'test-uuid-' . uniqid();

    $res1 = DoorService::cambiar([
        'qr'                  => $QR_ASIENTO,
        'pieza_defectuosa_qr' => $QR_DEFECT,
        'pieza_reemplazo_qr'  => $QR_REEMP,
        'buffer_id'           => $BUFFER_ID,
        'buffer_nombre'       => $BUFFER_NOM,
        'user'                => $userCalidad?->id,
        'puesto_id'           => 'puesto-1',
        'puesto_nombre'       => 'Puesto 1',
        'observaciones'       => 'Test door',
        'client_uuid'         => $CLIENT_UUID,
    ]);
    $creadoDoorIds[] = $res1['door']->id;

    $reemplazo->refresh();
    $defectuosa->refresh();
    $defecto->refresh();

    chequear($fallos, 'cambio · reemplazo EN_ASIENTO', $reemplazo->estado === 'EN_ASIENTO');
    chequear($fallos, 'cambio · defectuosa DEFECTUOSA, ciclos_cambio=1', $defectuosa->estado === 'DEFECTUOSA' && (int) $defectuosa->ciclos_cambio === 1);
    chequear($fallos, 'cambio · defectuosa apunta al defecto', (int) $defectuosa->defecto_id_actual === (int) $defecto->id);
    chequear($fallos, 'cambio · defecto CAMBIADO con pieza/door asociados', $defecto->estado === 'CAMBIADO' && (int) $defecto->pieza_id === (int) $defectuosa->id && (int) $defecto->door_id === (int) $res1['door']->id);
    chequear($fallos, 'cambio · no idempotente en la primera vez', $res1['idempotent'] === false);

    // ------------------------------------------------------------------
    // 4) TOMAR
    // ------------------------------------------------------------------
    echo "\n-- 4) tomar --\n";

    $piezaTomada = DoorService::tomar($QR_DEFECT, $userRetrabajador, null, ['id' => 'puesto-buffer', 'nombre' => 'Buffer']);
    chequear($fallos, 'tomar · EN_RETRABAJO', $piezaTomada->estado === 'EN_RETRABAJO');
    chequear($fallos, 'tomar · tomado_por seteado', $piezaTomada->tomado_por == $userRetrabajador?->id);

    // ------------------------------------------------------------------
    // 5) REINGRESAR (OK)
    // ------------------------------------------------------------------
    echo "\n-- 5) reingresar OK --\n";

    $resReingreso = DoorService::reingresar($QR_DEFECT, $userRetrabajador, 'OK', 'Retrabajado ok', null, ['id' => 'puesto-buffer', 'nombre' => 'Buffer']);
    $piezaReingresada = $resReingreso['pieza'];
    $defectoReingreso = $resReingreso['defecto'];

    chequear($fallos, 'reingresar · PEND_VALIDACION (requiere validacion por default)', $piezaReingresada->estado === 'PEND_VALIDACION');
    chequear($fallos, 'reingresar · ciclos_retrabajo=1', (int) $piezaReingresada->ciclos_retrabajo === 1);
    chequear($fallos, 'reingresar · defecto RETRABAJADO', $defectoReingreso && $defectoReingreso->estado === 'RETRABAJADO');

    // ------------------------------------------------------------------
    // 6) VALIDAR
    // ------------------------------------------------------------------
    echo "\n-- 6) validar --\n";

    $resValidar = DoorService::validarPieza($QR_DEFECT, $userInspector, 'validar', 'Validado ok', ['id' => 'puesto-1', 'nombre' => 'Puesto 1']);
    $piezaValidada = $resValidar['pieza'];
    $defectoValidado = $resValidar['defecto'];

    chequear($fallos, 'validar · pieza DISPONIBLE', $piezaValidada->estado === 'DISPONIBLE');
    chequear($fallos, 'validar · disponible_desde en el futuro (secado)', $piezaValidada->disponible_desde && $piezaValidada->disponible_desde->gt(now()));
    chequear($fallos, 'validar · defecto VALIDADO', $defectoValidado && $defectoValidado->estado === 'VALIDADO');

    // ------------------------------------------------------------------
    // Conteo de eventos
    // ------------------------------------------------------------------
    echo "\n-- conteo de eventos --\n";

    $eventosPieza = EolPiezaEvento::whereIn('pieza_id', [$defectuosa->id, $reemplazo->id])->count();
    $eventosDefecto = EolDefectoEvento::where('eol_defecto_id', $defecto->id)->count();

    chequear($fallos, "eventos pieza · >=5 (hay $eventosPieza)", $eventosPieza >= 5);
    chequear($fallos, "eventos defecto · >=3 (hay $eventosDefecto)", $eventosDefecto >= 3);

    // ------------------------------------------------------------------
    // 7) Doble cambio con el mismo client_uuid -> idempotente, no duplica
    // ------------------------------------------------------------------
    echo "\n-- 7) doble cambio, mismo client_uuid --\n";

    $doorsAntes = EolDoor::where('client_uuid', $CLIENT_UUID)->count();
    $res2 = DoorService::cambiar([
        'qr'                  => $QR_ASIENTO,
        'pieza_defectuosa_qr' => $QR_DEFECT,
        'pieza_reemplazo_qr'  => $QR_REEMP,
        'buffer_id'           => $BUFFER_ID,
        'buffer_nombre'       => $BUFFER_NOM,
        'user'                => $userCalidad?->id,
        'client_uuid'         => $CLIENT_UUID,
    ]);
    $doorsDespues = EolDoor::where('client_uuid', $CLIENT_UUID)->count();

    chequear($fallos, 'idempotencia · marca idempotent=true', $res2['idempotent'] === true);
    chequear($fallos, 'idempotencia · mismo id de door devuelto', (int) $res2['door']->id === (int) $res1['door']->id);
    chequear($fallos, 'idempotencia · no duplico la fila en eol_doors', $doorsAntes === $doorsDespues);

    // ------------------------------------------------------------------
    // 8) Cambio con pieza de reemplazo NO disponible -> error, sin efectos
    // ------------------------------------------------------------------
    echo "\n-- 8) cambio con pieza de reemplazo no disponible --\n";

    $doorsGlobalAntes = EolDoor::count();
    $lanzoError = false;
    $mensajeError = null;
    try {
        DoorService::cambiar([
            'qr'                  => $QR_ASIENTO,
            'pieza_defectuosa_qr' => $QR_DEFECT_2,
            'pieza_reemplazo_qr'  => $QR_OCUPADA, // EN_ASIENTO, no DISPONIBLE
            'buffer_id'           => $BUFFER_ID,
            'buffer_nombre'       => $BUFFER_NOM,
            'user'                => $userCalidad?->id,
        ]);
    } catch (\RuntimeException $e) {
        $lanzoError = true;
        $mensajeError = $e->getMessage();
    }
    $doorsGlobalDespues = EolDoor::count();
    $piezaDefect2Existe = EolPieza::where('pieza_qr', $QR_DEFECT_2)->exists();

    chequear($fallos, 'error · se rechazo (pieza no disponible)', $lanzoError);
    echo "   -> mensaje: " . ($mensajeError ?? '(ninguno)') . "\n";
    chequear($fallos, 'error · no creo cambios de mas', $doorsGlobalAntes === $doorsGlobalDespues);
    chequear($fallos, 'error · no genero alta automatica de la defectuosa (fallo antes)', !$piezaDefect2Existe);

    // ------------------------------------------------------------------
    // 9) eol_interlock_marcas no crecio en todo el test (flujo DOOR no toca JITMS)
    // ------------------------------------------------------------------
    echo "\n-- 9) interlock intacto --\n";

    $marcasInterlockDespues = DB::connection('sqlsrv')->table('eol_interlock_marcas')->count();
    chequear($fallos, "interlock · eol_interlock_marcas sin cambios ($marcasInterlockAntes -> $marcasInterlockDespues)", $marcasInterlockAntes === $marcasInterlockDespues);

    // ------------------------------------------------------------------
    // 10) Cambio con 2 defectos + 11) re-cambio de pieza ya DEFECTUOSA (rechazado)
    // ------------------------------------------------------------------
    echo "\n-- 10) cambio con 2 defectos, cierre completo --\n";

    $defectuosaA = EolPieza::create([
        'pieza_qr' => $QR_DEFECT_A, 'tipo_pieza' => $TIPO, 'modelo' => 'MODELO-TEST', 'lado' => 'LH',
        'estado' => 'EN_ASIENTO', 'linea' => 'EOL-TEST', 'alta_at' => now(), 'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $defectuosaA->id;

    $reemplazoA = EolPieza::create([
        'pieza_qr' => $QR_REEMP_A, 'tipo_pieza' => $TIPO, 'modelo' => 'MODELO-TEST', 'lado' => 'LH',
        'estado' => 'DISPONIBLE', 'buffer_id' => $BUFFER_ID, 'buffer_nombre' => $BUFFER_NOM,
        'linea' => 'EOL-TEST', 'alta_at' => now(), 'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $reemplazoA->id;

    $defectoA1 = EolDefecto::create([
        'qr' => $QR_ASIENTO_A, 'modelo' => 'MODELO-TEST', 'linea' => 'EOL-TEST', 'lado' => 'LH', 'tipo' => $TIPO,
        'falla_codigo' => 'ADH', 'falla_nombre' => 'Adhesivado defectuoso', 'cantidad' => 1,
        'estado' => 'PENDIENTE', 'flujo' => 'DOOR',
        'marcado_por' => $userCalidad?->id, 'marcado_por_nombre' => $userCalidad?->name, 'marcado_at' => now(),
    ]);
    $creadoDefectoIds[] = $defectoA1->id;

    $defectoA2 = EolDefecto::create([
        'qr' => $QR_ASIENTO_A, 'modelo' => 'MODELO-TEST', 'linea' => 'EOL-TEST', 'lado' => 'LH', 'tipo' => $TIPO,
        'falla_codigo' => 'RAY', 'falla_nombre' => 'Rayado', 'cantidad' => 1,
        'estado' => 'PENDIENTE', 'flujo' => 'DOOR',
        'marcado_por' => $userCalidad?->id, 'marcado_por_nombre' => $userCalidad?->name, 'marcado_at' => now(),
    ]);
    $creadoDefectoIds[] = $defectoA2->id;

    $resA = DoorService::cambiar([
        'qr' => $QR_ASIENTO_A,
        'pieza_defectuosa_qr' => $QR_DEFECT_A,
        'pieza_reemplazo_qr'  => $QR_REEMP_A,
        'buffer_id' => $BUFFER_ID, 'buffer_nombre' => $BUFFER_NOM,
        'user' => $userCalidad?->id,
    ]);
    $creadoDoorIds[] = $resA['door']->id;

    chequear($fallos, '10 · cambio registra los 2 defectos (defectos_count / defectosActualizados)',
        (int) $resA['door']->defectos_count === 2 && count($resA['defectosActualizados']) === 2);
    chequear($fallos, '10 · ambos defectos CAMBIADO',
        EolDefecto::whereIn('id', [$defectoA1->id, $defectoA2->id])->where('estado', 'CAMBIADO')->count() === 2);

    echo "\n-- 11) re-cambio de una pieza ya DEFECTUOSA (rechazado 409) --\n";

    $reemplazoB = EolPieza::create([
        'pieza_qr' => $QR_REEMP_B, 'tipo_pieza' => $TIPO, 'modelo' => 'MODELO-TEST', 'lado' => 'LH',
        'estado' => 'DISPONIBLE', 'buffer_id' => $BUFFER_ID, 'buffer_nombre' => $BUFFER_NOM,
        'linea' => 'EOL-TEST', 'alta_at' => now(), 'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $reemplazoB->id;

    $lanzoError11 = false; $mensajeError11 = null; $codigoError11 = null;
    try {
        DoorService::cambiar([
            'qr'                  => $QR_ASIENTO_A,
            'pieza_defectuosa_qr' => $QR_DEFECT_A, // ya esta DEFECTUOSA (recien cambiada en el paso 10)
            'pieza_reemplazo_qr'  => $QR_REEMP_B,
            'buffer_id' => $BUFFER_ID, 'buffer_nombre' => $BUFFER_NOM,
            'user' => $userCalidad?->id,
        ]);
    } catch (\RuntimeException $e) {
        $lanzoError11 = true;
        $mensajeError11 = $e->getMessage();
        $codigoError11 = $e->getCode();
    }
    $reemplazoB->refresh();

    chequear($fallos, '11 · se rechazo el re-cambio (409, whitelist de estado de origen)', $lanzoError11 && $codigoError11 === 409);
    echo "   -> mensaje: " . ($mensajeError11 ?? '(ninguno)') . "\n";
    chequear($fallos, '11 · el reemplazo B no se toco (sigue DISPONIBLE)', $reemplazoB->estado === 'DISPONIBLE');

    // Cierro el ciclo del escenario 10 (con la pieza intacta tras el intento rechazado).
    DoorService::tomar($QR_DEFECT_A, $userRetrabajador, null, ['id' => 'puesto-buffer', 'nombre' => 'Buffer']);
    $resReingresoA = DoorService::reingresar($QR_DEFECT_A, $userRetrabajador, 'OK', null, null, ['id' => 'puesto-buffer', 'nombre' => 'Buffer']);

    chequear($fallos, '10 · reingresar resuelve los 2 defectos del cambio (no solo defecto_id_actual)', $resReingresoA['defectos']->count() === 2);
    chequear($fallos, '10 · ambos defectos RETRABAJADO tras reingresar',
        EolDefecto::whereIn('id', [$defectoA1->id, $defectoA2->id])->where('estado', 'RETRABAJADO')->count() === 2);

    $resValidarA = DoorService::validarPieza($QR_DEFECT_A, $userInspector, 'validar', 'Validado ok', ['id' => 'puesto-1', 'nombre' => 'Puesto 1']);

    chequear($fallos, '10 · pieza DISPONIBLE tras validar', $resValidarA['pieza']->estado === 'DISPONIBLE');
    chequear($fallos, '10 · ambos defectos VALIDADO',
        EolDefecto::whereIn('id', [$defectoA1->id, $defectoA2->id])->where('estado', 'VALIDADO')->count() === 2);

    // ------------------------------------------------------------------
    // 12) defecto_ids de otro QR o flujo EOL -> rechazado, sin efectos
    // ------------------------------------------------------------------
    echo "\n-- 12) defecto_ids de otro QR o flujo EOL (rechazado) --\n";

    $defectuosaC = EolPieza::create([
        'pieza_qr' => $QR_DEFECT_C, 'tipo_pieza' => $TIPO, 'modelo' => 'MODELO-TEST', 'lado' => 'LH',
        'estado' => 'EN_ASIENTO', 'linea' => 'EOL-TEST', 'alta_at' => now(), 'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $defectuosaC->id;

    $reemplazoC = EolPieza::create([
        'pieza_qr' => $QR_REEMP_C, 'tipo_pieza' => $TIPO, 'modelo' => 'MODELO-TEST', 'lado' => 'LH',
        'estado' => 'DISPONIBLE', 'buffer_id' => $BUFFER_ID, 'buffer_nombre' => $BUFFER_NOM,
        'linea' => 'EOL-TEST', 'alta_at' => now(), 'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $reemplazoC->id;

    $defectoOtroQr = EolDefecto::create([
        'qr' => $QR_ASIENTO_OTRO, 'modelo' => 'MODELO-TEST', 'linea' => 'EOL-TEST', 'lado' => 'LH', 'tipo' => $TIPO,
        'falla_codigo' => 'ADH', 'falla_nombre' => 'Adhesivado defectuoso', 'cantidad' => 1,
        'estado' => 'PENDIENTE', 'flujo' => 'DOOR',
        'marcado_por' => $userCalidad?->id, 'marcado_por_nombre' => $userCalidad?->name, 'marcado_at' => now(),
    ]);
    $creadoDefectoIds[] = $defectoOtroQr->id;

    $defectoFlujoEol = EolDefecto::create([
        'qr' => $QR_ASIENTO_C, 'modelo' => 'MODELO-TEST', 'linea' => 'EOL-TEST', 'lado' => 'LH', 'tipo' => $TIPO,
        'falla_codigo' => 'ADH', 'falla_nombre' => 'Adhesivado defectuoso', 'cantidad' => 1,
        'estado' => 'PENDIENTE', 'flujo' => 'EOL', // <- flujo equivocado para la linea de door
        'marcado_por' => $userCalidad?->id, 'marcado_por_nombre' => $userCalidad?->name, 'marcado_at' => now(),
    ]);
    $creadoDefectoIds[] = $defectoFlujoEol->id;

    $lanzoError12a = false; $codigo12a = null;
    try {
        DoorService::cambiar([
            'qr' => $QR_ASIENTO_C,
            'pieza_defectuosa_qr' => $QR_DEFECT_C,
            'pieza_reemplazo_qr'  => $QR_REEMP_C,
            'defecto_ids'         => [$defectoOtroQr->id], // pertenece a QR_ASIENTO_OTRO, no a QR_ASIENTO_C
            'buffer_id' => $BUFFER_ID, 'buffer_nombre' => $BUFFER_NOM,
            'user' => $userCalidad?->id,
        ]);
    } catch (\RuntimeException $e) {
        $lanzoError12a = true;
        $codigo12a = $e->getCode();
        echo "   -> mensaje (defecto de otro QR): " . $e->getMessage() . "\n";
    }
    chequear($fallos, '12a · defecto_ids de otro QR rechazado (409)', $lanzoError12a && $codigo12a === 409);

    $lanzoError12b = false; $codigo12b = null;
    try {
        DoorService::cambiar([
            'qr' => $QR_ASIENTO_C,
            'pieza_defectuosa_qr' => $QR_DEFECT_C,
            'pieza_reemplazo_qr'  => $QR_REEMP_C,
            'defecto_ids'         => [$defectoFlujoEol->id], // mismo QR pero flujo EOL, no DOOR
            'buffer_id' => $BUFFER_ID, 'buffer_nombre' => $BUFFER_NOM,
            'user' => $userCalidad?->id,
        ]);
    } catch (\RuntimeException $e) {
        $lanzoError12b = true;
        $codigo12b = $e->getCode();
        echo "   -> mensaje (defecto flujo EOL): " . $e->getMessage() . "\n";
    }
    chequear($fallos, '12b · defecto_ids flujo EOL rechazado (409)', $lanzoError12b && $codigo12b === 409);

    $defectuosaC->refresh();
    $reemplazoC->refresh();
    chequear($fallos, '12 · sin efectos: defectuosa C sigue EN_ASIENTO', $defectuosaC->estado === 'EN_ASIENTO');
    chequear($fallos, '12 · sin efectos: reemplazo C sigue DISPONIBLE', $reemplazoC->estado === 'DISPONIBLE');
    chequear($fallos, '12 · sin efectos: defecto de otro QR sigue PENDIENTE', $defectoOtroQr->fresh()->estado === 'PENDIENTE');

    // ------------------------------------------------------------------
    // 13) Rechazo en validacion: pieza vuelve a DEFECTUOSA, defecto a CAMBIADO, reaperturas++
    // ------------------------------------------------------------------
    echo "\n-- 13) rechazo en validacion --\n";

    $defectuosaD = EolPieza::create([
        'pieza_qr' => $QR_DEFECT_D, 'tipo_pieza' => $TIPO, 'modelo' => 'MODELO-TEST', 'lado' => 'LH',
        'estado' => 'EN_ASIENTO', 'linea' => 'EOL-TEST', 'alta_at' => now(), 'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $defectuosaD->id;

    $reemplazoD = EolPieza::create([
        'pieza_qr' => $QR_REEMP_D, 'tipo_pieza' => $TIPO, 'modelo' => 'MODELO-TEST', 'lado' => 'LH',
        'estado' => 'DISPONIBLE', 'buffer_id' => $BUFFER_ID, 'buffer_nombre' => $BUFFER_NOM,
        'linea' => 'EOL-TEST', 'alta_at' => now(), 'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $reemplazoD->id;

    $defectoD = EolDefecto::create([
        'qr' => $QR_ASIENTO_D, 'modelo' => 'MODELO-TEST', 'linea' => 'EOL-TEST', 'lado' => 'LH', 'tipo' => $TIPO,
        'falla_codigo' => 'ADH', 'falla_nombre' => 'Adhesivado defectuoso', 'cantidad' => 1,
        'estado' => 'PENDIENTE', 'flujo' => 'DOOR',
        'marcado_por' => $userCalidad?->id, 'marcado_por_nombre' => $userCalidad?->name, 'marcado_at' => now(),
    ]);
    $creadoDefectoIds[] = $defectoD->id;

    $resD = DoorService::cambiar([
        'qr' => $QR_ASIENTO_D,
        'pieza_defectuosa_qr' => $QR_DEFECT_D,
        'pieza_reemplazo_qr'  => $QR_REEMP_D,
        'buffer_id' => $BUFFER_ID, 'buffer_nombre' => $BUFFER_NOM,
        'user' => $userCalidad?->id,
    ]);
    $creadoDoorIds[] = $resD['door']->id;

    DoorService::tomar($QR_DEFECT_D, $userRetrabajador, null, []);
    DoorService::reingresar($QR_DEFECT_D, $userRetrabajador, 'OK', null, null, []);

    $resRechazoD = DoorService::validarPieza($QR_DEFECT_D, $userInspector, 'rechazar', 'No quedo bien', []);
    $defectoD->refresh();

    chequear($fallos, '13 · pieza vuelve a DEFECTUOSA', $resRechazoD['pieza']->estado === 'DEFECTUOSA');
    chequear($fallos, '13 · defecto vuelve a CAMBIADO', $defectoD->estado === 'CAMBIADO');
    chequear($fallos, '13 · reaperturas = 1', (int) $defectoD->reaperturas === 1);
    chequear($fallos, '13 · reparado_por/reparado_at limpiados', $defectoD->reparado_por === null && $defectoD->reparado_at === null);

    // ------------------------------------------------------------------
    // 14) Scrap directo (EN_RETRABAJO -> SCRAP)
    // ------------------------------------------------------------------
    echo "\n-- 14) scrap directo --\n";

    $defectuosaE = EolPieza::create([
        'pieza_qr' => $QR_DEFECT_E, 'tipo_pieza' => $TIPO, 'modelo' => 'MODELO-TEST', 'lado' => 'LH',
        'estado' => 'EN_ASIENTO', 'linea' => 'EOL-TEST', 'alta_at' => now(), 'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $defectuosaE->id;

    $reemplazoE = EolPieza::create([
        'pieza_qr' => $QR_REEMP_E, 'tipo_pieza' => $TIPO, 'modelo' => 'MODELO-TEST', 'lado' => 'LH',
        'estado' => 'DISPONIBLE', 'buffer_id' => $BUFFER_ID, 'buffer_nombre' => $BUFFER_NOM,
        'linea' => 'EOL-TEST', 'alta_at' => now(), 'ultimo_evento_at' => now(),
    ]);
    $creadoPiezaIds[] = $reemplazoE->id;

    $defectoE = EolDefecto::create([
        'qr' => $QR_ASIENTO_E, 'modelo' => 'MODELO-TEST', 'linea' => 'EOL-TEST', 'lado' => 'LH', 'tipo' => $TIPO,
        'falla_codigo' => 'ADH', 'falla_nombre' => 'Adhesivado defectuoso', 'cantidad' => 1,
        'estado' => 'PENDIENTE', 'flujo' => 'DOOR',
        'marcado_por' => $userCalidad?->id, 'marcado_por_nombre' => $userCalidad?->name, 'marcado_at' => now(),
    ]);
    $creadoDefectoIds[] = $defectoE->id;

    $resE = DoorService::cambiar([
        'qr' => $QR_ASIENTO_E,
        'pieza_defectuosa_qr' => $QR_DEFECT_E,
        'pieza_reemplazo_qr'  => $QR_REEMP_E,
        'buffer_id' => $BUFFER_ID, 'buffer_nombre' => $BUFFER_NOM,
        'user' => $userCalidad?->id,
    ]);
    $creadoDoorIds[] = $resE['door']->id;

    DoorService::tomar($QR_DEFECT_E, $userRetrabajador, null, []);
    $resScrapE = DoorService::scrapPieza($QR_DEFECT_E, $userInspector, 'Rota, no se puede retrabajar', []);
    $piezaEFresh = EolPieza::find($defectuosaE->id);

    chequear($fallos, '14 · pieza SCRAP', $resScrapE['pieza']->estado === 'SCRAP');
    chequear($fallos, '14 · defecto SCRAP', $resScrapE['defecto'] && $resScrapE['defecto']->estado === 'SCRAP');
    chequear($fallos, '14 · slot liberado (defecto_id_actual/door_id_actual/tomado_* en null)',
        $piezaEFresh->defecto_id_actual === null && $piezaEFresh->door_id_actual === null && $piezaEFresh->tomado_por === null);
} finally {
    // ------------------------------------------------------------------
    // LIMPIEZA: borro todo lo que crea este test, por id.
    // ------------------------------------------------------------------
    echo "\n-- limpieza --\n";

    if (!empty($creadoDoorIds)) {
        EolDoor::whereIn('id', $creadoDoorIds)->delete();
    }
    if (!empty($creadoDefectoIds)) {
        EolDefectoEvento::whereIn('eol_defecto_id', $creadoDefectoIds)->delete();
        EolDefecto::whereIn('id', $creadoDefectoIds)->delete();
    }
    if (!empty($creadoPiezaIds)) {
        EolPiezaEvento::whereIn('pieza_id', $creadoPiezaIds)->delete();
        EolPieza::whereIn('id', $creadoPiezaIds)->delete();
    }
    // Por si el escenario de error 8 llego a crear algo pese al rollback esperado.
    limpiar($todosLosQr);

    echo "[datos de prueba eliminados]\n";
}

echo "\n" . ($fallos === 0 ? "\e[32mTODO OK\e[0m" : "\e[31m$fallos fallo(s)\e[0m") . "\n";
