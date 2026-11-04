<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('ubicaciones', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('deposito_id')->nullable();
            $table->foreign('deposito_id')->references('id')->on('depositos');

            $table->string('nombre');
            $table->integer('orden')->nullable()->default(0);
            $table->integer('capacidad')->nullable()->default(1);
            $table->boolean('habilitada')->default(true);

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('ubicaciones');
    }
};
