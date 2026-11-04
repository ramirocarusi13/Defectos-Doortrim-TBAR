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
        Schema::create('pc_plan_produccions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->foreign('modelo_id')->references('id')->on('modelos');

            $table->unsignedBigInteger('kanban_id')->nullable();
            $table->foreign('kanban_id')->references('id')->on('kanbans');

            $table->dateTime('vigencia_desde')->nullable();
            $table->dateTime('vigencia_hasta')->nullable();

            $table->integer('consumo')->default(0);
            $table->integer('orden')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('pc_plan_produccions');
    }
};
