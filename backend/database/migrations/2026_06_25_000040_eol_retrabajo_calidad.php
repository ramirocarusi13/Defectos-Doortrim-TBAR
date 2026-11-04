<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campos de validacion de calidad + contador de reaperturas en el defecto.
        Schema::table('eol_defectos', function (Blueprint $table) {
            if (!Schema::hasColumn('eol_defectos', 'validado_por')) {
                $table->unsignedBigInteger('validado_por')->nullable();
            }
            if (!Schema::hasColumn('eol_defectos', 'validado_por_nombre')) {
                $table->string('validado_por_nombre', 150)->nullable();
            }
            if (!Schema::hasColumn('eol_defectos', 'validado_at')) {
                $table->dateTime('validado_at')->nullable();
            }
            if (!Schema::hasColumn('eol_defectos', 'reaperturas')) {
                $table->integer('reaperturas')->default(0);
            }
        });

        // Historial de eventos: trazabilidad de cada instancia del defecto.
        if (!Schema::hasTable('eol_defecto_eventos')) {
            Schema::create('eol_defecto_eventos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('eol_defecto_id')->index();
                $table->string('qr', 120)->nullable();
                $table->string('evento', 30); // ABIERTO | RETRABAJADO | VALIDADO | RECHAZADO
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_nombre', 150)->nullable();
                $table->string('departamento', 40)->nullable(); // CALIDAD | PRODUCCION
                $table->string('turno', 20)->nullable();
                $table->text('observaciones')->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eol_defecto_eventos');
        Schema::table('eol_defectos', function (Blueprint $table) {
            $table->dropColumn(['validado_por', 'validado_por_nombre', 'validado_at', 'reaperturas']);
        });
    }
};
