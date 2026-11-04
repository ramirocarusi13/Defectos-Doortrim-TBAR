<?php

/**
 * Config general del modulo EOL (fuera de factory-config, que vive en DB).
 * Sirve para apagar cosas por deploy via .env, sin tocar codigo.
 */
return [
    // Si esta apagado, EolDefectoController NO llama a InterlockService (ni al
    // marcar ni al validar). Se usa en el deploy de la linea de door (EOL_DOOR),
    // que no tiene conexion a JITMS.
    'interlock_enabled' => env('EOL_INTERLOCK_ENABLED', true),

    // Si esta prendido, la conexion a JITMS (sqlsrvsar) solo acepta lecturas:
    // cualquier INSERT/UPDATE/DELETE/EXEC se rechaza antes de llegar al servidor
    // (ver AppServiceProvider). Va en true en el deploy de door trims.
    'jitms_solo_lectura' => (bool) env('JITMS_SOLO_LECTURA', false),
];
