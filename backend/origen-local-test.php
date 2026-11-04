<?php
/**
 * PRUEBA LOCAL del ORIGEN del defecto (puesto de trabajo / turno / operario que lo
 * genero) declarado por el retrabajador, contra la base local (EOL_TBAR).
 * Crea los defectos de prueba directo en eol_defectos (NO pasa por store(), asi
 * que no toca JITMS / interlock) y los borra al final.
 *
 *   1) reparar con componentes + origen -> columnas de origen + resumen en obs y evento
 *   2) origen con operario vacio -> NO IDENTIFICADO (operario null), turno AMARILLO -> A
 *   3) reparar sin origen -> no toca columnas de origen (tablets viejas)
 *   4) origen sin puesto -> se ignora
 *   5) GET reporte-origen -> totales, por puesto, por operario, por turno, sin operario,
 *      filtro por turno, excluye PRACTICA, pendientes sin origen
 *
 *   php origen-local-test.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\EolDefectoController;
use App\Http\Controllers\ReporteOrigenController;
use App\Models\EolDefecto;
use App\Models\EolDefectoComponente;
use App\Models\EolDefectoEvento;
use App\Models\User;
use Illuminate\Http\Request;

$fallos = 0;
function chk(bool $cond, string $label): void {
    global $fallos;
    echo $label . ' [' . ($cond ? "\e[32mOK\e[0m" : "\e[31mFALLA\e[0m") . "]\n";
    if (!$cond) $fallos++;
}

echo "=============================================================\n";
echo "  PRUEBA LOCAL ORIGEN DEL DEFECTO (puesto / turno / operario)\n";
echo "=============================================================\n";

$PREFIX = 'TEST-ORIGEN-261022';

$limpiar = function () use ($PREFIX) {
    $ids = EolDefecto::where('qr', 'like', "$PREFIX%")->pluck('id');
    EolDefectoComponente::whereIn('eol_defecto_id', $ids)->delete();
    EolDefectoEvento::whereIn('eol_defecto_id', $ids)->delete();
    EolDefecto::whereIn('id', $ids)->delete();
};
$limpiar();

$user = User::where('cod_autorizacion', '9001')->first() ?: User::first();
if (!$user) {
    echo "\e[31mNo hay usuarios en la base local.\e[0m\n";
    exit(1);
}

$crear = function (string $sufijo, string $flujo = 'EOL') use ($PREFIX) {
    return EolDefecto::create([
        'qr' => "$PREFIX-$sufijo", 'modelo' => 'TEST', 'linea' => 'TEST', 'tipo' => 'BC', 'lado' => 'RH',
        'falla_codigo' => 'TST', 'falla_nombre' => 'Falla de prueba', 'cantidad' => 1,
        'estado' => 'PENDIENTE', 'flujo' => $flujo, 'marcado_at' => now(), 'turno' => 'A',
    ])->id;
};

$controller = new EolDefectoController();
$reparar = function (int $id, array $payload) use ($controller, $user) {
    $req = Request::create("/api/eol-defecto/$id/reparar", 'PUT', array_merge(['user' => $user->id, 'estado' => 'RETRABAJADO'], $payload));
    return $controller->reparar($req, $id);
};

try {
    // Base del reporte ANTES (puede haber datos reales de hoy en la base local).
    $antes = json_decode((new ReporteOrigenController())->index(Request::create('/x', 'GET'))->getContent(), true)['data'];

    echo "\n-- 1) componentes + origen --\n";
    $id1 = $crear('0001');
    $resp = $reparar($id1, [
        'componentes' => [['codigo' => 'FUNDA', 'nombre' => 'Funda', 'accion' => 'CAMBIADO', 'cantidad' => 1]],
        'origen' => [
            'puestoId' => 'pt-fr-3', 'puestoCodigo' => 'FR 3', 'puestoNombre' => 'Engrampado Back', 'sector' => 'front',
            'turno' => 'A', 'operarioLegajo' => '2236', 'operarioNombre' => 'Abalsamo Julián Agustín',
        ],
    ]);
    chk($resp->getStatusCode() === 200, '1 · status 200');
    $d1 = EolDefecto::find($id1);
    chk($d1->estado === 'RETRABAJADO', '1 · estado RETRABAJADO');
    chk($d1->operacion_id === 'pt-fr-3' && $d1->operacion_nombre === 'FR 3 - Engrampado Back', '1 · puesto guardado (' . $d1->operacion_nombre . ')');
    chk($d1->sector_generacion === 'FRONT', '1 · sector normalizado a FRONT');
    chk($d1->turno_generacion === 'A', '1 · turno A');
    chk($d1->operario_id === '2236' && $d1->operario_nombre === 'Abalsamo Julián Agustín', '1 · operario legajo + nombre');
    chk((int) $d1->autorizante_id === (int) $user->id && $d1->asignado_at !== null, '1 · autorizante y asignado_at');
    chk(str_contains($d1->reparacion_obs, 'Componentes: 1x FUNDA') && str_contains($d1->reparacion_obs, 'Origen: FR 3'), '1 · obs con componentes y origen');
    $ev = EolDefectoEvento::where('eol_defecto_id', $id1)->where('evento', 'RETRABAJADO')->first();
    chk($ev && str_contains($ev->observaciones, 'Abalsamo'), '1 · evento RETRABAJADO con el origen');
    chk(EolDefectoComponente::where('eol_defecto_id', $id1)->count() === 1, '1 · componente registrado igual que antes');

    echo "\n-- 1b) trazabilidad del defecto --\n";
    $tz = json_decode($controller->trazabilidad($id1)->getContent(), true)['data'];
    chk($tz['estado'] === 'RETRABAJADO' && $tz['retrabajo'] !== null, '1b · estado y retrabajo');
    chk(($tz['origen']['puesto'] ?? null) === 'FR 3 - Engrampado Back' && ($tz['origen']['sector'] ?? null) === 'FRONT', '1b · origen puesto + sector');
    chk(($tz['origen']['turno'] ?? null) === 'A (AMARILLO)' && ($tz['origen']['legajo'] ?? null) === '2236', '1b · origen turno + legajo');
    chk(($tz['origen']['declaradoPor'] ?? null) === $user->name, '1b · origen declarado por el retrabajador');
    chk(count($tz['componentes']) === 1 && $tz['componentes'][0]['accion'] === 'CAMBIADO', '1b · componentes');
    chk(collect($tz['eventos'])->contains('evento', 'RETRABAJADO') && $tz['validacion'] === null, '1b · eventos y sin validar');
    chk($controller->trazabilidad(999999999)->getStatusCode() === 404, '1b · id inexistente -> 404');

    echo "\n-- 2) operario no identificado + turno por nombre --\n";
    $id2 = $crear('0002');
    $reparar($id2, ['origen' => ['puestoCodigo' => 'T-UP 2', 'puestoNombre' => 'Alambre', 'sector' => 'TIP-UP', 'turno' => 'AMARILLO', 'operarioLegajo' => null, 'operarioNombre' => null]]);
    $d2 = EolDefecto::find($id2);
    chk($d2->operacion_id === 'T-UP 2' && $d2->operario_nombre === null, '2 · puesto sin operario');
    chk($d2->turno_generacion === 'A', '2 · AMARILLO -> A');
    chk(str_contains($d2->reparacion_obs, 'NO IDENTIFICADO'), '2 · obs dice NO IDENTIFICADO');

    echo "\n-- 3) sin origen (tablet vieja) --\n";
    $id3 = $crear('0003');
    $reparar($id3, []);
    $d3 = EolDefecto::find($id3);
    chk($d3->estado === 'RETRABAJADO' && $d3->asignado_at === null && $d3->operacion_id === null, '3 · retrabaja sin tocar origen');

    echo "\n-- 4) origen sin puesto --\n";
    $id4 = $crear('0004');
    $reparar($id4, ['origen' => ['turno' => 'A', 'operarioNombre' => 'X']]);
    chk(EolDefecto::find($id4)->asignado_at === null, '4 · se ignora');

    echo "\n-- 5) reporte-origen --\n";
    $id5 = $crear('0005');
    $reparar($id5, ['origen' => ['puestoId' => 'pt-fr-3', 'puestoCodigo' => 'FR 3', 'puestoNombre' => 'Engrampado Back', 'sector' => 'FRONT', 'turno' => 'B', 'operarioLegajo' => '5174', 'operarioNombre' => 'Abatangelo Uriel Nicolas']]);
    $id6 = $crear('0006', 'PRACTICA');
    $reparar($id6, ['origen' => ['puestoCodigo' => 'FR 3', 'turno' => 'A', 'operarioLegajo' => '2236', 'operarioNombre' => 'Abalsamo Julián Agustín']]);
    $crear('0007'); // pendiente sin origen

    $rep = json_decode((new ReporteOrigenController())->index(Request::create('/x', 'GET'))->getContent(), true)['data'];
    chk($rep['total'] - $antes['total'] === 3, '5 · total +3 (excluye PRACTICA) -> ' . $rep['total']);
    chk($rep['sinOperario'] - $antes['sinOperario'] === 1, '5 · sin operario +1');
    chk($rep['pendientesSinOrigen'] - $antes['pendientesSinOrigen'] === 1, '5 · pendientes sin origen +1');
    $fr3 = collect($rep['porPuesto'])->firstWhere('id', 'pt-fr-3');
    chk($fr3 && $fr3['total'] >= 2 && $fr3['sector'] === 'FRONT', '5 · FR 3 con 2 defectos');
    $abal = collect($rep['porOperario'])->firstWhere('legajo', '2236');
    chk($abal && $abal['total'] >= 1 && ($abal['puestos'][0]['nombre'] ?? '') === 'FR 3 - Engrampado Back', '5 · ranking operario con su puesto');
    chk(count($rep['porHora']) === 24 && $rep['porHora'][0]['hora'] === 6, '5 · por hora arranca 06h');
    chk(collect($rep['ultimos'])->contains('id', $id1), '5 · ultimos incluye el defecto 1');

    $repB = json_decode((new ReporteOrigenController())->index(Request::create('/x', 'GET', ['turno' => 'BLANCO']))->getContent(), true)['data'];
    chk($repB['turno'] === 'B' && collect($repB['ultimos'])->contains('id', $id5) && !collect($repB['ultimos'])->contains('id', $id1), '5 · filtro turno BLANCO');

    $mal = (new ReporteOrigenController())->index(Request::create('/x', 'GET', ['fecha' => '22-10-2026']));
    chk($mal->getStatusCode() === 422, '5 · fecha invalida -> 422');
} finally {
    $limpiar();
}

echo "\n" . ($fallos === 0 ? "\e[32mTODO OK\e[0m" : "\e[31m$fallos FALLA(S)\e[0m") . "\n";
exit($fallos === 0 ? 0 : 1);
