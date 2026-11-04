<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('temp_modelos_produccion_hora_horas', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->nullable();
            $table->float('real')->default(0);
            $table->string('turno', 1)->nullable();
            $table->string('fondo', 50)->nullable();
            $table->string('fondo_actual', 50)->nullable();
            $table->date('fecha')->nullable();
            $table->string('tipo',50)->nullable();
            $table->float('plan')->default(0);
            $table->float('actual')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('temp_modelos_produccion_hora_horas');
    }
};
