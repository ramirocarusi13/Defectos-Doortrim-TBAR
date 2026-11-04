<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasColumn('defecto_cobot_parte', 'x')) {
            Schema::table('defecto_cobot_parte', function (Blueprint $table) {
                $table->float('x')->nullable()->after('parte_id');
                $table->float('y')->nullable()->after('x');
            });
        }
    }

    public function down() {
        if (Schema::hasColumn('defecto_cobot_parte', 'x')) {
            Schema::table('defecto_cobot_parte', function (Blueprint $table) {
                $table->dropColumn(['x', 'y']);
            });
        }
    }
};
