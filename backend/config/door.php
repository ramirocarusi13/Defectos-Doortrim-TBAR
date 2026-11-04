<?php

/**
 * Linea de door trims. Los QR los genera la app de camaras del .153 y sus datos
 * estan en la base TB-L de ese server (conexion 'tbl', SIEMPRE de solo lectura:
 * ver AppServiceProvider). Door no consulta JITMS.
 */
return [
    // SELECT que devuelve la hora del panel (columna 'fecha') para el QR leido ('?').
    // Vacio = el panel queda sin control de curado. Se completa al ver la tabla real
    // con: php backend/tbl-explorar.php
    'tbl_sql_panel' => env('DOOR_TBL_SQL_PANEL'),

    // Control final: el sistema del puesto deja el QR de la pieza en esta tabla
    // (una fila por TIPO: Panel / Armrest / Garnish) y la tablet de Calidad la abre
    // sola. Se lee por la conexion 'tbl' (mismo server .153, solo lectura).
    'control_final_tabla' => env('DOOR_CONTROL_FINAL_TABLA', '[TB-PLC].[dbo].[tblDoorControlFinal]'),
];
