<?php

namespace App\Console\Commands;

use App\Models\FactoryConfig;
use Illuminate\Console\Command;

/**
 * Marca inactivas (o activas) las vistas de banco trasero / tumble en la
 * factory-config, para que NO aparezcan en el EOL (solo se inspecciona el
 * asiento delantero).
 *
 * Al ser un cambio de CONFIG (no de codigo), las tablets lo toman solas por el
 * polling de /config -> NO hay que reinstalar el APK en las 40 tablets.
 *
 *   php artisan eol:tumble           (simula, no guarda)
 *   php artisan eol:tumble --apply   (aplica: oculta tumble)
 *   php artisan eol:tumble --apply --mostrar   (revierte: vuelve a mostrar)
 */
class OcultarVistasTumble extends Command
{
    protected $signature = 'eol:tumble {--apply : Guarda los cambios} {--mostrar : En vez de ocultar, vuelve a mostrar}';

    protected $description = 'Oculta/muestra las vistas de tumble (banco trasero) en la config, sin reinstalar el APK';

    private const RX = '/tumble|banco|bench|40\s*%|60\s*%/i';

    public function handle(): int
    {
        $ocultar = ! $this->option('mostrar');
        $apply   = (bool) $this->option('apply');

        $row = FactoryConfig::query()->where('key', 'active')->first();
        if (! $row) {
            $this->error('No hay factory_config activa.');
            return self::FAILURE;
        }

        $data = $row->data;
        $nuevo = $ocultar ? false : true;
        $tocadas = 0;
        foreach ($data['modelImages'] ?? [] as $k => $img) {
            $nombre = $img['viewName'] ?? $img['name'] ?? '';
            if (preg_match(self::RX, $nombre)) {
                if (($img['active'] ?? true) !== $nuevo) {
                    $tocadas++;
                }
                $data['modelImages'][$k]['active'] = $nuevo;
            }
        }

        $accion = $ocultar ? 'OCULTAR' : 'MOSTRAR';
        $this->info(($apply ? "[$accion aplicado] " : "[$accion simulacion, usa --apply] ")
            . "vistas de tumble afectadas: $tocadas");

        if ($apply) {
            // Asignar y save() refresca updated_at -> sube la version que consultan
            // las tablets. (updateOrCreate no detectaba el cambio del array anidado.)
            $row->data = $data;
            $row->save();
            $this->info('Config guardada. Las tablets lo tomaran en el proximo poll (~10s), sin reinstalar.');
        }

        return self::SUCCESS;
    }
}
