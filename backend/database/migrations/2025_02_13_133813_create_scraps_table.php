<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('scraps', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->unsignedBigInteger('material_id')->nullable();
            $table->foreign('material_id')->references('id')->on('materiales_piezas');

            $table->float('kg')->nullable();
            $table->float('precio')->nullable();
            $table->string('motivo')->nullable();

            $table->unsignedBigInteger('linea_id')->nullable();
            $table->foreign('linea_id')->references('id')->on('lineas');

            $table->string('tipo_linea', 1)->nullable();
            $table->string('sector')->nullable();

            $table->unsignedBigInteger('defecto_id')->nullable();
            $table->foreign('defecto_id')->references('id')->on('fallas_informadas');

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('scraps');
    }
};
