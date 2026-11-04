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
        Schema::create('linea_operaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('linea')->nullable();
            $table->string('nivel')->nullable();
            $table->integer('orden')->nullable();
            $table->boolean('habilitado')->default(true)->nullable();
            $table->boolean('sublinea')->default(false)->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('linea_operaciones');
    }
};
