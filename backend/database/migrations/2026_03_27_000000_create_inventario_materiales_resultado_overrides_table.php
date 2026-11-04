<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventario_materiales_resultado_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_id');
            $table->date('fecha');
            $table->decimal('subtotal_kg', 12, 3)->nullable();
            $table->decimal('densidad', 12, 3)->nullable();
            $table->decimal('total_m2', 12, 3)->nullable();
            $table->decimal('ancho', 12, 3)->nullable();
            $table->decimal('total_ml', 12, 3)->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['material_id', 'fecha'], 'uq_inv_mat_res_override_material_fecha');
            $table->foreign('material_id')->references('id')->on('materiales_piezas');
            $table->foreign('updated_by_user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventario_materiales_resultado_overrides');
    }
};
