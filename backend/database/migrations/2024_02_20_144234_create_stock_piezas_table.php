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
        Schema::create('stock_piezas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pieza_id')->nullable();
            $table->foreign('pieza_id')->references('id')->on('piezas');

            $table->integer("cantidad")->nullable();
            $table->date("fecha");

            $table->string("motivo")->nullable();

            $table->unsignedBigInteger('kanban_id')->nullable();
            $table->foreign('kanban_id')->references('id')->on('kanbans');

            // $table->unsignedBigInteger('kanban_reemplazo_id')->nullable();
            // $table->foreign('kanban_reemplazo_id')->references('id')->on('kanbans_reemplazo');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->unsignedBigInteger('deposito_id')->nullable();
            $table->foreign('deposito_id')->references('id')->on('depositos');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('stock_piezas');
    }
};
