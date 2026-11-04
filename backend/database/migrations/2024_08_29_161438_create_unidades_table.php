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
        Schema::create('wms_unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->float('volumen')->nullable()->default(0);
            $table->boolean('cantidad_unica')->default(false)->nullable();
            $table->boolean('es_kanban')->default(false)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('wms_unidades');
    }
};
