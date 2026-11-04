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
        Schema::create('modelos_hora_horas', function (Blueprint $table) {
            $table->id();
            $table->string('linea', 5)->nullable();
            $table->string('turno')->nullable();
            $table->string('nombre_turno')->nullable();
            $table->date('fecha')->nullable();
            $table->string('modelo')->nullable();
            $table->integer('plan')->nullable();
            $table->integer('real')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('modelos_hora_horas');
    }
};
