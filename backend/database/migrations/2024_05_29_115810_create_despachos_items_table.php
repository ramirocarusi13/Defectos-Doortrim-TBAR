<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('despachos_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('despacho_id')->nullable();
            $table->foreign('despacho_id')->references('id')->on('despachos');

            $table->boolean('pickeado')->default(false);
            $table->boolean('desbalanceo')->default(false);
            $table->boolean('cl2')->default(false);
            $table->string('kanban')->nullable();
            $table->string('posicion')->nullable();
            $table->string('modelo');
            $table->integer('produccion')->nullable();

            $table->unsignedBigInteger('deposito_id')->nullable();
            $table->foreign('deposito_id')->references('id')->on('depositos');

            $table->boolean('cuarentena')->default(false)->nullable();
            $table->dateTime('fecha_calidad')->nullable();

            $table->unsignedBigInteger('user_id_calidad')->nullable();
            $table->foreign('user_id_calidad')->references('id')->on('users');

            $table->string('estado_calidad')->nullable();
            $table->string('kanban_envio')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->unsignedBigInteger('user_id2')->nullable();
            $table->foreign('user_id2')->references('id')->on('users');

            $table->timestamps();
        });
    }


    public function down() {
        Schema::dropIfExists('despachos_items');
    }
};
