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
        Schema::create('lineas', function (Blueprint $table) {
            $table->id();
            $table->string("codigo", 10);
            $table->integer("capacidad")->default(3);
            $table->integer("posicion")->default(1);
            $table->integer("columnas")->default(3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('lineas');
    }
};
