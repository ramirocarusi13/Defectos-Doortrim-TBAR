<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('log_plan_costuras', function (Blueprint $table) {
            $table->id();
            $table->string("modelo")->nullable();
            $table->integer("cortes_requeridos")->nullable();
            $table->integer("cant_buffer")->nullable();
            $table->integer("cant_assy")->nullable();
            $table->integer("cant_corte")->nullable();
            $table->integer('cortes_ejecutados')->nullable();
            $table->integer('orden')->nullable();
            $table->date('fecha')->nullable();
            $table->timestamps();
        });
    }


    public function down() {
        Schema::dropIfExists('log_plan_costuras');
    }
};
