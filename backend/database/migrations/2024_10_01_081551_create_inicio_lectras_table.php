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
        Schema::create('inicio_lectras', function (Blueprint $table) {
            $table->id();
            $table->string("lectra", 1)->nullable();
            $table->date("fecha")->nullable();
            $table->string("hora")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('inicio_lectras');
    }
};
