<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Clasificacion A/B/C del defecto (caracteristica), sellada al marcarlo desde el
 * catalogo de factory-config. Los defectos tipo A son graves: para validarlos hace
 * falta que un GL (rol >= 30) autorice escaneando su codigo; queda sellado en
 * validado_gl_por / validado_gl_por_nombre.
 *
 * Defensivo (Schema::hasColumn) como 2026_08_27_000230, inocuo sobre los ~13.000
 * defectos historicos (quedan con caracteristica NULL, se tratan como 'C' en la app).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('eol_defectos')) {
            return;
        }

        Schema::table('eol_defectos', function (Blueprint $table) {
            if (!Schema::hasColumn('eol_defectos', 'caracteristica')) {
                $table->string('caracteristica', 1)->nullable()->index();
            }
            if (!Schema::hasColumn('eol_defectos', 'validado_gl_por')) {
                $table->unsignedBigInteger('validado_gl_por')->nullable();
            }
            if (!Schema::hasColumn('eol_defectos', 'validado_gl_por_nombre')) {
                $table->string('validado_gl_por_nombre', 120)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('eol_defectos')) {
            return;
        }

        // SQL Server no permite DROP COLUMN si la columna tiene un indice: hay que
        // tirar primero el indice de 'caracteristica' (creado via ->index() con el
        // nombre convencional de Laravel) y despues las columnas.
        if ($this->indexExists('eol_defectos', 'eol_defectos_caracteristica_index')) {
            Schema::table('eol_defectos', function (Blueprint $table) {
                $table->dropIndex('eol_defectos_caracteristica_index');
            });
        }

        $columnas = ['caracteristica', 'validado_gl_por', 'validado_gl_por_nombre'];
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
