<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Componentes consumidos al retrabajar un defecto (funda/cierre/etc), declarados
 * por produccion al confirmar reparar(): cada uno con cantidad y accion
 * CAMBIADO|SCRAP. El catalogo de componentes es configurable en factory-config
 * (coleccion 'componentes'); esta tabla es el detalle transaccional que alimenta
 * el reporte de consumo (ReporteComponentesController).
 *
 * Defensivo (Schema::hasTable) como el resto de las migraciones EOL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eol_defecto_componentes')) {
            return;
        }

        Schema::create('eol_defecto_componentes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('eol_defecto_id')->index();
            $table->string('qr', 120)->nullable()->index();
            $table->string('componente_codigo', 60);
            $table->string('componente_nombre', 150)->nullable();
            $table->string('accion', 15)->index(); // CAMBIADO | SCRAP
            $table->integer('cantidad')->default(1);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_nombre', 120)->nullable();
            $table->string('turno', 10)->nullable();
            $table->dateTime('created_at')->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('eol_defecto_componentes')) {
            return;
        }

        Schema::dropIfExists('eol_defecto_componentes');
    }
};
