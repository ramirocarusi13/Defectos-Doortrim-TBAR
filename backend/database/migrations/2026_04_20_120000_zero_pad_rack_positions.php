<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const RACKS_DEPOSITO_ID = 8;

    public function up() {
        DB::transaction(function () {
            $ubicaciones = DB::table('ubicaciones')
                ->where('deposito_id', self::RACKS_DEPOSITO_ID)
                ->get(['id', 'nombre']);

            foreach ($ubicaciones as $ubicacion) {
                $nombre = strtoupper(trim($ubicacion->nombre ?? ''));

                if (!preg_match('/^([A-H])-(\d+)-([0-4])$/', $nombre, $m)) {
                    continue;
                }

                $row = intval($m[2]);
                $nuevo = $m[1] . '-' . str_pad($row, 2, '0', STR_PAD_LEFT) . '-' . $m[3];

                if ($nuevo === $nombre) {
                    continue;
                }

                $yaExiste = DB::table('ubicaciones')
                    ->where('deposito_id', self::RACKS_DEPOSITO_ID)
                    ->whereRaw('UPPER(nombre) = ?', [$nuevo])
                    ->where('id', '<>', $ubicacion->id)
                    ->exists();

                if ($yaExiste) {
                    DB::table('ubicaciones')
                        ->where('id', $ubicacion->id)
                        ->delete();
                    continue;
                }

                DB::table('ubicaciones')
                    ->where('id', $ubicacion->id)
                    ->update([
                        'nombre' => $nuevo,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down() {
        // Las A-A-1 legacy no se tocan, solo revierte el padding.
        DB::transaction(function () {
            $ubicaciones = DB::table('ubicaciones')
                ->where('deposito_id', self::RACKS_DEPOSITO_ID)
                ->get(['id', 'nombre']);

            foreach ($ubicaciones as $ubicacion) {
                $nombre = strtoupper(trim($ubicacion->nombre ?? ''));

                if (!preg_match('/^([A-H])-0(\d+)-([0-4])$/', $nombre, $m)) {
                    continue;
                }

                $sinPad = $m[1] . '-' . intval($m[2]) . '-' . $m[3];

                DB::table('ubicaciones')
                    ->where('id', $ubicacion->id)
                    ->update([
                        'nombre' => $sinPad,
                        'updated_at' => now(),
                    ]);
            }
        });
    }
};
