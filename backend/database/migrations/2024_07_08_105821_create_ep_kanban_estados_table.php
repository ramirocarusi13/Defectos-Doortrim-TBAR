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
        Schema::create('ep_kanban_estados', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_impresion')->nullable();
            $table->foreign('user_impresion')->references('id')->on('users');

            $table->unsignedBigInteger('user_costura')->nullable();
            $table->foreign('user_costura')->references('id')->on('users');

            $table->unsignedBigInteger('user_qc')->nullable();
            $table->foreign('user_qc')->references('id')->on('users');

            $table->string('kanban')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('ep_kanban_estados');
    }
};
