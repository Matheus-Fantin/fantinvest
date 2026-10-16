<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ObjetivosTest extends TestCase
{
    use RefreshDatabase;

    public function test_aporte_mensal_sem_rendimento_divide_o_que_falta(): void
    {
        $g = new Goal(['valor_alvo' => 12000, 'valor_atual' => 0, 'data_alvo' => '2027-10-15', 'rendimento_anual' => 0]);

        $this->assertSame(12, $g->mesesRestantes(Carbon::parse('2026-10-15')));
        $this->assertEqualsWithDelta(1000, $g->aporteMensalNecessario(Carbon::parse('2026-10-15')), 0.01);
    }

    public function test_rendimento_reduz_o_aporte_mensal(): void
    {
        $g = new Goal(['valor_alvo' => 12000, 'valor_atual' => 2000, 'data_alvo' => '2027-10-15', 'rendimento_anual' => 12]);
        $hoje = Carbon::parse('2026-10-15');

        $mensal = $g->aporteMensalNecessario($hoje);
        $this->assertLessThan(10000 / 12, $mensal);

        // Conferência: guardando esse valor por 12 meses a 12% a.a., chega-se ao alvo.
        $i = 1.12 ** (1 / 12) - 1;
        $saldo = 2000;
        for ($m = 0; $m < 12; $m++) {
            $saldo = $saldo * (1 + $i) + $mensal;
        }
        $this->assertEqualsWithDelta(12000, $saldo, 0.01);
    }

    public function test_cria_guarda_e_lanca_no_orcamento(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/objetivos', ['nome' => 'Viagem', 'valor_alvo' => '12.000,00', 'data_alvo' => '2027-12-31', 'rendimento_anual' => '10'])->assertRedirect();
        $objetivo = $user->goals()->first();
        $this->assertEquals(12000, $objetivo->valor_alvo);

        $this->actingAs($user)->post("/objetivos/{$objetivo->id}/depositar", ['valor' => '1.500,00', 'lancar_no_orcamento' => '1'])->assertRedirect();
        $this->assertEquals(1500, $objetivo->fresh()->valor_atual);
        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'categoria' => 'Objetivos', 'valor' => 1500]);

        $this->actingAs($user)->post("/objetivos/{$objetivo->id}/depositar", ['valor' => '-500'])->assertRedirect();
        $this->assertEquals(1000, $objetivo->fresh()->valor_atual);
        $this->assertSame(1, $user->transactions()->count()); // retirada não vira lançamento

        $this->actingAs($user)->get('/objetivos')->assertOk()->assertSee('Viagem');
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Viagem');
    }

    public function test_nao_mexe_em_objetivo_de_outro_usuario(): void
    {
        $dono = User::factory()->create();
        $objetivo = $dono->goals()->create(['nome' => 'Carro', 'valor_alvo' => 1000]);

        $this->actingAs(User::factory()->create())->post("/objetivos/{$objetivo->id}/depositar", ['valor' => '100'])->assertForbidden();
        $this->assertEquals(0, $objetivo->fresh()->valor_atual);
    }
}
