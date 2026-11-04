<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('eol_defectos', function (Blueprint $table) {
            // Trazabilidad: quien GENERO el defecto (asignado en retrabajo por el TL)
            $table->string('linea_generacion', 60)->nullable()->after('linea');
            $table->boolean('es_sublinea')->default(false)->after('linea_generacion');
            $table->string('turno_generacion', 20)->nullable()->after('es_sublinea');
            $table->string('operacion_id', 60)->nullable()->after('turno_generacion');
            $table->string('operacion_nombre', 150)->nullable()->after('operacion_id');
            $table->string('operario_id', 60)->nullable()->after('operacion_nombre');
            $table->string('operario_nombre', 150)->nullable()->after('operario_id');
            $table->unsignedBigInteger('autorizante_id')->nullable()->after('operario_nombre');
            $table->dateTime('asignado_at')->nullable()->after('autorizante_id');
        });
    }

    public function down(): void
    {
        Schema::table('eol_defectos', function (Blueprint $table) {
            $table->dropColumn([
                'linea_generacion', 'es_sublinea', 'turno_generacion',
                'operacion_id', 'operacion_nombre', 'operario_id', 'operario_nombre',
                'autorizante_id', 'asignado_at',
            ]);
        });
    }
};
