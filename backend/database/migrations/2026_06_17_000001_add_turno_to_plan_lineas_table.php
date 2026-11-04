<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasColumn('plan_lineas', 'turno')) {
            Schema::table('plan_lineas', function (Blueprint $table) {
                $table->string('turno', 1)->nullable();
            });
        }
    }

    public function down() {
        if (Schema::hasColumn('plan_lineas', 'turno')) {
            Schema::table('plan_lineas', function (Blueprint $table) {
                $table->dropColumn('turno');
            });
        }
    }
};
