<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro de piezas serializadas (door / arm rest) para la linea de door.
 *
 * Cada pieza tiene un QR permanente (se pega en la pieza, no en el kanban) y
 * viaja por un mini buffer propio: se da de alta al terminar su adhesivado,
 * queda DISPONIBLE tras el secado (disponible_desde), se entrega en un
 * cambio de pieza, puede volver como DEFECTUOSA/EN_RETRABAJO y reingresar al
 * buffer. Vive en una base nueva (EOL_DOOR) pero la migracion tambien corre
 * sobre EOL_TBAR en desarrollo local, por eso es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eol_piezas')) {
            return;
        }

        Schema::create('eol_piezas', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Identificacion permanente de la pieza
            $table->string('pieza_qr', 120)->unique();
            $table->string('tipo_pieza', 30)->index(); // DOOR | ARM_REST

            $table->string('modelo', 150)->nullable()->index();
            $table->string('codigo', 60)->nullable(); // part number
            $table->string('lado', 30)->nullable();

            // DISPONIBLE | EN_ASIENTO | DEFECTUOSA | EN_RETRABAJO | PEND_VALIDACION | SCRAP | BAJA
            $table->string('estado', 20)->default('DISPONIBLE');

            // Ubicacion en el mini buffer (sin FK, mismo patron que puesto_id/puesto_nombre)
            $table->string('buffer_id', 60)->nullable();
            $table->string('buffer_nombre', 120)->nullable();
            $table->string('linea', 60)->nullable()->index();

            // Fin del secado del adhesivado; null = ya disponible
            $table->dateTime('disponible_desde')->nullable();

            $table->unsignedBigInteger('defecto_id_actual')->nullable()->index();
            $table->unsignedBigInteger('door_id_actual')->nullable();

            $table->integer('ciclos_cambio')->default(0);
            $table->integer('ciclos_retrabajo')->default(0);

            // Trazabilidad: alta (fin de adhesivado)
            $table->unsignedBigInteger('alta_por')->nullable();
            $table->string('alta_por_nombre', 120)->nullable();
            $table->dateTime('alta_at')->nullable();

            // Trazabilidad: quien la tomo del buffer
            $table->unsignedBigInteger('tomado_por')->nullable();
            $table->string('tomado_por_nombre', 120)->nullable();
            $table->dateTime('tomado_at')->nullable();

            $table->dateTime('ultimo_evento_at')->nullable();
            $table->string('observaciones', 500)->nullable();

            $table->timestamps();

            $table->index(['estado', 'tipo_pieza']);
            $table->index(['buffer_id', 'estado']);
            $table->index(['estado', 'disponible_desde']);
            $table->index(['modelo', 'lado', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eol_piezas');
    }
};
