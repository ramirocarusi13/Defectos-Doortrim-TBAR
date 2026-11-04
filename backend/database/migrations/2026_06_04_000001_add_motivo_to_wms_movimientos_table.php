<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('wms_movimientos', function (Blueprint $table) {
            if (!Schema::hasColumn('wms_movimientos', 'motivo')) {
                $table->string('motivo', 250)->nullable();
            }
        });
    }

    public function down() {
        Schema::table('wms_movimientos', function (Blueprint $table) {
            if (Schema::hasColumn('wms_movimientos', 'motivo')) {
                $table->dropColumn('motivo');
            }
        });
    }
};
