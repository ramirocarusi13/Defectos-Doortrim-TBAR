<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Origen del defecto declarado por el retrabajador al cerrar el retrabajo:
 * puesto de trabajo de la linea (operacion_id / operacion_nombre), turno
 * (turno_generacion A/B/N) y operario (operario_id = legajo / operario_nombre).
 * Esas columnas ya existian (2026_06_19_000020); aca se suma el SECTOR del puesto
 * (FRONT / TUMBLE / SPACE-UP / TIP-UP, segun la dotacion "MANNING SEAT") para
 * agrupar el tablero en tiempo real, y un indice por asignado_at (el tablero
 * filtra por esa fecha).
 *
 * Defensivo (hasColumn) como el resto de las migraciones EOL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eol_defectos', function (Blueprint $table) {
            if (!Schema::hasColumn('eol_defectos', 'sector_generacion')) {
                $table->string('sector_generacion', 40)->nullable();
            }
        });

        // Indice aparte (SQL Server no deja crear la columna e indexarla en el mismo
        // ALTER si ya existia de una corrida parcial).
        if (!$this->tieneIndice('eol_defectos', 'eol_defectos_asignado_at_index')) {
            Schema::table('eol_defectos', function (Blueprint $table) {
                $table->index('asignado_at', 'eol_defectos_asignado_at_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->tieneIndice('eol_defectos', 'eol_defectos_asignado_at_index')) {
            Schema::table('eol_defectos', function (Blueprint $table) {
                $table->dropIndex('eol_defectos_asignado_at_index');
            });
        }

        Schema::table('eol_defectos', function (Blueprint $table) {
            if (Schema::hasColumn('eol_defectos', 'sector_generacion')) {
                $table->dropColumn('sector_generacion');
            }
        });
    }

    private function tieneIndice(string $tabla, string $indice): bool
    {
        foreach (Schema::getIndexes($tabla) as $idx) {
            if (strcasecmp($idx['name'] ?? '', $indice) === 0) {
                return true;
            }
        }

        return false;
    }
};
