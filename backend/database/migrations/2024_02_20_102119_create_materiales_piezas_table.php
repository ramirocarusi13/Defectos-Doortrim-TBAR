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
        Schema::create('materiales_piezas', function (Blueprint $table) {
            $table->id();

            $table->string("codigo_interno")->nullable();
            $table->string("codigo")->nullable();
            $table->string("nombre")->nullable();
            $table->string("color")->nullable();

            $table->integer("minimo")->nullable();
            $table->integer("maximo")->nullable();
            $table->integer("pto_pedido")->nullable();

            $table->string("codigo_proveedor")->nullable();
            $table->float("ancho")->default(0);
            $table->string("um")->nullable();
            $table->string("modelo", 50)->nullable();
            $table->float("densidad")->default(0);
            $table->integer("orden")->nullable();

            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->foreign('proveedor_id')->references('id')->on('proveedores');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('materiales_piezas');
    }
};
