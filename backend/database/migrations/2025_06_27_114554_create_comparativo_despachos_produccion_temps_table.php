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
        Schema::create('comparativo_despachos_produccion_temps', function (Blueprint $table) {
            $table->id();

            $table->date('fecha')->nullable();
            $table->string('titulo')->nullable();
            $table->integer('vehiculos')->default(0);
            $table->integer('cuero')->default(0);
            $table->integer('bcab')->default(0);
            $table->integer('tup')->default(0);
            $table->integer('m7')->default(0);
            $table->integer('m9')->default(0);
            $table->integer('prod_vehiculos')->default(0);
            $table->integer('prod_cuero')->default(0);
            $table->integer('prod_bcab')->default(0);
            $table->integer('prod_tup')->default(0);
            $table->integer('prod_m7')->default(0);
            $table->integer('prod_m9')->default(0);
            $table->integer('dif_vehiculos')->default(0);
            $table->integer('dif_cuero')->default(0);
            $table->integer('dif_bcab')->default(0);
            $table->integer('dif_tup')->default(0);
            $table->integer('dif_m7')->default(0);
            $table->integer('dif_m9')->default(0);
            $table->integer('orden')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('comparativo_despachos_produccion_temps');
    }
};
