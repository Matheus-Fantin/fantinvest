<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_extrato_com_ponto_e_virgula_e_sugere_categorias(): void
    {
        $user = User::factory()->create();
        $csv = "Data;Descrição;Valor\n05/10/2026;Salário Empresa X;8.200,00\n06/10/2026;Aluguel outubro;-2.100,00\n12/10/2026;Supermercado Assaí;-389,90\n13/10/2026;Algo qualquer;-10,00\n";

        $resposta = $this->actingAs($user)->post('/orcamento/importar', ['arquivo' => UploadedFile::fake()->createWithContent('extrato.csv', $csv)]);

        $resposta->assertOk();
        $linhas = $resposta->viewData('linhas');
        $this->assertCount(4, $linhas);
        $this->assertSame(['2026-10-05', 'receita', 'Salário', 8200.0], [$linhas[0]['data'], $linhas[0]['tipo'], $linhas[0]['categoria'], $linhas[0]['valor']]);
        $this->assertSame(['despesa', 'Moradia', 2100.0], [$linhas[1]['tipo'], $linhas[1]['categoria'], $linhas[1]['valor']]);
        $this->assertSame('Mercado', $linhas[2]['categoria']);
        $this->assertSame('Outros gastos', $linhas[3]['categoria']);
    }

    public function test_le_fatura_nubank_e_desmarca_pagamentos(): void
    {
        $user = User::factory()->create();
        $csv = "date,title,amount\n2026-10-03,Ifood *Restaurante,45.90\n2026-10-04,Uber *Trip,18.5\n2026-10-10,Pagamento recebido,-1200.00\n";

        $linhas = $this->actingAs($user)
            ->post('/orcamento/importar', ['arquivo' => UploadedFile::fake()->createWithContent('nubank.csv', $csv), 'fatura' => '1'])
            ->viewData('linhas');

        $this->assertSame(['Restaurantes', 'Transporte'], [$linhas[0]['categoria'], $linhas[1]['categoria']]);
        $this->assertTrue($linhas[0]['incluir']);
        $this->assertFalse($linhas[2]['incluir']);
        $this->assertSame('despesa', $linhas[2]['tipo']);
    }

    public function test_marca_linhas_ja_lancadas(): void
    {
        $user = User::factory()->create();
        $user->transactions()->create(['data' => '2026-10-06', 'tipo' => 'despesa', 'categoria' => 'Moradia', 'descricao' => 'Aluguel', 'valor' => 2100]);
        $csv = "Data;Descrição;Valor\n06/10/2026;Aluguel;-2.100,00\n";

        $linhas = $this->actingAs($user)->post('/orcamento/importar', ['arquivo' => UploadedFile::fake()->createWithContent('e.csv', $csv)])->viewData('linhas');

        $this->assertFalse($linhas[0]['incluir']);
        $this->assertSame('Já lançado', $linhas[0]['aviso']);
    }

    public function test_arquivo_sem_colunas_reconhecidas_mostra_erro(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/orcamento/importar', ['arquivo' => UploadedFile::fake()->createWithContent('x.csv', "a;b\n1;2\n")])
            ->assertSessionHasErrors('arquivo');
    }

    public function test_confirma_apenas_as_linhas_marcadas(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/orcamento/importar/confirmar', ['linhas' => [
            ['incluir' => '1', 'data' => '2026-10-05', 'descricao' => 'Salário', 'valor' => '8200', 'tipo' => 'receita', 'categoria' => 'Salário'],
            ['data' => '2026-10-06', 'descricao' => 'Aluguel', 'valor' => '2100', 'tipo' => 'despesa', 'categoria' => 'Moradia'],
            ['incluir' => '1', 'data' => '2026-10-12', 'descricao' => 'Mercado', 'valor' => '389.9', 'tipo' => 'despesa', 'categoria' => 'Mercado'],
        ]])->assertRedirect(route('orcamento.index', ['mes' => '2026-10']));

        $this->assertSame(2, $user->transactions()->count());
        $this->assertDatabaseMissing('transactions', ['descricao' => 'Aluguel']);
    }

    public function test_rejeita_categoria_que_nao_combina_com_o_tipo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/orcamento/importar/confirmar', ['linhas' => [
            ['incluir' => '1', 'data' => '2026-10-05', 'valor' => '10', 'tipo' => 'receita', 'categoria' => 'Mercado'],
        ]])->assertSessionHasErrors();
        $this->assertSame(0, $user->transactions()->count());
    }
}
