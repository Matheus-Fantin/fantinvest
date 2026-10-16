<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CotacoesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cotacoes.brapi.token' => 'token-teste', 'cotacoes.brapi.tickers_por_requisicao' => 1]);
    }

    private function fakeApis(): void
    {
        Http::fake([
            'brapi.dev/api/quote/ITSA4*' => Http::response(['results' => [['symbol' => 'ITSA4', 'regularMarketPrice' => 11.37, 'regularMarketChangePercent' => 1.25]]]),
            'brapi.dev/api/quote/HGLG11*' => Http::response(['results' => [['symbol' => 'HGLG11', 'regularMarketPrice' => 159.9, 'regularMarketChangePercent' => -0.4]]]),
            'brapi.dev/api/quote/*' => Http::response(['error' => true, 'message' => 'Não encontramos a ação'], 404),
            'api.coingecko.com/*' => Http::response(['bitcoin' => ['brl' => 612345.67, 'brl_24h_change' => 2.5]]),
        ]);
    }

    private function ativo(User $user, string $ticker, string $classe, bool $auto = true)
    {
        return $user->assets()->create(['ticker' => $ticker, 'classe' => $classe, 'quantidade' => 1, 'preco_medio' => 10, 'cotacao' => 10, 'nota' => 7, 'atualizacao_automatica' => $auto]);
    }

    public function test_atualiza_b3_e_cripto_e_informa_falhas(): void
    {
        $this->fakeApis();
        $user = User::factory()->create();
        $itsa = $this->ativo($user, 'ITSA4', 'acoes');
        $hglg = $this->ativo($user, 'HGLG11', 'fii');
        $btc = $this->ativo($user, 'BTC', 'cripto');
        $xxx = $this->ativo($user, 'XXXX3', 'acoes');
        $cdb = $this->ativo($user, 'CDB', 'rf');
        $manual = $this->ativo($user, 'BBSE3', 'acoes', auto: false);

        $this->actingAs($user)->postJson('/cotacoes/atualizar')
            ->assertOk()
            ->assertJson(['atualizados' => 3, 'falhas' => ['XXXX3']]);

        $this->assertEquals(11.37, $itsa->fresh()->cotacao);
        $this->assertEquals(1.25, $itsa->fresh()->variacao_dia);
        $this->assertNotNull($itsa->fresh()->cotacao_atualizada_em);
        $this->assertEquals(159.9, $hglg->fresh()->cotacao);
        $this->assertEquals(612345.67, $btc->fresh()->cotacao);
        $this->assertEquals(10, $xxx->fresh()->cotacao);
        $this->assertEquals(10, $cdb->fresh()->cotacao);     // renda fixa não tem API
        $this->assertEquals(10, $manual->fresh()->cotacao);  // marcado como manual

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'quote/ITSA4') && $r->hasHeader('Authorization', 'Bearer token-teste'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'simple/price') && str_contains($r->url(), 'ids=bitcoin') && str_contains($r->url(), 'vs_currencies=brl'));
    }

    public function test_reaproveita_cotacao_em_cache(): void
    {
        $this->fakeApis();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->ativo($a, 'ITSA4', 'acoes');
        $this->ativo($b, 'ITSA4', 'acoes');

        $this->artisan('cotacoes:atualizar')->assertSuccessful();
        $this->artisan('cotacoes:atualizar')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_agrupa_tickers_quando_o_plano_permite(): void
    {
        config(['cotacoes.brapi.tickers_por_requisicao' => 10]);
        Http::fake(['brapi.dev/*' => Http::response(['results' => [
            ['symbol' => 'ITSA4', 'regularMarketPrice' => 11],
            ['symbol' => 'TAEE11', 'regularMarketPrice' => 36],
        ]])]);
        $user = User::factory()->create();
        $this->ativo($user, 'ITSA4', 'acoes');
        $this->ativo($user, 'TAEE11', 'acoes');

        $this->actingAs($user)->postJson('/cotacoes/atualizar')->assertJson(['atualizados' => 2]);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'quote/ITSA4,TAEE11'));
    }

    public function test_api_fora_do_ar_nao_quebra_a_pagina(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);
        $user = User::factory()->create();
        $ativo = $this->ativo($user, 'ITSA4', 'acoes');

        $this->actingAs($user)->post('/cotacoes/atualizar')->assertRedirect()->assertSessionHas('sucesso');
        $this->assertEquals(10, $ativo->fresh()->cotacao);
        $this->actingAs($user)->get('/carteira')->assertOk()->assertSee('Atualizar agora');
    }

    public function test_novo_ativo_ja_busca_a_cotacao(): void
    {
        $this->fakeApis();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/carteira', [
            'ticker' => 'itsa4', 'classe' => 'acoes', 'quantidade' => '100', 'preco_medio' => '9,50', 'nota' => '8', 'atualizacao_automatica' => '1',
        ])->assertSessionHas('sucesso', fn ($m) => str_contains($m, 'R$ 11,37'));
    }
}
