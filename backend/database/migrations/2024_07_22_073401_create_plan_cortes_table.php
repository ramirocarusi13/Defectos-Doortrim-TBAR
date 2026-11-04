<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up() {
        Schema::create('plan_cortes', function (Blueprint $table) {
            $table->id();
            $table->uuid("operacion")->nullable();
            $table->string("fecha");
            $table->string("turno")->nullable();
            $table->integer('orden')->default(0)->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('plan_cortes');
    }
};
