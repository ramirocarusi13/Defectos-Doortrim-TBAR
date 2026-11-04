<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria de lo que EOL toca en JITMS (produccion.historicocontrolcalidad).
 *
 * Motivo: al validar un retrabajo hay que revertir SOLO lo que escribio este
 * sistema. No se puede identificar por Mensaje: en produccion ya existen NG con
 * 'Asiento previamente escaneado' escritas por operarios reales (el reescaneo
 * normal las genera), asi que filtrar por texto puede borrar filas ajenas.
 *
 * Guardando el IdHistoricoControlCalidad exacto de cada fila que insertamos o
 * modificamos, la limpieza opera por ID y nunca puede tocar un registro de otro
 * sistema. Ademas guarda el Mensaje original para poder restaurarlo tal cual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eol_interlock_marcas', function (Blueprint $table) {
            $table->id();
            $table->string('qr', 120)->index();
            $table->unsignedBigInteger('defecto_id')->nullable()->index();

            // Id de la fila en produccion.historicocontrolcalidad (JITMS).
            $table->bigInteger('jitms_id')->index();

            // INSERT = la fila la creamos nosotros -> se borra al validar.
            // MARCA  = fila ajena a la que solo le pisamos el Mensaje -> se restaura.
            // NG     = NG que el PLC genero por nuestro OK -> se borra al validar.
            $table->string('accion', 10);

            $table->string('estado_fila', 10)->nullable();
            $table->string('mensaje_original', 255)->nullable();
            $table->string('usuario_fila', 100)->nullable();

            $table->timestamp('revertido_at')->nullable();
            $table->timestamps();

            $table->index(['qr', 'revertido_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eol_interlock_marcas');
    }
};
