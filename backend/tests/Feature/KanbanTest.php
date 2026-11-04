<?php

namespace Tests\Feature;

use App\Http\Estados;
use App\Http\Kanban;
use App\Models\Kanbans;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class KanbanTest extends TestCase {

    public function test_listado_kanbans() {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/api/kanban');

        $response->assertStatus(200);
    }

    public function test_creacion_kanban() {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/kanban', [
                'hojas'             => '1',
                'modelo'            => 2,
                'mes'               => '04',
                'cantidad_reverso'  => '1'
            ]);

        $response->assertStatus(200);
    }

    public function test_get_kanban() {
        $user = User::factory()->create();

        $kanban = Kanbans::first();

        $response = $this->actingAs($user)
            ->get('/api/kanban/' . $kanban->codigo . '/existencia');

        $response->assertStatus(200);
    }

    public function test_get_kanban_incorrecto() {
        $user = User::factory()->create();

        // $kanban = Kanbans::first();

        $response = $this->actingAs($user)
            ->get('/api/kanban/P2323232323232/existencia');

        $response->assertStatus(404);
    }

    public function test_verifica_cambio_estado_incorrecto() {
        $user = User::factory()->create();
        $kanban = Kanban::create("P", ['mes' => '04', 'modelo' => 23]);

        $response = $this->actingAs($user)
            ->postJson('/api/kanban/existencia/estado', [
                'kanban'            => $kanban->codigo,
                'estado'            => Estados::EN_BUFFER,
            ]);

        $response->assertStatus(404);
    }

    public function test_verifica_cambio_estado() {
        $user = User::factory()->create();
        $kanban = Kanban::create("P", ['mes' => '04', 'modelo' => 23]);

        $response = $this->actingAs($user)
            ->postJson('/api/kanban/existencia/estado', [
                'kanban'            => $kanban->codigo,
                'estado'            => Estados::EN_CORTE,
            ]);

        $response->assertStatus(200);
    }
}
