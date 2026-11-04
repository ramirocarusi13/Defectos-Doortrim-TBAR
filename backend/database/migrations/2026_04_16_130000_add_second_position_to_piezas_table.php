<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('piezas', function (Blueprint $table) {
            if (!Schema::hasColumn('piezas', 'p2_left')) {
                $table->double('p2_left')->nullable();
            }

            if (!Schema::hasColumn('piezas', 'p2_top')) {
                $table->double('p2_top')->nullable();
            }

            if (!Schema::hasColumn('piezas', 'p2_width')) {
                $table->double('p2_width')->nullable();
            }

            if (!Schema::hasColumn('piezas', 'p2_height')) {
                $table->double('p2_height')->nullable();
            }
        });
    }

    public function down() {
        Schema::table('piezas', function (Blueprint $table) {
            foreach (['p2_left', 'p2_top', 'p2_width', 'p2_height'] as $column) {
                if (Schema::hasColumn('piezas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
