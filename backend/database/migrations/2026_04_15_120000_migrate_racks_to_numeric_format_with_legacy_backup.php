<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up() {
        // NO-OP intencional.
        // Esta migración quedó desactivada para evitar renombrados/migraciones destructivas
        // de posiciones históricas de RACKS.
    }

    public function down() {
        // NO-OP intencional.
    }
};

