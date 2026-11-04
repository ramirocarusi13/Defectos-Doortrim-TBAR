<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('hora_hora_produccions', function (Blueprint $table) {
            $table->id();

            $table->date('fecha')->nullable();
            $table->string('turno')->nullable();
            $table->string('turno_nombre')->nullable();
            $table->string('linea')->nullable();
            $table->string('intervalo')->nullable();
            $table->integer('plan')->nullable();
            $table->integer('plan_acumulado')->nullable();
            $table->string('modelo')->nullable();
            $table->integer('real')->nullable();
            $table->integer('acumulado')->nullable();
            $table->integer('diferencia')->nullable();
            $table->integer('diferencia_acumulado')->nullable();
            $table->integer('piezas_reparadas')->nullable();
            $table->integer('piezas_scrap')->nullable();
            $table->timestamps();
        });

        Schema::create('produccion_paradas', function (Blueprint $table) {
            $table->id();

            $table->date('fecha')->nullable();
            $table->string('turno')->nullable();
            $table->string('turno_nombre')->nullable();
            $table->string('intervalo')->nullable();
            $table->string('area')->nullable();
            $table->string('shop')->nullable();
            $table->integer('minutos')->nullable();
            $table->string('categoria')->nullable();
            $table->string('grupo')->nullable();
            $table->string('contramedida')->nullable();

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('hora_hora_produccions');
        Schema::dropIfExists('produccion_paradas');
    }
};
