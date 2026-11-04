<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('kanbans_reemplazos', function (Blueprint $table) {
            $table->dateTime('fecha_plan')->nullable()->after('fecha_ingreso');
        });
    }

    public function down() {
        Schema::table('kanbans_reemplazos', function (Blueprint $table) {
            $table->dropColumn('fecha_plan');
        });
    }
};
