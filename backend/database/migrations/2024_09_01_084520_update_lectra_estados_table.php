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
        Schema::table('lectra_estados', function (Blueprint $table) {
            $table->dateTime('fin_estimado')->nullable();
            $table->string('demora')->nullable();
            $table->integer('id_reanudar')->nullable();
            $table->dateTime('fecha_abastecido')->nullable();

            $table->dateTime('inicio_plan')->nullable();
            $table->dateTime('fin_plan')->nullable();

            $table->boolean('es_plan_anterior')->nullable()->default(false);
        });
    }

    public function down() {
        //
    }
};
