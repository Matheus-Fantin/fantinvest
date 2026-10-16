<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Services\CarteiraService;
use Tests\TestCase;

class PlanoAporteTest extends TestCase
{
    private function ativo(string $ticker, string $classe, float $qtd, float $cotacao, float $nota): Asset
    {
        return new Asset(['ticker' => $ticker, 'classe' => $classe, 'quantidade' => $qtd, 'preco_medio' => $cotacao, 'cotacao' => $cotacao, 'nota' => $nota]);
    }

    public function test_aporte_vai_para_a_classe_abaixo_da_meta(): void
    {
        $ativos = collect([
            $this->ativo('ITSA4', 'acoes', 100, 10, 8),   // R$ 1.000
            $this->ativo('HGLG11', 'fii', 30, 100, 8),    // R$ 3.000
        ]);
        $metas = ['acoes' => 50, 'fii' => 50, 'exterior' => 0, 'rf' => 0, 'cripto' => 0];

        $plano = (new CarteiraService)->planoAporte($ativos, $metas, 2000);

        // Depois do aporte o total é 6.000; ações precisam de 3.000 (faltam 2.000), FIIs já estão na meta.
        $this->assertCount(1, $plano['compras']);
        $this->assertSame('ITSA4', $plano['compras'][0]['ativo']->ticker);
        $this->assertSame(200, $plano['compras'][0]['cotas']);
        $this->assertEqualsWithDelta(0, $plano['sobra'], 0.001);
    }

    public function test_compra_apenas_cotas_inteiras_e_informa_a_sobra(): void
    {
        $ativos = collect([$this->ativo('IVVB11', 'exterior', 1, 300, 8)]);
        $metas = ['acoes' => 0, 'fii' => 0, 'exterior' => 100, 'rf' => 0, 'cripto' => 0];

        $plano = (new CarteiraService)->planoAporte($ativos, $metas, 1000);

        $this->assertSame(3, $plano['compras'][0]['cotas']);
        $this->assertEqualsWithDelta(100, $plano['sobra'], 0.001);
        $this->assertEqualsWithDelta(900, $plano['investido'], 0.001);
    }

    public function test_ativo_com_nota_zero_nao_recebe_aporte(): void
    {
        $ativos = collect([
            $this->ativo('BBAS3', 'acoes', 10, 25, 0),
            $this->ativo('TAEE11', 'acoes', 10, 35, 9),
        ]);
        $metas = ['acoes' => 100, 'fii' => 0, 'exterior' => 0, 'rf' => 0, 'cripto' => 0];

        $plano = (new CarteiraService)->planoAporte($ativos, $metas, 700);

        $this->assertSame(['TAEE11'], array_map(fn ($c) => $c['ativo']->ticker, $plano['compras']));
    }

    public function test_renda_fixa_recebe_valor_fracionado(): void
    {
        $ativos = collect([$this->ativo('TESOURO SELIC', 'rf', 1, 5000, 7)]);
        $metas = ['acoes' => 0, 'fii' => 0, 'exterior' => 0, 'rf' => 100, 'cripto' => 0];

        $plano = (new CarteiraService)->planoAporte($ativos, $metas, 1234.56);

        $this->assertNull($plano['compras'][0]['cotas']);
        $this->assertEqualsWithDelta(1234.56, $plano['compras'][0]['valor'], 0.001);
    }
}
