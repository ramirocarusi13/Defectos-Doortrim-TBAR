<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('log_abastecimientos', function (Blueprint $table) {
            $table->id();
            $table->string('modelo')->nullable();
            $table->string('dado')->nullable();
            $table->string('accion')->nullable();
            $table->integer('lectra')->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('log_abastecimientos');
    }
};
