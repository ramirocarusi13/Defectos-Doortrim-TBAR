<?php

use App\Http\Depositos;
use App\Http\WmsUnidades;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const RACK_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    private const ROW_START = 1;
    private const ROW_END = 34;
    private const LEVEL_START = 0;
    private const LEVEL_END = 4;

    public function up() {
        DB::transaction(function () {
            $requiredNames = $this->buildRequiredNames();
            $this->createMissingRackPositions($requiredNames);
            $this->ensureKanbanCapacityConfig($requiredNames);
        });
    }

    public function down() {
        // No-op intencional para evitar eliminar ubicaciones productivas.
    }

    private function createMissingRackPositions(array $requiredNames): void {
        $existingNames = DB::table('ubicaciones')
            ->where('deposito_id', Depositos::RACKS)
            ->whereIn('nombre', $requiredNames)
            ->pluck('nombre')
            ->toArray();

        $existingMap = array_fill_keys($existingNames, true);

        $maxOrder = DB::table('ubicaciones')
            ->where('deposito_id', Depositos::RACKS)
            ->max('orden');

        $order = is_null($maxOrder) ? 0 : intval($maxOrder);
        $now = now();
        $insertBatch = [];

        foreach ($requiredNames as $name) {
            if (array_key_exists($name, $existingMap)) {
                continue;
            }

            $order++;
            $insertBatch[] = [
                'deposito_id' => Depositos::RACKS,
                'orden' => $order,
                'capacidad' => 1,
                'nombre' => $name,
                'habilitada' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($insertBatch) >= 200) {
                DB::table('ubicaciones')->insert($insertBatch);
                $insertBatch = [];
            }
        }

        if (!empty($insertBatch)) {
            DB::table('ubicaciones')->insert($insertBatch);
        }
    }

    private function ensureKanbanCapacityConfig(array $requiredNames): void {
        $defaultCapacity = DB::table('wms_unidades_ubicaciones as uu')
            ->join('ubicaciones as u', 'u.id', '=', 'uu.ubicacion_id')
            ->where('u.deposito_id', Depositos::RACKS)
            ->where('uu.unidad_id', WmsUnidades::KANBAN)
            ->orderBy('uu.id')
            ->value('uu.capacidad');

        $defaultCapacity = intval($defaultCapacity);
        if ($defaultCapacity <= 0) {
            $defaultCapacity = 1;
        }

        $now = now();

        DB::table('ubicaciones')
            ->select('id')
            ->where('deposito_id', Depositos::RACKS)
            ->whereIn('nombre', $requiredNames)
            ->orderBy('id')
            ->chunkById(300, function ($rows) use ($defaultCapacity, $now) {
                $ids = [];
                foreach ($rows as $row) {
                    $ids[] = intval($row->id);
                }

                if (empty($ids)) {
                    return;
                }

                $configuredIds = DB::table('wms_unidades_ubicaciones')
                    ->where('unidad_id', WmsUnidades::KANBAN)
                    ->whereIn('ubicacion_id', $ids)
                    ->pluck('ubicacion_id')
                    ->map(function ($value) {
                        return intval($value);
                    })
                    ->toArray();

                $configuredMap = array_fill_keys($configuredIds, true);
                $insertBatch = [];

                foreach ($ids as $ubicacionId) {
                    if (array_key_exists($ubicacionId, $configuredMap)) {
                        continue;
                    }

                    $insertBatch[] = [
                        'ubicacion_id' => $ubicacionId,
                        'unidad_id' => WmsUnidades::KANBAN,
                        'capacidad' => $defaultCapacity,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($insertBatch) >= 300) {
                        DB::table('wms_unidades_ubicaciones')->insert($insertBatch);
                        $insertBatch = [];
                    }
                }

                if (!empty($insertBatch)) {
                    DB::table('wms_unidades_ubicaciones')->insert($insertBatch);
                }
            }, 'id');
    }

    private function buildRequiredNames(): array {
        $names = [];

        foreach (self::RACK_LETTERS as $rackLetter) {
            for ($row = self::ROW_START; $row <= self::ROW_END; $row++) {
                for ($level = self::LEVEL_START; $level <= self::LEVEL_END; $level++) {
                    $names[] = $rackLetter . '-' . $row . '-' . $level;
                }
            }
        }

        return $names;
    }
};
