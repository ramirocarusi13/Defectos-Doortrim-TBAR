<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use phpseclib4\Crypt\RSA;
use Tests\TestCase;

/**
 * Verifica que Passport 13 funcione con el esquema de tablas OAuth creado por Passport 11
 * (el que existe en produccion), sin migrar oauth_clients.
 */
class PassportLegacySchemaTest extends TestCase {
    private static ?string $keyDir = null;

    protected function setUp(): void {
        parent::setUp();

        // Passport usa la APP_KEY para cifrar; si el entorno no tiene .env se usa una de prueba.
        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        }

        if (self::$keyDir === null) {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'passport-test-keys-' . getmypid();
            @mkdir($dir);
            $key = RSA::createKey(2048);
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'oauth-private.key', (string) $key);
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'oauth-public.key', (string) $key->getPublicKey());
            if (! windows_os()) {
                chmod($dir . DIRECTORY_SEPARATOR . 'oauth-private.key', 0600);
                chmod($dir . DIRECTORY_SEPARATOR . 'oauth-public.key', 0660);
            }
            self::$keyDir = $dir;
        }
        Passport::loadKeysFrom(self::$keyDir);

        // Si User vive en otra conexion (p.ej. sqlsrv_users), se la apunta a un sqlite en
        // memoria propio para que el test nunca toque SQL Server.
        $userConnection = (new User())->getConnectionName();
        if ($userConnection !== null && $userConnection !== config('database.default')) {
            config(["database.connections.{$userConnection}" => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ]]);
            DB::purge($userConnection);
        }

        Schema::connection($userConnection)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Esquema de Passport 11 (columnas user_id, redirect, personal_access_client, password_client).
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->string('secret', 100)->nullable();
            $table->string('provider')->nullable();
            $table->text('redirect');
            $table->boolean('personal_access_client');
            $table->boolean('password_client');
            $table->boolean('revoked');
            $table->timestamps();
        });

        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('client_id');
            $table->string('name')->nullable();
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->timestamps();
            $table->dateTime('expires_at')->nullable();
        });

        // Cliente de acceso personal con id 1 (como lo crea passport:install), secreto en texto plano.
        DB::table('oauth_clients')->insert([
            'id' => 1, 'user_id' => null, 'name' => 'Personal Access Client', 'secret' => 'secreto-plano',
            'provider' => null, 'redirect' => 'http://localhost', 'personal_access_client' => true,
            'password_client' => false, 'revoked' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Route::middleware('auth:api')->get('/_test/passport/me', fn () => ['id' => auth()->id()]);
    }

    public static function tearDownAfterClass(): void {
        if (self::$keyDir !== null) {
            @unlink(self::$keyDir . DIRECTORY_SEPARATOR . 'oauth-private.key');
            @unlink(self::$keyDir . DIRECTORY_SEPARATOR . 'oauth-public.key');
            @rmdir(self::$keyDir);
            self::$keyDir = null;
        }

        // keyPath es estatico: se restaura para no afectar a los tests que siguen.
        Passport::$keyPath = null;

        parent::tearDownAfterClass();
    }

    public function test_crea_y_valida_tokens_personales_para_el_usuario_con_el_mismo_id_que_el_cliente(): void {
        $user = User::forceCreate(['id' => 1, 'name' => 'Uno', 'email' => 'uno@example.test', 'password' => bcrypt('x')]);

        $token = $user->createToken('Laravel Password Grant Client')->accessToken;

        $this->assertDatabaseHas('oauth_access_tokens', ['user_id' => 1, 'client_id' => 1, 'revoked' => false]);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/_test/passport/me')
            ->assertOk()
            ->assertJson(['id' => 1]);
    }

    public function test_crea_y_valida_tokens_personales_para_otros_usuarios(): void {
        $user = User::forceCreate(['id' => 5, 'name' => 'Cinco', 'email' => 'cinco@example.test', 'password' => bcrypt('x')]);

        $token = $user->createToken('Laravel Password Grant Client')->accessToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/_test/passport/me')
            ->assertOk()
            ->assertJson(['id' => 5]);
    }

    public function test_rechaza_tokens_revocados(): void {
        $user = User::forceCreate(['id' => 7, 'name' => 'Siete', 'email' => 'siete@example.test', 'password' => bcrypt('x')]);
        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        DB::table('oauth_access_tokens')->update(['revoked' => true]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/_test/passport/me')
            ->assertUnauthorized();
    }
}
