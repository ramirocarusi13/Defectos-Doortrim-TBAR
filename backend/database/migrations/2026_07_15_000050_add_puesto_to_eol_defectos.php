<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Puesto de control (calidad) donde se marco el defecto. Cada tablet es un
        // puesto fijo (id + nombre vienen de factory-config). Trazabilidad: en que
        // puesto se detecto/controlo, ademas de quien.
        Schema::table('eol_defectos', function (Blueprint $table) {
            if (!Schema::hasColumn('eol_defectos', 'puesto_id')) {
                $table->string('puesto_id', 60)->nullable()->index();
            }
            if (!Schema::hasColumn('eol_defectos', 'puesto_nombre')) {
                $table->string('puesto_nombre', 120)->nullable();
            }
        });

        Schema::table('eol_defecto_eventos', function (Blueprint $table) {
            if (!Schema::hasColumn('eol_defecto_eventos', 'puesto_id')) {
                $table->string('puesto_id', 60)->nullable();
            }
            if (!Schema::hasColumn('eol_defecto_eventos', 'puesto_nombre')) {
                $table->string('puesto_nombre', 120)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('eol_defectos', function (Blueprint $table) {
            $table->dropColumn(['puesto_id', 'puesto_nombre']);
        });
        Schema::table('eol_defecto_eventos', function (Blueprint $table) {
            $table->dropColumn(['puesto_id', 'puesto_nombre']);
        });
    }
};
