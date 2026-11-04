<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('despachos', function (Blueprint $table) {
            $table->id();

            // $table->dateTime('fecha')->nullable();
            $table->boolean('pendiente')->default(true);

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->string('run')->nullable();
            $table->dateTime('fecha_salida_camion')->nullable();
            $table->date('fecha')->nullable();

            $table->timestamps();
        });
    }


    public function down() {
        Schema::dropIfExists('despachos');
    }
};
