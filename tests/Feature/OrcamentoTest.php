<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OrcamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrcamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_lanca_e_resume_o_mes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/orcamento', ['tipo' => 'receita', 'data' => '2026-10-05', 'categoria' => 'Salário', 'valor' => '10.000,00'])->assertRedirect();
        $this->actingAs($user)->post('/orcamento', ['tipo' => 'despesa', 'data' => '2026-10-06', 'categoria' => 'Moradia', 'valor' => '3000'])->assertRedirect();
        $this->actingAs($user)->post('/orcamento', ['tipo' => 'despesa', 'data' => '2026-10-07', 'categoria' => 'Lazer', 'valor' => '1000'])->assertRedirect();
        $this->actingAs($user)->post('/orcamento', ['tipo' => 'despesa', 'data' => '2026-10-08', 'categoria' => 'Investimentos', 'valor' => '2000'])->assertRedirect();

        $r = app(OrcamentoService::class)->resumoDoMes($user, '2026-10');

        $this->assertEquals(10000, $r['receitas']);
        $this->assertEquals(4000, $r['gastos']);      // investimento não conta como gasto
        $this->assertEquals(3000, $r['ess']);
        $this->assertEquals(1000, $r['des']);
        $this->assertEquals(2000, $r['inv']);
        $this->assertEquals(4000, $r['sobra']);
        $this->assertEquals(60, $r['taxa_poupanca']); // (2.000 investidos + 4.000 de sobra) / 10.000
    }

    public function test_categoria_precisa_combinar_com_o_tipo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/orcamento', ['tipo' => 'receita', 'data' => '2026-10-05', 'categoria' => 'Moradia', 'valor' => '100'])
            ->assertSessionHasErrors('categoria');
    }

    public function test_nao_exclui_lancamento_de_outro_usuario(): void
    {
        $dono = User::factory()->create();
        $lancamento = $dono->transactions()->create(['tipo' => 'despesa', 'data' => '2026-10-05', 'categoria' => 'Mercado', 'valor' => 50]);

        $this->actingAs(User::factory()->create())->delete("/orcamento/{$lancamento->id}")->assertForbidden();
        $this->assertModelExists($lancamento);
    }
}
