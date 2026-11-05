<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paneles NG del puesto de produccion (Produccion 3 / 4): el panel vuelve con un
 * defecto, se escanea y queda NG; se escanea el panel nuevo y se le reimprime la
 * etiqueta con la secuencia del NG. Una fila por panel NG.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eol_door_produccion_ng')) {
            return;
        }
        Schema::create('eol_door_produccion_ng', function (Blueprint $table) {
            $table->id();
            $table->string('qr_ng', 120)->index();          // QR del panel con defecto
            $table->string('id_ng', 30)->nullable();        // id del panel (1er campo del QR)
            $table->string('secuencia', 30)->nullable();     // secuencia que pasa al panel nuevo
            $table->string('sd', 10)->nullable();
            $table->string('posicion', 5)->nullable();
            $table->string('mano', 5)->nullable();
            $table->string('puesto', 40)->nullable();       // PRODUCCION 4 / PRODUCCION 3
            $table->string('estado', 20)->default('NG');     // NG -> REETIQUETADO
            $table->string('qr_nuevo', 120)->nullable();     // QR original del panel de reemplazo
            $table->string('qr_impreso', 120)->nullable();   // QR de la etiqueta reimpresa
            $table->string('impresora', 40)->nullable();
            $table->dateTime('impreso_at')->nullable();
            $table->unsignedInteger('impresiones')->default(0);
            $table->string('usuario_nombre', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eol_door_produccion_ng');
    }
};
