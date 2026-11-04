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
            $table->integer("rrhh_ausentismo")->nullable();
            $table->integer("rrhh_rotacion")->nullable();
            $table->integer("pr_piqueo")->nullable();
            $table->integer("pr_habilidad")->nullable();
            $table->integer("pr_reposicion")->nullable();
            $table->integer("pr_retendido_nylon")->nullable();
            $table->integer("pr_falta_tendido")->nullable();
            $table->integer("kz_setup")->nullable();
            $table->integer("qc_defectos_proveedor")->nullable();
            $table->integer("qc_problema_calidad")->nullable();
            $table->integer("pc_falta_carros")->nullable();
            $table->integer("pc_falta_material")->nullable();
            $table->integer("mtto_perdida_destino")->nullable();
            $table->integer("mtto_cambio_cuchilla")->nullable();
            $table->integer("mtto_falla_maquina")->nullable();
            $table->boolean('abastecido')->nullable()->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void 
     */
    public function down() {
        //
    }
};
