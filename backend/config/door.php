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
    'control_final_tabla' => env('DOOR_CONTROL_FINAL_TABLA', '[TB-PLC].[dbo].[tblDoorMain]'),

    // Puesto de reetiquetado (panel NG): cada puesto imprime en su Zebra (ZPL por
    // TCP 9100). El numero del puesto es el de la impresora.
    // OJO 05/11/2026: la impresora 4 perdio la IP (.90 por DHCP; quedo en la
    // 192.168.254.254 de fabrica). Hasta que IT le fije la IP, Produccion 4 imprime
    // en la impresora 3 (.68). Cuando la tenga: volver a '172.17.129.90' (o su IP).
    'impresoras' => [
        'PRODUCCION 4' => env('DOOR_IMPRESORA_PRODUCCION_4', '172.17.129.68'),
        'PRODUCCION 3' => env('DOOR_IMPRESORA_PRODUCCION_3', '172.17.129.68'),
    ],

    // Ajuste de la etiqueta reimpresa a la etiqueta fisica (Zebra ZT231 203 dpi).
    // escala_x/escala_y: tamano del diseno (812x812 pts) en ancho y alto; x/y:
    // corrimiento en puntos (8 pts = 1 mm); qr_aumento: tamano del QR (3-5);
    // oscuridad: -30..30 relativa a la de la impresora; velocidad: pulgadas/seg.
    'etiqueta' => [
        'escala_x'   => (float) env('DOOR_ETIQUETA_ESCALA_X', 0.95),
        'escala_y'   => (float) env('DOOR_ETIQUETA_ESCALA_Y', 0.98),
        'x'          => (int) env('DOOR_ETIQUETA_X', 36),
        'y'          => (int) env('DOOR_ETIQUETA_Y', 14),
        'qr_aumento' => (int) env('DOOR_ETIQUETA_QR', 4),
        'largo'     => (int) env('DOOR_ETIQUETA_LARGO', 812),
        'oscuridad' => (int) env('DOOR_ETIQUETA_OSCURIDAD', 15),
        'velocidad' => (int) env('DOOR_ETIQUETA_VELOCIDAD', 4),
    ],
];
