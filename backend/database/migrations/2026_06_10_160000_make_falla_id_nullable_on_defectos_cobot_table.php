<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up() {
        DB::statement('ALTER TABLE defectos_cobot ALTER COLUMN falla_id bigint NULL');
    }

    public function down() {
        DB::statement('ALTER TABLE defectos_cobot ALTER COLUMN falla_id bigint NOT NULL');
    }
};
