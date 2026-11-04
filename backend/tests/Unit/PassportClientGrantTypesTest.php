<?php

namespace Tests\Unit;

use App\Models\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PassportClientGrantTypesTest extends TestCase {
    private function legacyClient(array $attributes): Client {
        $client = new Client();
        $client->setRawAttributes(array_merge([
            'id' => 1,
            'user_id' => null,
            'name' => 'cliente',
            'secret' => 'secreto',
            'provider' => null,
            'redirect' => 'http://localhost',
            'personal_access_client' => 0,
            'password_client' => 0,
            'revoked' => 0,
        ], $attributes));

        return $client;
    }

    public function test_usa_el_modelo_de_cliente_de_la_app(): void {
        $this->assertSame(Client::class, Passport::clientModel());
    }

    public function test_cliente_personal_heredado_no_admite_client_credentials(): void {
        $client = $this->legacyClient(['personal_access_client' => 1]);

        $this->assertTrue($client->hasGrantType('personal_access'));
        $this->assertFalse($client->hasGrantType('client_credentials'));
    }

    public function test_cliente_password_heredado_no_admite_client_credentials(): void {
        $client = $this->legacyClient(['id' => 2, 'password_client' => 1]);

        $this->assertTrue($client->hasGrantType('password'));
        $this->assertFalse($client->hasGrantType('client_credentials'));
    }

    public function test_cliente_confidencial_comun_sigue_admitiendo_client_credentials(): void {
        $client = $this->legacyClient(['id' => 3]);

        $this->assertTrue($client->hasGrantType('client_credentials'));
    }

    public function test_respeta_la_columna_grant_types_del_esquema_nuevo(): void {
        $client = $this->legacyClient(['grant_types' => json_encode(['client_credentials'])]);

        $this->assertSame(['client_credentials'], $client->grant_types);
    }

    public function test_los_clientes_usan_ids_enteros(): void {
        $this->assertFalse(Passport::$clientUuids);
        $this->assertFalse((new Client())->usesUniqueIds());
    }
}
