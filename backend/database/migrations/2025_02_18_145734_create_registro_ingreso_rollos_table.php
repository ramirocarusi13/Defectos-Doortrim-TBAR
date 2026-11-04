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
        Schema::create('registro_ingreso_rollos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->foreign('proveedor_id')->references('id')->on('proveedores');

            $table->unsignedBigInteger('material_id')->nullable();
            $table->foreign('material_id')->references('id')->on('materiales_piezas');

            $table->string('codigo_escaneado')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->string('codigo_sar')->nullable();
            $table->string('lote')->nullable();
            $table->float('peso_bruto')->nullable();
            $table->float('peso_liquido')->nullable();
            $table->float('mt_bruto')->nullable();
            $table->float('mt_liquido')->nullable();
            $table->string('otros')->nullable();
            $table->uuid('operacion')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('registro_ingreso_rollos');
    }
};
