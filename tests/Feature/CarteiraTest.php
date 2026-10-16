<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarteiraTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginas_exigem_login(): void
    {
        foreach (['/dashboard', '/carteira', '/aporte', '/orcamento', '/simuladores/juros-compostos', '/simuladores/financiamento'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_paginas_abrem_para_usuario_logado(): void
    {
        $user = User::factory()->create();

        foreach (['/dashboard', '/carteira', '/aporte', '/orcamento', '/simuladores/juros-compostos', '/simuladores/financiamento'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_cadastra_ativo_com_numeros_em_formato_brasileiro(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/carteira', [
            'ticker' => 'itsa4', 'classe' => 'acoes', 'quantidade' => '1.000', 'preco_medio' => '9,50', 'cotacao' => '10,25', 'nota' => '8',
        ])->assertRedirect();

        $ativo = $user->assets()->first();
        $this->assertSame('ITSA4', $ativo->ticker);
        $this->assertEquals(1000, $ativo->quantidade);
        $this->assertEquals(9.5, $ativo->preco_medio);
        $this->assertEquals(10.25, $ativo->cotacao);
    }

    public function test_nao_altera_ativo_de_outro_usuario(): void
    {
        $dono = User::factory()->create();
        $outro = User::factory()->create();
        $ativo = $dono->assets()->create(['ticker' => 'WEGE3', 'classe' => 'acoes', 'quantidade' => 10, 'preco_medio' => 40, 'cotacao' => 40, 'nota' => 7]);

        $this->actingAs($outro)->delete("/carteira/{$ativo->id}")->assertForbidden();
        $this->assertModelExists($ativo);
    }

    public function test_nota_vem_do_checklist(): void
    {
        $user = User::factory()->create();
        $ativo = $user->assets()->create(['ticker' => 'TAEE11', 'classe' => 'acoes', 'quantidade' => 10, 'preco_medio' => 35, 'cotacao' => 35, 'nota' => 0]);

        $this->actingAs($user)->put("/carteira/{$ativo->id}/nota", ['criterios' => [0, 1, 2, 3, 4, 5, 6]])->assertRedirect();

        $this->assertEquals(7.0, $ativo->fresh()->nota);
    }

    public function test_metas_precisam_somar_100(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/metas', ['metas' => ['acoes' => '50', 'fii' => '20', 'exterior' => '10', 'rf' => '10', 'cripto' => '0']])
            ->assertSessionHasErrors('metas');

        $this->actingAs($user)->put('/metas', ['metas' => ['acoes' => '40', 'fii' => '20', 'exterior' => '20', 'rf' => '17,5', 'cripto' => '2,5']])
            ->assertSessionHasNoErrors();
        $this->assertEquals(17.5, $user->metas()['rf']);
    }

    public function test_registrar_aporte_atualiza_preco_medio_e_lanca_no_orcamento(): void
    {
        $user = User::factory()->create();
        foreach (['acoes' => 100, 'fii' => 0, 'exterior' => 0, 'rf' => 0, 'cripto' => 0] as $classe => $p) {
            $user->allocationTargets()->create(['classe' => $classe, 'percentual' => $p]);
        }
        $ativo = $user->assets()->create(['ticker' => 'BBSE3', 'classe' => 'acoes', 'quantidade' => 10, 'preco_medio' => 30, 'cotacao' => 40, 'nota' => 8]);

        $this->actingAs($user)->post('/aporte', ['valor' => '400'])->assertRedirect();

        $ativo->refresh();
        $this->assertEquals(20, $ativo->quantidade);
        $this->assertEquals(35, $ativo->preco_medio);
        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'categoria' => 'Investimentos', 'valor' => 400]);
    }

    public function test_aporte_em_cripto_aumenta_a_quantidade(): void
    {
        $user = User::factory()->create();
        foreach (['acoes' => 0, 'fii' => 0, 'exterior' => 0, 'rf' => 0, 'cripto' => 100] as $classe => $p) {
            $user->allocationTargets()->create(['classe' => $classe, 'percentual' => $p]);
        }
        $btc = $user->assets()->create(['ticker' => 'BTC', 'classe' => 'cripto', 'quantidade' => 0.01, 'preco_medio' => 400000, 'cotacao' => 500000, 'nota' => 7, 'atualizacao_automatica' => false]);

        $this->actingAs($user)->post('/aporte', ['valor' => '1000'])->assertRedirect();

        $btc->refresh();
        $this->assertEqualsWithDelta(0.012, $btc->quantidade, 1e-9);
        $this->assertEqualsWithDelta(5000 / 0.012, $btc->preco_medio, 0.01);
        $this->assertEquals(500000, $btc->cotacao);
    }
}
