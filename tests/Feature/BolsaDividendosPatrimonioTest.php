<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ContaService;
use App\Services\PatrimonioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BolsaDividendosPatrimonioTest extends TestCase
{
    use RefreshDatabase;

    public function test_pesquisa_e_pagina_da_acao_com_api_simulada(): void
    {
        config(['cotacoes.brapi.token' => 't']);
        Http::fake([
            'brapi.dev/api/quote/list*' => Http::response(['stocks' => [
                ['stock' => 'ITSA4', 'name' => 'Itausa PN', 'close' => 11.2, 'change' => 0.8, 'sector' => 'Finance', 'type' => 'stock'],
            ]]),
            'brapi.dev/api/quote/ITSA4*' => Http::response(['results' => [[
                'symbol' => 'ITSA4', 'longName' => 'Itaúsa S.A.', 'regularMarketPrice' => 11.37, 'regularMarketChangePercent' => 1.1,
                'historicalDataPrice' => [['date' => 1760000000, 'close' => 10.9], ['date' => 1760086400, 'close' => 11.37]],
            ]]]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/bolsa?q=itausa')->assertOk()->assertSee('ITSA4')->assertSee('Itausa PN');
        $this->actingAs($user)->get('/bolsa/itsa4')->assertOk()->assertSee('Itaúsa S.A.')->assertSee('R$ 11,37');
    }

    public function test_api_fora_do_ar_nao_quebra_a_bolsa(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/bolsa?q=petr')->assertOk()->assertSee('Nada encontrado');
        $this->actingAs($user)->get('/bolsa/PETR4')->assertOk()->assertSee('Não consegui buscar');
    }

    public function test_compra_e_venda_calculam_preco_medio_e_lucro(): void
    {
        Http::fake(['*' => Http::response([], 404)]);
        $user = User::factory()->create();
        $corretora = $user->accounts()->create(['nome' => 'XP', 'tipo' => 'corretora', 'saldo_inicial' => 0]);

        $this->actingAs($user)->post('/bolsa/TAEE11/operacao', ['tipo' => 'compra', 'classe' => 'acoes', 'data' => now()->subDays(10)->toDateString(), 'quantidade' => '100', 'preco' => '30,00', 'taxas' => '0'])->assertRedirect();
        $this->actingAs($user)->post('/bolsa/TAEE11/operacao', ['tipo' => 'compra', 'classe' => 'acoes', 'data' => now()->subDays(5)->toDateString(), 'quantidade' => '100', 'preco' => '40,00', 'taxas' => '10,00'])->assertRedirect();

        $ativo = $user->assets()->where('ticker', 'TAEE11')->first();
        $this->assertEquals(200, $ativo->quantidade);
        $this->assertEqualsWithDelta(35.05, $ativo->preco_medio, 0.0001);
        $this->assertEquals(2, $user->transactions()->where('categoria', 'Investimentos')->count());

        $this->actingAs($user)->post('/bolsa/TAEE11/operacao', ['tipo' => 'venda', 'classe' => 'acoes', 'data' => now()->toDateString(), 'quantidade' => '50', 'preco' => '45,00', 'taxas' => '5', 'account_id' => $corretora->id])->assertRedirect();
        $ativo->refresh();
        $this->assertEquals(150, $ativo->quantidade);
        $this->assertEqualsWithDelta(35.05, $ativo->preco_medio, 0.0001);
        $venda = $user->operations()->where('tipo', 'venda')->first();
        $this->assertEqualsWithDelta((45 - 35.05) * 50 - 5, $venda->resultado, 0.01);
        $this->assertEqualsWithDelta(50 * 45 - 5, app(ContaService::class)->comSaldos($user)->first()->saldo, 0.01);

        $this->actingAs($user)->post('/bolsa/TAEE11/operacao', ['tipo' => 'venda', 'classe' => 'acoes', 'data' => now()->toDateString(), 'quantidade' => '1000', 'preco' => '45'])
            ->assertSessionHasErrors('quantidade');
    }

    public function test_dividendo_vira_receita_e_some_ao_excluir(): void
    {
        $user = User::factory()->create();
        $ativo = $user->assets()->create(['ticker' => 'HGLG11', 'classe' => 'fii', 'quantidade' => 20, 'preco_medio' => 150, 'cotacao' => 160, 'nota' => 8]);

        $this->actingAs($user)->post('/dividendos', ['asset_id' => $ativo->id, 'tipo' => 'rendimento', 'data' => now()->toDateString(), 'valor' => '22,00'])->assertRedirect();

        $provento = $user->dividends()->first();
        $this->assertDatabaseHas('transactions', ['id' => $provento->transaction_id, 'tipo' => 'receita', 'categoria' => 'Dividendos', 'valor' => 22]);
        $this->actingAs($user)->get('/dividendos')->assertOk()->assertSee('HGLG11')->assertSee('R$ 22,00');

        $this->actingAs($user)->delete("/dividendos/{$provento->id}")->assertRedirect();
        $this->assertSame(0, $user->transactions()->count());
    }

    public function test_patrimonio_soma_bens_e_desconta_dividas(): void
    {
        $user = User::factory()->create();
        $user->accounts()->create(['nome' => 'Inter', 'tipo' => 'corrente', 'saldo_inicial' => 10000]);
        $user->assets()->create(['ticker' => 'ITSA4', 'classe' => 'acoes', 'quantidade' => 100, 'preco_medio' => 10, 'cotacao' => 12, 'nota' => 8]);
        $cartao = $user->cards()->create(['nome' => 'Nubank', 'limite' => 5000, 'dia_fechamento' => 3, 'dia_vencimento' => 10]);
        $this->actingAs($user)->post('/orcamento', ['tipo' => 'despesa', 'data' => now()->toDateString(), 'categoria' => 'Compras', 'valor' => '600', 'parcelas' => '3', 'origem' => 'cartao:'.$cartao->id]);
        $user->financings()->create(['nome' => 'Casa', 'valor_imovel' => 300000, 'valor_financiado' => 200000, 'taxa_juros_anual' => 10, 'sistema' => 'sac', 'prazo_meses' => 360, 'primeira_parcela' => now()->addMonth()->toDateString()]);

        $p = app(PatrimonioService::class)->calcular($user);
        $this->assertEquals(10000 + 1200 + 300000, $p['total_bens']);
        $this->assertEquals(600 + 200000, $p['total_dividas']);
        $this->assertEquals(110600, $p['liquido']);

        $this->actingAs($user)->get('/patrimonio')->assertOk()->assertSee('R$ 110.600,00');
        $this->assertDatabaseHas('net_worth_snapshots', ['user_id' => $user->id, 'mes' => now()->format('Y-m')]);
    }
}
