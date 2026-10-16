<?php

namespace Tests\Feature;

use App\Models\Financing;
use App\Models\User;
use App\Services\FinanciamentoImovelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinanciamentoImovelTest extends TestCase
{
    use RefreshDatabase;

    private function contrato(User $user, array $extra = []): Financing
    {
        return $user->financings()->create($extra + [
            'nome' => 'Casa', 'banco' => 'Caixa', 'valor_imovel' => 400000, 'valor_financiado' => 300000,
            'recursos_proprios' => 50000, 'taxa_juros_anual' => 12, 'sistema' => 'sac', 'prazo_meses' => 360,
            'primeira_parcela' => '2026-01-10', 'seguro_mensal' => 80, 'taxa_administracao' => 25,
        ]);
    }

    public function test_cronograma_sac(): void
    {
        $f = $this->contrato(User::factory()->create());
        $linhas = app(FinanciamentoImovelService::class)->cronograma($f, Carbon::parse('2025-12-01'))['linhas'];

        $i = 1.12 ** (1 / 12) - 1;
        $this->assertCount(360, $linhas);
        $this->assertEqualsWithDelta(300000 / 360, $linhas[0]['amortizacao'], 0.01);
        $this->assertEqualsWithDelta(300000 * $i, $linhas[0]['juros'], 0.01);
        $this->assertEqualsWithDelta(300000 / 360 + 300000 * $i + 105, $linhas[0]['total'], 0.01);
        $this->assertLessThan($linhas[0]['total'], $linhas[359]['total']);
        $this->assertEqualsWithDelta(0, $linhas[359]['saldo'], 0.01);
    }

    public function test_cronograma_price_tem_prestacao_fixa(): void
    {
        $f = $this->contrato(User::factory()->create(), ['sistema' => 'price']);
        $linhas = app(FinanciamentoImovelService::class)->cronograma($f)['linhas'];

        $this->assertEqualsWithDelta($linhas[0]['prestacao'], $linhas[200]['prestacao'], 0.01);
        $this->assertEqualsWithDelta(0, end($linhas)['saldo'], 0.01);
    }

    public function test_amortizacao_extra_reduzindo_prazo_encurta_o_contrato(): void
    {
        $user = User::factory()->create();
        $f = $this->contrato($user);
        $this->actingAs($user)->post("/financiamento-imovel/{$f->id}/pagamentos", ['tipo' => 'amortizacao', 'valor' => '50.000,00', 'data' => '2026-03-15', 'reduzir' => 'prazo'])->assertRedirect();

        $c = app(FinanciamentoImovelService::class)->cronograma($f->fresh());
        $this->assertLessThan(360, count($c['linhas']));
        $this->assertGreaterThan(0, $c['resumo']['meses_economizados']);
        $this->assertEqualsWithDelta(50000, $c['linhas'][2]['amortizacao_extra'], 0.01);
    }

    public function test_amortizacao_extra_reduzindo_parcela_mantem_prazo(): void
    {
        $user = User::factory()->create();
        $f = $this->contrato($user);
        $antes = app(FinanciamentoImovelService::class)->cronograma($f)['linhas'][10]['total'];

        $this->actingAs($user)->post("/financiamento-imovel/{$f->id}/pagamentos", ['tipo' => 'amortizacao', 'valor' => '50000', 'data' => '2026-03-15', 'reduzir' => 'parcela']);

        $linhas = app(FinanciamentoImovelService::class)->cronograma($f->fresh())['linhas'];
        $this->assertCount(360, $linhas);
        $this->assertLessThan($antes, $linhas[10]['total']);
    }

    public function test_registra_parcela_obra_e_lanca_no_orcamento(): void
    {
        $user = User::factory()->create();
        $conta = $user->accounts()->create(['nome' => 'Caixa', 'tipo' => 'corrente', 'saldo_inicial' => 10000]);
        $f = $this->contrato($user);

        $this->actingAs($user)->post("/financiamento-imovel/{$f->id}/pagamentos", ['tipo' => 'parcela', 'numero' => '1', 'valor' => '3.910,62', 'data' => '2026-01-10', 'lancar_no_orcamento' => '1', 'account_id' => $conta->id])->assertRedirect();
        $this->actingAs($user)->post("/financiamento-imovel/{$f->id}/pagamentos", ['tipo' => 'parcela', 'numero' => '1', 'valor' => '1', 'data' => '2026-01-10'])->assertSessionHasErrors('numero');
        $this->actingAs($user)->post("/financiamento-imovel/{$f->id}/liberacoes", ['valor' => '100.000,00', 'data' => '2025-12-01', 'descricao' => '1ª medição']);
        $this->actingAs($user)->post("/financiamento-imovel/{$f->id}/gastos", ['valor' => '12.500,00', 'data' => '2025-12-05', 'descricao' => 'Cimento e areia', 'categoria' => 'Materiais', 'origem' => 'financiamento']);
        $this->actingAs($user)->post("/financiamento-imovel/{$f->id}/gastos", ['valor' => '2.000,00', 'data' => '2025-12-06', 'descricao' => 'Projeto', 'categoria' => 'Projeto e engenharia', 'origem' => 'proprio']);

        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'categoria' => 'Moradia', 'valor' => 3910.62, 'account_id' => $conta->id]);
        $servico = app(FinanciamentoImovelService::class);
        $resumo = $servico->cronograma($f->fresh())['resumo'];
        $this->assertSame(1, $resumo['parcelas_pagas']);
        $this->assertEqualsWithDelta(300000 - 300000 / 360, $resumo['saldo_devedor'], 0.01);

        $obra = $servico->obra($f);
        $this->assertEquals(100000, $obra['liberado']);
        $this->assertEquals(200000, $obra['a_liberar']);
        $this->assertEquals(87500, $obra['saldo_liberado']);
        $this->assertEquals(48000, $obra['proprio_restante']);

        $this->actingAs($user)->get("/financiamento-imovel/{$f->id}")->assertOk()->assertSee('Cimento e areia')->assertSee('R$ 12.500,00');
        $this->actingAs($user)->get('/financiamento-imovel')->assertRedirect("/financiamento-imovel/{$f->id}");
    }

    public function test_cria_contrato_pelo_formulario_e_protege_de_outros_usuarios(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/financiamento-imovel', [
            'nome' => 'Minha casa', 'banco' => 'Caixa', 'valor_imovel' => '350.000,00', 'valor_financiado' => '280.000,00',
            'recursos_proprios' => '70.000,00', 'taxa_juros_anual' => '10,99', 'sistema' => 'sac', 'prazo_meses' => '420',
            'primeira_parcela' => '2027-02-10', 'seguro_mensal' => '95,40', 'taxa_administracao' => '25,00',
        ])->assertRedirect();

        $f = $user->financings()->first();
        $this->assertEquals(280000, $f->valor_financiado);
        $this->assertEquals(10.99, $f->taxa_juros_anual);

        $this->actingAs(User::factory()->create())->get("/financiamento-imovel/{$f->id}")->assertForbidden();
    }
}
