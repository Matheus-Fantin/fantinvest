<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use App\Services\CartaoService;
use App\Services\ContaService;
use App\Services\LancamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContasCartoesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_fatura_da_compra_respeita_fechamento_e_vencimento(): void
    {
        $nubank = new Card(['dia_fechamento' => 3, 'dia_vencimento' => 10]);
        $this->assertSame('2026-10', $nubank->faturaDaCompra('2026-10-02'));
        $this->assertSame('2026-11', $nubank->faturaDaCompra('2026-10-03'));
        $this->assertSame('2026-11', $nubank->faturaDaCompra('2026-10-25'));

        // Fecha dia 25 e vence dia 5 do mês seguinte.
        $outro = new Card(['dia_fechamento' => 25, 'dia_vencimento' => 5]);
        $this->assertSame('2026-11', $outro->faturaDaCompra('2026-10-24'));
        $this->assertSame('2026-12', $outro->faturaDaCompra('2026-10-25'));
        $this->assertSame('2026-10-25', $outro->dataFechamento('2026-11')->toDateString());
    }

    public function test_compra_parcelada_no_cartao_vai_para_faturas_seguidas(): void
    {
        $user = User::factory()->create();
        $cartao = $user->cards()->create(['nome' => 'Nubank', 'limite' => 5000, 'dia_fechamento' => 3, 'dia_vencimento' => 10]);

        $this->actingAs($user)->post('/orcamento', [
            'tipo' => 'despesa', 'data' => '2026-10-15', 'categoria' => 'Compras', 'descricao' => 'Geladeira',
            'valor' => '3.000,00', 'parcelas' => '10', 'origem' => 'cartao:'.$cartao->id,
        ])->assertRedirect();

        $parcelas = $user->transactions()->orderBy('parcela')->get();
        $this->assertCount(10, $parcelas);
        $this->assertEquals(3000, $parcelas->sum('valor'));
        $this->assertSame('2026-11', $parcelas[0]->fatura);
        $this->assertSame('2027-08', $parcelas[9]->fatura);
        $this->assertSame('3/10', $parcelas[2]->rotuloParcela());
        $this->assertSame(1, $parcelas->pluck('parcelamento_id')->unique()->count());
        $this->assertEquals(3000, app(CartaoService::class)->limiteUsado($cartao));

        // Excluir todas as parcelas de uma vez.
        $this->actingAs($user)->delete("/orcamento/{$parcelas[4]->id}", ['todas' => '1'])->assertRedirect();
        $this->assertSame(0, $user->transactions()->count());
    }

    public function test_parcelas_com_centavos_somam_o_total(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/orcamento', ['tipo' => 'despesa', 'data' => '2026-10-15', 'categoria' => 'Compras', 'valor' => '100,00', 'parcelas' => '3']);

        $valores = $user->transactions()->orderBy('parcela')->pluck('valor')->all();
        $this->assertEquals([33.34, 33.33, 33.33], $valores);
    }

    public function test_pagamento_de_fatura_baixa_a_conta_sem_contar_como_gasto(): void
    {
        Carbon::setTestNow('2026-11-12');
        $user = User::factory()->create();
        $conta = $user->accounts()->create(['nome' => 'Inter', 'tipo' => 'corrente', 'saldo_inicial' => 5000]);
        $cartao = $user->cards()->create(['nome' => 'Nubank', 'limite' => 5000, 'dia_fechamento' => 3, 'dia_vencimento' => 10, 'account_id' => $conta->id]);

        $this->actingAs($user)->post('/orcamento', ['tipo' => 'despesa', 'data' => '2026-10-20', 'categoria' => 'Mercado', 'valor' => '800', 'origem' => 'cartao:'.$cartao->id]);
        $this->actingAs($user)->post('/orcamento', ['tipo' => 'receita', 'data' => '2026-11-05', 'categoria' => 'Salário', 'valor' => '3000', 'origem' => 'conta:'.$conta->id]);

        $faturas = app(CartaoService::class)->faturas($cartao);
        $this->assertSame('atrasada', $faturas['2026-11']['situacao']);

        $this->actingAs($user)->post("/cartoes/{$cartao->id}/pagar", ['fatura' => '2026-11', 'valor' => '800,00', 'data' => '2026-11-12', 'account_id' => $conta->id])->assertRedirect();

        $faturas = app(CartaoService::class)->faturas($cartao);
        $this->assertSame('paga', $faturas['2026-11']['situacao']);
        $this->assertEquals(7200, app(ContaService::class)->comSaldos($user)->first()->saldo); // 5000 + 3000 − 800
        $this->assertSame(1, $user->transactions()->where('tipo', 'despesa')->count()); // o pagamento não virou gasto
        $this->actingAs($user)->get("/cartoes/{$cartao->id}?fatura=2026-11")->assertOk()->assertSee('Paga');
    }

    public function test_saldo_da_conta_com_transferencias_e_lancamentos_futuros(): void
    {
        Carbon::setTestNow('2026-10-16');
        $user = User::factory()->create();
        $corrente = $user->accounts()->create(['nome' => 'Corrente', 'tipo' => 'corrente', 'saldo_inicial' => 1000]);
        $poupanca = $user->accounts()->create(['nome' => 'Poupança', 'tipo' => 'poupanca', 'saldo_inicial' => 0]);

        $this->actingAs($user)->post('/transferencias', ['de_account_id' => $corrente->id, 'para_account_id' => $poupanca->id, 'valor' => '300,00', 'data' => '2026-10-10'])->assertRedirect();
        $this->actingAs($user)->post('/orcamento', ['tipo' => 'despesa', 'data' => '2026-10-11', 'categoria' => 'Mercado', 'valor' => '100', 'origem' => 'conta:'.$corrente->id]);
        $this->actingAs($user)->post('/orcamento', ['tipo' => 'despesa', 'data' => '2026-10-30', 'categoria' => 'Mercado', 'valor' => '999', 'origem' => 'conta:'.$corrente->id]);

        $saldos = app(ContaService::class)->comSaldos($user)->pluck('saldo', 'nome');
        $this->assertEquals(600, $saldos['Corrente']); // o gasto do dia 30 ainda não aconteceu
        $this->assertEquals(300, $saldos['Poupança']);
        $this->actingAs($user)->get('/contas')->assertOk()->assertSee('R$ 600,00');
    }

    public function test_nao_usa_conta_ou_cartao_de_outro_usuario(): void
    {
        $outro = User::factory()->create();
        $cartaoAlheio = $outro->cards()->create(['nome' => 'X', 'limite' => 1, 'dia_fechamento' => 1, 'dia_vencimento' => 8]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/orcamento', ['tipo' => 'despesa', 'data' => '2026-10-15', 'categoria' => 'Compras', 'valor' => '10', 'origem' => 'cartao:'.$cartaoAlheio->id])
            ->assertSessionHasErrors('card_id');
        $this->actingAs($user)->get("/cartoes/{$cartaoAlheio->id}")->assertForbidden();
    }

    public function test_compromissos_futuros_somam_parcelas_e_financiamento(): void
    {
        Carbon::setTestNow('2026-10-16');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/orcamento', ['tipo' => 'despesa', 'data' => '2026-10-16', 'categoria' => 'Compras', 'valor' => '1200', 'parcelas' => '12']);
        $user->financings()->create(['nome' => 'Casa', 'valor_financiado' => 120000, 'taxa_juros_anual' => 0, 'sistema' => 'sac', 'prazo_meses' => 120, 'primeira_parcela' => '2026-11-10']);

        $c = collect(app(LancamentoService::class)->compromissosFuturos($user))->keyBy('mes');
        $this->assertEquals(100, $c['2026-10']['parcelas']);
        $this->assertEquals(0, $c['2026-10']['financiamento']);
        $this->assertEquals(1000, $c['2026-11']['financiamento']);
        $this->assertEquals(100, $c['2027-09']['parcelas']);
        $this->actingAs($user)->get('/orcamento')->assertOk()->assertSee('Já comprometido');
    }
}
