<?php

namespace App\Models\Passport;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Laravel\Passport\Client as PassportClient;

/**
 * Cliente OAuth compatible con el esquema de oauth_clients heredado de Passport 11
 * (columnas personal_access_client / password_client, sin grant_types).
 *
 * Passport 13 deriva los grant types de esas columnas y le asigna "client_credentials"
 * a todo cliente confidencial de primera parte, incluido el cliente de acceso personal.
 * Su TokenGuard rechaza (401) cualquier token cuyo usuario tenga el mismo ID numerico que
 * el cliente si el cliente admite client_credentials (laravel/passport#1912). Con IDs
 * enteros eso dejaria afuera a los usuarios cuyo id coincide con el del cliente personal
 * o el de password grant (normalmente los usuarios 1 y 2).
 *
 * Aca los clientes de acceso personal y de password grant NO reciben client_credentials.
 * Si la tabla ya tiene la columna grant_types (esquema nuevo), se respeta tal cual.
 */
class Client extends PassportClient
{
    protected function grantTypes(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => isset($value) ? $this->fromJson($value) : array_keys(array_filter([
                'authorization_code' => ! empty($this->redirect_uris),
                'client_credentials' => $this->confidential() && $this->firstParty()
                    && ! $this->personal_access_client && ! $this->password_client,
                'implicit' => ! empty($this->redirect_uris),
                'password' => $this->password_client,
                'personal_access' => $this->personal_access_client && $this->confidential(),
                'refresh_token' => true,
                'urn:ietf:params:oauth:grant-type:device_code' => true,
            ])),
        );
    }
}
