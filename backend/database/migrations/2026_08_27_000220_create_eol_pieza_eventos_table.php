<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de eventos de cada pieza serializada del buffer de door.
 * Clon estructural de eol_defecto_eventos (misma logica: solo created_at,
 * sin updated_at, es un log append-only de trazabilidad).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eol_pieza_eventos')) {
            return;
        }

        Schema::create('eol_pieza_eventos', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('pieza_id')->index();
            $table->string('pieza_qr', 120)->nullable();

            // ALTA | ENTREGADA | RETIRADA_DEFECTUOSA | TOMADA_RETRABAJO | RETRABAJADA |
            // PEND_VALIDACION | VALIDADA | RECHAZADA | REINGRESO_BUFFER | SCRAP | BAJA | AJUSTE_MANUAL
            $table->string('evento', 30);

            $table->unsignedBigInteger('defecto_id')->nullable();
            $table->unsignedBigInteger('door_id')->nullable();

            $table->string('estado_desde', 20)->nullable();
            $table->string('estado_hasta', 20)->nullable();

            $table->string('buffer_id', 60)->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_nombre', 120)->nullable();
            $table->string('departamento', 60)->nullable();
            $table->string('turno', 10)->nullable();

            $table->string('puesto_id', 60)->nullable();
            $table->string('puesto_nombre', 120)->nullable();

            $table->text('observaciones')->nullable();

            $table->dateTime('created_at')->nullable();

            $table->index(['pieza_id', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eol_pieza_eventos');
    }
};
