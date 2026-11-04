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
        Schema::create('tienda_pedidos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->unsignedBigInteger('user_autorizante_id')->nullable();
            $table->foreign('user_autorizante_id')->references('id')->on('users');

            $table->boolean('pendiente')->default(true);

            $table->unsignedBigInteger('kanban_id')->nullable();
            $table->foreign('kanban_id')->references('id')->on('kanbans');

            $table->unsignedBigInteger('falla_id')->nullable();
            $table->foreign('falla_id')->references('id')->on('codigo_fallas');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('tienda_pedidos');
    }
};
