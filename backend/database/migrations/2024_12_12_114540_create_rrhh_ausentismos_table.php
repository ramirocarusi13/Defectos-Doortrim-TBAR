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
        Schema::create('rrhh_ausentismos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->nullable();
            $table->string('turno')->nullable();

            $table->unsignedBigInteger('causa_id')->nullable();
            $table->foreign('causa_id')->references('id')->on('rrhh_causa_ausentismos');

            $table->unsignedBigInteger('area_id')->nullable();
            $table->foreign('area_id')->references('id')->on('rrhh_areas');

            $table->unsignedBigInteger('detalle_id')->nullable();
            $table->foreign('detalle_id')->references('id')->on('rrhh_detalle_ausentismos');

            $table->date('inicio')->nullable();
            $table->date('fecha_probable')->nullable();
            $table->date('fecha_real')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->string('observaciones')->nullable();
            $table->boolean('activo')->default(true);


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('rrhh_ausentismos');
    }
};
