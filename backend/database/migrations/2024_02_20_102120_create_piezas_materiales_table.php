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
        Schema::create('piezas_materiales', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('pieza_id')->nullable();
            $table->foreign('pieza_id')->references('id')->on('piezas');


            $table->unsignedBigInteger('material_pieza_id')->nullable();
            $table->foreign('material_pieza_id')->references('id')->on('materiales_piezas');

            $table->integer("secuencia")->default(1);
            $table->string("dado")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('piezas_materiales');
    }
};
