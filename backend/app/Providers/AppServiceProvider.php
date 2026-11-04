<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {
        // Passport 13 dejo de registrar por defecto la "JSON API" (/oauth/clients,
        // /oauth/tokens, /oauth/personal-access-tokens, /oauth/scopes) que Passport 11
        // exponia. Se mantiene para no cambiar la superficie de rutas. Va en register()
        // porque PassportServiceProvider registra sus rutas en su boot(), antes que este provider.
        Passport::$registersJsonApiRoutes = true;
        // Passport 13 agrega el device code grant (rutas /oauth/device* y tabla
        // oauth_device_codes, que no existe en produccion). No se usa: se deshabilita.
        Passport::$deviceCodeGrantEnabled = false;

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        // Passport 13: los clientes existentes en oauth_clients tienen ID entero
        // (Passport 11). Sin esto Passport 13 asume UUIDs para los clientes nuevos.
        Passport::$clientUuids = false;

        // Ver App\Models\Passport\Client: evita que los clientes personal access / password
        // (esquema heredado) se consideren client_credentials y bloqueen a los usuarios cuyo
        // id coincide con el id del cliente.
        Passport::useClientModel(\App\Models\Passport\Client::class);

        // Passport 12+ deshabilita el password grant por defecto; se mantiene
        // habilitado para no romper clientes que pidan tokens via /oauth/token.
        Passport::enablePasswordGrant();

        $this->trabarJitmsSoloLectura();
    }

    /**
     * JITMS (conexion sqlsrvsar) es la base del MES de produccion. Con
     * JITMS_SOLO_LECTURA=true (deploy de door trims) cualquier sentencia que no
     * sea una lectura se rechaza ANTES de mandarla al servidor: ni el interlock,
     * ni un stored procedure, ni un modelo Sar\* pueden escribir.
     */
    private function trabarJitmsSoloLectura(): void
    {
        // TB-L (base de la app de camaras de door trims) es SIEMPRE de solo lectura.
        $conexiones = ['tbl'];
        if (config('eol.jitms_solo_lectura')) {
            $conexiones[] = 'sqlsrvsar';
        }

        foreach ($conexiones as $conexion) {
            \Illuminate\Support\Facades\DB::connection($conexion)->beforeExecuting(function ($query) use ($conexion) {
                if (self::esLectura((string) $query)) {
                    return;
                }
                \Illuminate\Support\Facades\Log::critical("{$conexion} solo lectura: sentencia bloqueada", ['sql' => mb_substr((string) $query, 0, 300)]);
                throw new \RuntimeException("La base {$conexion} está en solo lectura: se bloqueó una escritura.");
            });
        }
    }

    /** Solo SELECT / WITH ... SELECT, sin ninguna palabra que modifique datos. */
    public static function esLectura(string $sql): bool
    {
        // Sin comentarios ni literales: las palabras se buscan solo en el SQL.
        $limpio = preg_replace(["~/\*.*?\*/~s", '~--[^\n]*~', "~'(?:[^']|'')*'~"], ' ', $sql);
        $limpio = strtolower(trim($limpio));
        if (!preg_match('~^(select|with)\b~', $limpio)) {
            return false;
        }
        return !preg_match('~\b(insert|update|delete|merge|into|exec|execute|drop|alter|create|truncate|grant|revoke|deny|dbcc|backup|restore)\b~', $limpio);
    }
}
