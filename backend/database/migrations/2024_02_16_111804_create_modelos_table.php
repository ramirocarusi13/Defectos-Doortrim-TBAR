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
        Schema::create('modelos', function (Blueprint $table) {
            $table->id();

            $table->string("hr")->nullable();
            $table->string("ctrhr")->nullable();
            $table->string("ar")->nullable();
            $table->string("descripcion")->nullable();
            $table->string("codigo");
            $table->string("nombre");
            $table->integer("cantidad")->default(10);
            $table->boolean("airbag")->default(false);

            $table->unsignedBigInteger('material_id')->nullable();
            $table->foreign('material_id')->references('id')->on('materiales');

            $table->unsignedBigInteger('color_id')->nullable();
            $table->foreign('color_id')->references('id')->on('colores');

            $table->unsignedBigInteger('fila_id')->nullable();
            $table->foreign('fila_id')->references('id')->on('filas');

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
        Schema::dropIfExists('modelos');
    }
};
