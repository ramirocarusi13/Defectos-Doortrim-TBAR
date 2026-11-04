<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('user_operacion_lineas', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->unsignedBigInteger('operacion_id')->nullable();
            $table->foreign('operacion_id')->references('id')->on('linea_operaciones');

            $table->unsignedBigInteger('autorizante')->nullable();
            $table->foreign('autorizante')->references('id')->on('users');

            $table->timestamps();
        });
    }


    public function down() {
        Schema::dropIfExists('user_operacion_lineas');
    }
};
