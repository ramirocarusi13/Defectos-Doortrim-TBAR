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
        Schema::create('control_straps', function (Blueprint $table) {
            $table->id();

            $table->string("codigo_barra")->nullable();
            $table->string("posicion")->nullable();

            $table->integer("cantidad")->nullable();

            $table->string("modelo")->nullable();
            $table->string("lote")->nullable();
            $table->string("part_number")->nullable();
            $table->string("kanban")->nullable();


            $table->unsignedBigInteger('user_id_in')->nullable();
            $table->foreign('user_id_in')->references('id')->on('users');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->unsignedBigInteger('user_id_pedido')->nullable();
            $table->foreign('user_id_pedido')->references('id')->on('users');

            $table->dateTime("fecha_entrega")->nullable();
            $table->integer('fifo')->nullable();

            $table->boolean("entregado")->nullable()->default(false);
            $table->boolean("remanente")->nullable()->default(false);
            $table->boolean("anulado")->nullable()->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('control_straps');
    }
};
