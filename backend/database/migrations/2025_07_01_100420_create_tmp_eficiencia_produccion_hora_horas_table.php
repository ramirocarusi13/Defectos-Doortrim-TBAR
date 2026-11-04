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
        Schema::create('tmp_eficiencia_produccion_hora_horas', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->nullable();
            $table->integer('volumen')->default(0);
            $table->integer('real')->default(0);
            $table->string('tipo')->nullable();
            $table->string('turno', 1)->nullable();
            $table->date('fecha')->nullable();
            $table->float('eficiencia')->default(0);
            $table->float('oa')->default(0);


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('tmp_eficiencia_produccion_hora_horas');
    }
};
