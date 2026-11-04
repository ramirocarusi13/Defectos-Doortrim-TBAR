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
        Schema::create('wms_deposito_layouts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('deposito_id')->nullable();
            $table->foreign('deposito_id')->references('id')->on('depositos');

            $table->string('calles')->nullable();
            $table->string('racks')->nullable();
            $table->string('posiciones')->nullable();
            $table->string('niveles')->nullable();

            $table->boolean('calle_letra')->default(false)->nullable();
            $table->boolean('rack_letra')->default(true);
            $table->boolean('posicion_letra')->default(true);
            $table->boolean('nivel_letra')->default(false);

            $table->string('formato_codigo')->default('R-P-N');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('deposito_layouts');
    }
};
