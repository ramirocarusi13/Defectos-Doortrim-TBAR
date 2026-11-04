<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Engancha el flujo de door de piezas (door/arm rest) al defecto EOL clasico.
 *
 * Un defecto puede originar un cambio de pieza en vez de un retrabajo manual;
 * 'flujo' discrimina el camino sin romper el modelo EolDefecto existente.
 * Todo ADD COLUMN es defensivo (Schema::hasColumn) y con default, asi que es
 * inocuo tanto en EOL_TBAR (produccion, con ~13.000 defectos historicos) como
 * en la EOL_DOOR nueva si eol_defectos no existiera ahi (se guarda tambien
 * con Schema::hasTable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eol_defectos')) {
            return;
        }

        Schema::table('eol_defectos', function (Blueprint $table) {
            if (!Schema::hasColumn('eol_defectos', 'flujo')) {
                $table->string('flujo', 10)->default('EOL')->index();
            }
            if (!Schema::hasColumn('eol_defectos', 'pieza_id')) {
                $table->unsignedBigInteger('pieza_id')->nullable()->index();
            }
            if (!Schema::hasColumn('eol_defectos', 'pieza_qr')) {
                $table->string('pieza_qr', 120)->nullable()->index();
            }
            if (!Schema::hasColumn('eol_defectos', 'door_id')) {
                $table->unsignedBigInteger('door_id')->nullable()->index();
            }
            if (!Schema::hasColumn('eol_defectos', 'cambiado_at')) {
                $table->dateTime('cambiado_at')->nullable();
            }
            if (!Schema::hasColumn('eol_defectos', 'cambiado_por')) {
                $table->unsignedBigInteger('cambiado_por')->nullable();
            }
            if (!Schema::hasColumn('eol_defectos', 'cambiado_por_nombre')) {
                $table->string('cambiado_por_nombre', 120)->nullable();
            }
        });

        // Backfill: defectos historicos (EOL clasico) sin flujo asignado.
        DB::table('eol_defectos')->whereNull('flujo')->update(['flujo' => 'EOL']);

        if (!$this->indexExists('eol_defectos', 'eol_defectos_flujo_estado_index')) {
            Schema::table('eol_defectos', function (Blueprint $table) {
                $table->index(['flujo', 'estado'], 'eol_defectos_flujo_estado_index');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('eol_defectos')) {
            return;
        }

        // SQL Server no permite DROP COLUMN si la columna tiene un indice: hay
        // que tirar primero el compuesto y despues cada indice individual
        // (creados via ->index() con el nombre convencional de Laravel).
        if ($this->indexExists('eol_defectos', 'eol_defectos_flujo_estado_index')) {
            Schema::table('eol_defectos', function (Blueprint $table) {
                $table->dropIndex('eol_defectos_flujo_estado_index');
            });
        }

        $indicesSimples = [
            'eol_defectos_flujo_index',
            'eol_defectos_pieza_id_index',
            'eol_defectos_pieza_qr_index',
            'eol_defectos_door_id_index',
        ];
        foreach ($indicesSimples as $indexName) {
            if ($this->indexExists('eol_defectos', $indexName)) {
                Schema::table('eol_defectos', function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }

        $columnas = ['flujo', 'pieza_id', 'pieza_qr', 'door_id', 'cambiado_at', 'cambiado_por', 'cambiado_por_nombre'];
        $existentes = array_filter($columnas, fn ($col) => Schema::hasColumn('eol_defectos', $col));

        if (!empty($existentes)) {
            Schema::table('eol_defectos', function (Blueprint $table) use ($existentes) {
                $table->dropColumn(array_values($existentes));
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)',
            [$indexName, $table]
        );

        return $result && (int) $result->total > 0;
    }
};
