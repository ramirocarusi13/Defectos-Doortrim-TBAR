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
        Schema::create('strap_eventos', function (Blueprint $table) {
            $table->id();

            $table->string("evento")->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->unsignedBigInteger('autoriza_user_id')->nullable();
            $table->foreign('autoriza_user_id')->references('id')->on('users');

            $table->unsignedBigInteger('user_solicitante')->nullable();
            $table->foreign('user_solicitante')->references('id')->on('users');

            $table->string("codigo_escaneado")->nullable();
            $table->string("kanban")->nullable();
            $table->string("posicion")->nullable();
            $table->string("modelo")->nullable();
            $table->string("lote")->nullable();
            $table->string("linea")->nullable();
            $table->integer("cantidad")->nullable();
            $table->integer("tipo")->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('strap_eventos');
    }
};
