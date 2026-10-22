<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BuscaSugestoesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sugestoes_vem_da_brapi_e_ficam_em_cache(): void
    {
        config(['cotacoes.brapi.token' => 't']);
        Http::fake(['brapi.dev/api/quote/list*' => Http::response(['stocks' => [
            ['stock' => 'WIZC3', 'name' => 'Wiz Co ON', 'close' => 7.1, 'change' => -0.5, 'logo' => null],
        ]])]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/bolsa/sugestoes?q=wi')->assertOk()
            ->assertJson([['ticker' => 'WIZC3', 'nome' => 'Wiz Co ON', 'preco' => 7.1]]);
        $this->actingAs($user)->getJson('/bolsa/sugestoes?q=wi')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_uma_letra_nao_chama_a_api(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/bolsa/sugestoes?q=p')->assertOk()->assertExactJson([]);
        Http::assertNothingSent();
    }

    public function test_api_fora_do_ar_devolve_lista_vazia(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/bolsa/sugestoes?q=petro')->assertOk()->assertExactJson([]);
    }

    public function test_pagina_da_bolsa_tem_lista_local_de_sugestoes(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/bolsa')->assertOk()->assertSee('buscaAtivos', false)->assertSee('Petrobras PN');
    }
}
