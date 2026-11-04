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
        Schema::create('tmp_plan_lineas', function (Blueprint $table) {
            $table->id();

            $table->integer('linea_id')->nullable();
            $table->integer('cantidad')->nullable();
            $table->date('fecha')->nullable();
            $table->integer('orden')->nullable();
            $table->integer('diferencia')->nullable();
            $table->integer('diferencia_acumulada')->nullable();
            $table->float('andon_hora')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('tmp_plan_lineas');
    }
};
