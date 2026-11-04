<?php
/**
 * SEMILLA: catalogo de componentes consumidos en retrabajo (funda, uretano,
 * frame, etc), separados por linea SEAT (SEPA/BENCH) y TIP-UP, segun el
 * formulario papel FR 04-13-05.1 del cliente.
 *
 * Idempotente por (code, grupo): si el componente ya existe en el factory
 * config vigente (misma tabla/registro que usa FactoryConfigController, key
 * 'active') NO lo toca (conserva active/nombre editados a mano); si falta lo
 * agrega con active=true y un id nuevo estilo 'comp-N'. Nunca borra ni
 * desactiva nada de lo que ya haya.
 *
 *   php seed-componentes.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FactoryConfig;

const CONFIG_KEY = 'active';

// code | name | grupo ('SEAT' | 'TIPUP')
$CATALOGO = [
    // ---------------- SEAT ----------------
    ['FUNDA-BACK', 'Funda Back', 'SEAT'],
    ['FUNDA-CUSHION', 'Funda Cushion', 'SEAT'],
    ['URETANO-BACK', 'Uretano Back', 'SEAT'],
    ['URETANO-CUSHION', 'Uretano Cushion', 'SEAT'],
    ['FRAME-BACK', 'Frame Back', 'SEAT'],
    ['FRAME-CUSHION', 'Frame Cushion', 'SEAT'],
    ['PARRILLA-FRAME-BACK', 'Parrilla Frame Back', 'SEAT'],
    ['PARRILLA-FRAME-CUSHION', 'Parrilla Frame Cushion', 'SEAT'],
    ['TUERCA-TRABACINTO', 'Tuerca de Trabacinto', 'SEAT'],
    ['ARANDELA-TRABACINTO', 'Arandela de Trabacinto', 'SEAT'],
    ['TRABACINTO', 'Trabacinto', 'SEAT'],
    ['TORNILLO-CASAMIENTO', 'Tornillo de Casamiento', 'SEAT'],
    ['MISIL-CON-TRABA', 'Misil con Traba', 'SEAT'],
    ['MISIL-SIN-TRABA', 'Misil sin Traba', 'SEAT'],
    ['SHOPING-HOOCK', 'Shoping Hoock', 'SEAT'],
    ['CACHA-INTERNA', 'Cacha interna', 'SEAT'],
    ['SHIELD-MANUAL', 'Shield modelo manual', 'SEAT'],
    ['SHIELD-POWER-LATERAL', 'Shield power lateral', 'SEAT'],
    ['SHIELD-POWER-FRONTAL', 'Shield power frontal', 'SEAT'],
    ['SWITCH-SHIELD-POWER', 'Switch Shield power', 'SEAT'],
    ['MANETA-MANUAL', 'Maneta manual', 'SEAT'],
    ['MANETA-LIFTER-M6', 'Maneta de lifter M6', 'SEAT'],
    ['HANDLE', 'Handle', 'SEAT'],
    ['FUNDA-APOYACABEZA', 'Funda de apoyacabeza', 'SEAT'],
    ['URETANO-APOYACABEZA', 'Uretano de apoyacabeza', 'SEAT'],
    ['SENSOR-CUSHION', 'Sensor cushion', 'SEAT'],
    // ---------------- TIPUP ----------------
    ['FUNDA-BACK', 'Funda Back', 'TIPUP'],
    ['FUNDA-CUSHION', 'Funda Cushion', 'TIPUP'],
    ['FUNDA-CUSHION-60', 'Funda Cushion 60%', 'TIPUP'],
    ['FUNDA-CUSHION-40', 'Funda Cushion 40%', 'TIPUP'],
    ['URETANO-BACK', 'Uretano Back', 'TIPUP'],
    ['URETANO-CUSHION', 'Uretano Cushion', 'TIPUP'],
    ['URETANO-CUSHION-60', 'Uretano Cushion 60%', 'TIPUP'],
    ['URETANO-CUSHION-40', 'Uretano Cushion 40%', 'TIPUP'],
    ['METALICO-BACK', 'Metálico Back', 'TIPUP'],
    ['FUNDA-APOYABRAZO', 'Funda apoyabrazo', 'TIPUP'],
    ['URETANO-APOYABRAZO', 'Uretano de apoyabrazo', 'TIPUP'],
    ['CAT-HOLDER', 'Cat Holder', 'TIPUP'],
    ['TUERCA-CASAMIENTO', 'Tuercas de Casamiento', 'TIPUP'],
    ['TORNILLO-CASAMIENTO', 'Tornillos de Casamiento', 'TIPUP'],
    ['MISIL-CON-TRABA', 'Misiles con Traba', 'TIPUP'],
    ['MISIL-SIN-TRABA', 'Misiles sin Traba', 'TIPUP'],
    ['CACHA-INTERNA-60', 'Cacha interna 60%', 'TIPUP'],
    ['CACHA-EXTERNA-60', 'Cacha externa 60%', 'TIPUP'],
    ['CACHA-INTERNA-40', 'Cacha interna 40%', 'TIPUP'],
    ['CACHA-EXTERNA-40', 'Cacha externa 40%', 'TIPUP'],
    ['CACHA-EXTERNA-LH', 'Cacha externa LH', 'TIPUP'],
    ['CACHA-EXTERNA-RH', 'Cacha externa RH', 'TIPUP'],
    ['HOOCK', 'Hoock', 'TIPUP'],
    ['HOOCK-60', 'Hoock de 60%', 'TIPUP'],
    ['HOOCK-40', 'Hoock de 40%', 'TIPUP'],
    ['TORNILLO-HOOCK', 'Tornillo de Hoock', 'TIPUP'],
    ['TORNILLO-HOOCK-60', 'Tornillo de Hoock 60%', 'TIPUP'],
    ['TORNILLO-HOOCK-40', 'Tornillo de Hoock 40%', 'TIPUP'],
];

$row  = FactoryConfig::query()->where('key', CONFIG_KEY)->first();
$data = $row?->data ?: [];

$componentes = is_array($data['componentes'] ?? null) ? $data['componentes'] : [];

// Proximo id numerico libre, estilo 'comp-N' (a partir de los ids existentes).
$maxN = 0;
foreach ($componentes as $c) {
    $id = (string) ($c['id'] ?? '');
    if (preg_match('/^comp-(\d+)$/', $id, $m)) {
        $maxN = max($maxN, (int) $m[1]);
    }
}

// Indice de lo existente por (code, grupo) normalizado, para el match idempotente.
$existentes = [];
foreach ($componentes as $c) {
    $code  = strtoupper(trim((string) ($c['code'] ?? '')));
    $grupo = strtoupper(trim((string) ($c['grupo'] ?? '')));
    $existentes[$code . '|' . $grupo] = true;
}

$agregados = [];
$yaExistian = [];

foreach ($CATALOGO as [$code, $name, $grupo]) {
    $clave = strtoupper($code) . '|' . strtoupper($grupo);
    if (isset($existentes[$clave])) {
        $yaExistian[] = "$code ($grupo)";
        continue;
    }

    $maxN++;
    $nuevo = [
        'id'     => 'comp-' . $maxN,
        'code'   => $code,
        'name'   => $name,
        'active' => true,
        'grupo'  => $grupo,
    ];
    $componentes[] = $nuevo;
    $existentes[$clave] = true; // por si el catalogo trajera duplicados
    $agregados[] = "$code ($grupo)";
}

$data['componentes'] = $componentes;

FactoryConfig::updateOrCreate(
    ['key' => CONFIG_KEY],
    ['data' => $data],
);

echo "=============================================================\n";
echo "  SEED CATALOGO DE COMPONENTES (SEAT / TIP-UP)\n";
echo "=============================================================\n";
echo 'Agregados (' . count($agregados) . "):\n";
foreach ($agregados as $a) {
    echo "  + $a\n";
}
echo 'Ya existian, sin tocar (' . count($yaExistian) . "):\n";
foreach ($yaExistian as $e) {
    echo "  = $e\n";
}
echo "\nTotal componentes en config: " . count($componentes) . "\n";
