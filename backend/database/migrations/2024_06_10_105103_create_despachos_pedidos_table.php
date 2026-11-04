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
        Schema::create('despachos_pedidos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('despacho_id')->nullable();
            $table->foreign('despacho_id')->references('id')->on('despachos');

            $table->integer('pedido')->nullable();
            $table->integer('pendiente')->nullable();
            $table->integer('produccion')->nullable();
            $table->integer('desbalanceo')->nullable();
            $table->integer('cl2')->nullable();

            $table->string('modelo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('despachos_pedidos');
    }
};
