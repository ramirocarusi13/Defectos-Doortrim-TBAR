<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TurnosSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $start = Carbon::create(2026, 6, 1)->startOfDay();
        $end = Carbon::create(2026, 12, 31)->startOfDay();

        $rows = [];

        foreach (CarbonPeriod::create($start, $end) as $date) {
            if (!$date->isWeekday()) {
                continue;
            }

            $dayIndex = (int) $start->diffInDays($date, true);
            $weekIndex = intdiv($dayIndex, 7);
            $isFlipped = ($weekIndex % 2) === 1;

            $turnoManiana = $isFlipped ? 'B' : 'A';
            $turnoTarde = $isFlipped ? 'A' : 'B';

            $rows[] = [
                'turno' => 'M',
                'turno_nombre' => $turnoManiana,
                'fecha' => $date->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $rows[] = [
                'turno' => 'T',
                'turno_nombre' => $turnoTarde,
                'fecha' => $date->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('turnos')->insert($rows);
        }
    }
}
