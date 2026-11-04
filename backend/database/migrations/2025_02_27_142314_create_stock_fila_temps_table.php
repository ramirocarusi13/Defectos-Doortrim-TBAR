<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('stock_fila_temps', function (Blueprint $table) {
            $table->id();

            $table->string('fila', 50)->nullable();
            $table->integer('stock')->nullable();
            $table->date('fecha')->nullable();
            $table->string('modelo', 10)->nullable();
            $table->float('consumo')->nullable();
            $table->timestamps();
        });
    }


    public function down() {
        Schema::dropIfExists('stock_fila_temps');
    }
};
