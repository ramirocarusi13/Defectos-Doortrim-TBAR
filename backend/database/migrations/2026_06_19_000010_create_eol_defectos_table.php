<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('eol_defectos', function (Blueprint $table) {
            $table->id();

            // Identificacion de la pieza / etiqueta
            $table->string('qr', 120)->nullable()->index();
            $table->string('modelo', 150)->nullable();
            $table->string('codigo', 60)->nullable();
            $table->string('sd', 30)->nullable();
            $table->string('secuencia', 30)->nullable();

            // Ubicacion / contexto (config nueva: linea y fabrica por NOMBRE, string)
            $table->string('linea', 60)->nullable()->index();
            $table->string('fabrica', 60)->nullable();
            $table->string('ubicacion', 60)->nullable();
            $table->string('lado', 30)->nullable();
            $table->string('tipo', 30)->nullable();

            // Defecto marcado
            $table->string('falla_id', 60)->nullable();        // id de config (string) o de API
            $table->string('falla_codigo', 60)->nullable();
            $table->string('falla_nombre', 150)->nullable();
            $table->integer('cantidad')->default(1);
            $table->boolean('es_critico')->default(false);
            $table->boolean('es_leve')->default(false);
            $table->string('observaciones', 500)->nullable();

            // Coordenadas de la marca (porcentaje sobre la vista) + tamano de pantalla
            $table->float('pos_x')->nullable();
            $table->float('pos_y')->nullable();
            $table->float('screen_x')->nullable();
            $table->float('screen_y')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('imagen_marcada', 255)->nullable();

            // Trazabilidad: quien marco (calidad fin de linea)
            $table->string('estado', 20)->default('PENDIENTE'); // PENDIENTE | REPARADO | SCRAP
            $table->unsignedBigInteger('marcado_por')->nullable();
            $table->string('marcado_por_nombre', 120)->nullable();
            $table->string('turno', 20)->nullable();
            $table->dateTime('marcado_at')->nullable();

            // Trazabilidad: quien reparo (produccion)
            $table->unsignedBigInteger('reparado_por')->nullable();
            $table->string('reparado_por_nombre', 120)->nullable();
            $table->dateTime('reparado_at')->nullable();
            $table->string('reparacion_obs', 500)->nullable();

            $table->timestamps();

            $table->index(['estado', 'linea']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eol_defectos');
    }
};
