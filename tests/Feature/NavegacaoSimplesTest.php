<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NavegacaoSimplesTest extends TestCase
{
    use RefreshDatabase;

    public function test_inicio_mostra_primeiros_passos_para_conta_nova(): void
    {
        $user = User::factory()->create(['name' => 'Matheus Fantin']);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Olá, Matheus')
            ->assertSee('Primeiros passos')
            ->assertSee('Lançar gasto ou receita');
    }

    public function test_menu_agrupa_telas_em_abas(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/cartoes')->assertOk()->assertSee('Gastos e receitas')->assertSee('Cartões e faturas');
        $this->actingAs($user)->get('/dividendos')->assertOk()->assertSee('Minha carteira')->assertSee('Bolsa hoje');
    }

    public function test_cartao_criado_na_tela_de_lancamentos_ja_vem_selecionado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/orcamento')
            ->post('/cartoes', ['nome' => 'Nubank', 'dia_fechamento' => 3, 'dia_vencimento' => 10, 'limite' => ''])
            ->assertRedirect('/orcamento')
            ->assertSessionHas('origem_nova', 'cartao:'.$user->cards()->first()->id);

        $this->actingAs($user)->get('/orcamento')->assertOk()->assertSee('Qual cartão?')->assertSee('Nubank');
    }

    public function test_bolsa_mostra_rankings_sem_pesquisar(): void
    {
        config(['cotacoes.brapi.token' => 't']);
        Http::fake(function (Request $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
            $ticker = match (true) {
                ($q['type'] ?? '') === 'fund' => 'HGLG11',
                ($q['sortBy'] ?? '') === 'change' && ($q['sortOrder'] ?? '') === 'asc' => 'AZUL4',
                ($q['sortBy'] ?? '') === 'change' => 'MGLU3',
                default => 'PETR4',
            };

            return Http::response(['stocks' => [['stock' => $ticker, 'name' => $ticker.' S.A.', 'close' => 10, 'change' => 1.5]]]);
        });
        $user = User::factory()->create();

        $this->actingAs($user)->get('/bolsa')->assertOk()
            ->assertSee('Mais negociadas')->assertSee('PETR4')->assertSee('MGLU3')->assertSee('AZUL4')->assertSee('HGLG11');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'quote/list') && str_contains($r->url(), 'sortBy=volume') && str_contains($r->url(), 'sortOrder=desc'));
    }

    public function test_bolsa_sem_api_nao_quebra_e_nao_guarda_ranking_vazio(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/bolsa')->assertOk()->assertSee('Não consegui carregar agora');
        $this->assertNull(cache('bolsa:ranking:negociadas'));
    }
}
