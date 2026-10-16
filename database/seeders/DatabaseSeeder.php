<?php

namespace Database\Seeders;

use App\Models\Dividend;
use App\Models\User;
use App\Services\CartaoService;
use App\Services\FinanciamentoImovelService;
use App\Services\LancamentoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Cria um usuário de demonstração com carteira e 6 meses de orçamento de exemplo.
     * Login: demo@fantinvest.test / senha: password
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@fantinvest.test'],
            ['name' => 'Conta de demonstração', 'password' => 'password', 'email_verified_at' => now(), 'reserva_atual' => 18000, 'reserva_meses' => 6],
        );

        $user->forceFill(['is_admin' => true])->save();
        $user->dividends()->delete();
        $user->operations()->delete();
        $user->transfers()->delete();
        $user->financings()->delete();
        $user->assets()->delete();
        $user->transactions()->delete();
        $user->cards()->delete();
        $user->accounts()->delete();
        $user->allocationTargets()->delete();
        $user->goals()->delete();

        $corrente = $user->accounts()->create(['nome' => 'Inter', 'tipo' => 'corrente', 'saldo_inicial' => 4200]);
        $poupanca = $user->accounts()->create(['nome' => 'Caixa Poupança', 'tipo' => 'poupanca', 'saldo_inicial' => 18000]);
        $corretora = $user->accounts()->create(['nome' => 'XP Investimentos', 'tipo' => 'corretora', 'saldo_inicial' => 650]);
        $cartao = $user->cards()->create(['nome' => 'Nubank', 'limite' => 8000, 'dia_fechamento' => 3, 'dia_vencimento' => 10, 'account_id' => $corrente->id]);

        foreach (config('financas.metas_padrao') as $classe => $percentual) {
            $user->allocationTargets()->create(['classe' => $classe, 'percentual' => $percentual]);
        }

        $ativos = [
            ['ITSA4', 'acoes', 400, 9.10, 10.45, 9],
            ['TAEE11', 'acoes', 80, 33.20, 35.10, 8],
            ['BBSE3', 'acoes', 60, 31.50, 34.80, 8],
            ['WEGE3', 'acoes', 30, 41.00, 38.70, 7],
            ['HGLG11', 'fii', 20, 158.00, 161.20, 8],
            ['KNRI11', 'fii', 18, 141.00, 138.50, 7],
            ['XPML11', 'fii', 25, 104.00, 101.30, 6],
            ['IVVB11', 'exterior', 25, 290.00, 338.00, 8],
            ['TESOURO IPCA+ 2035', 'rf', 1, 9800, 10650, 8],
            ['CDB 110% CDI', 'rf', 1, 6000, 6420, 7],
        ];
        foreach ($ativos as [$ticker, $classe, $qtd, $pm, $cotacao, $nota]) {
            $user->assets()->create(['ticker' => $ticker, 'classe' => $classe, 'quantidade' => $qtd, 'preco_medio' => $pm, 'cotacao' => $cotacao, 'nota' => $nota]);
        }

        $user->goals()->createMany([
            ['nome' => 'Viagem para o Chile', 'valor_alvo' => 12000, 'valor_atual' => 3500, 'data_alvo' => now()->addMonths(14)->endOfMonth()->toDateString(), 'rendimento_anual' => 10],
            ['nome' => 'Troca do carro', 'valor_alvo' => 45000, 'valor_atual' => 9000, 'data_alvo' => now()->addYears(3)->endOfMonth()->toDateString(), 'rendimento_anual' => 10],
            ['nome' => 'Notebook novo', 'valor_alvo' => 6000, 'valor_atual' => 6000, 'data_alvo' => null, 'rendimento_anual' => 0],
        ]);

        for ($k = 5; $k >= 0; $k--) {
            $mes = Carbon::now()->startOfMonth()->subMonths($k);
            $w = 1 + ($k % 3) * 0.04;
            // Gastos do dia a dia no cartão; contas fixas, salário e aportes na conta corrente.
            $noCartao = ['Mercado', 'Transporte', 'Restaurantes', 'Lazer', 'Assinaturas'];
            $lancar = fn (int $dia, string $tipo, string $categoria, string $descricao, float $valor) => $user->transactions()->create([
                'data' => $mes->copy()->day($dia)->toDateString(),
                'tipo' => $tipo,
                'categoria' => $categoria,
                'descricao' => $descricao,
                'valor' => round($valor, 2),
                'account_id' => in_array($categoria, $noCartao, true) ? null : $corrente->id,
                'card_id' => in_array($categoria, $noCartao, true) ? $cartao->id : null,
                'fatura' => in_array($categoria, $noCartao, true) ? $cartao->faturaDaCompra($mes->copy()->day($dia)) : null,
            ]);

            $lancar(5, 'receita', 'Salário', 'Salário', 8200);
            if ($k % 2 === 0) {
                $lancar(18, 'receita', 'Renda extra', 'Freela', 900 + $k * 120);
            }
            $lancar(15, 'receita', 'Dividendos', 'Proventos', 140 + (5 - $k) * 18);
            $lancar(6, 'despesa', 'Moradia', 'Aluguel', 2100);
            $lancar(10, 'despesa', 'Contas da casa', 'Luz, água e internet', 390 * $w);
            $lancar(12, 'despesa', 'Mercado', 'Supermercado', 1080 * $w);
            $lancar(14, 'despesa', 'Transporte', 'Combustível e app', 420 * $w);
            $lancar(16, 'despesa', 'Saúde', 'Plano de saúde', 380);
            $lancar(20, 'despesa', 'Restaurantes', 'Restaurantes', 520 * (2 - $w));
            $lancar(21, 'despesa', 'Lazer', 'Cinema e passeios', 260 * $w);
            $lancar(22, 'despesa', 'Assinaturas', 'Streaming e apps', 89);
            $lancar(25, 'despesa', 'Investimentos', 'Aporte mensal', 1600);
        }

        // Compras parceladas.
        $lancamentos = app(LancamentoService::class);
        $lancamentos->criar($user, ['tipo' => 'despesa', 'data' => now()->subMonths(2)->day(12)->toDateString(), 'categoria' => 'Compras', 'descricao' => 'Geladeira', 'valor' => 3600, 'parcelas' => 10, 'card_id' => $cartao->id]);
        $lancamentos->criar($user, ['tipo' => 'despesa', 'data' => now()->subMonth()->day(20)->toDateString(), 'categoria' => 'Educação', 'descricao' => 'Curso de inglês', 'valor' => 1800, 'parcelas' => 6, 'card_id' => $cartao->id]);

        // Faturas passadas pagas pela conta corrente.
        foreach (app(CartaoService::class)->faturas($cartao) as $f) {
            if (in_array($f['situacao'], ['fechada', 'atrasada'], true) && $f['falta'] > 0) {
                app(CartaoService::class)->pagar($cartao, $f['fatura'], $corrente, $f['falta'], $f['vencimento']->toDateString());
            }
        }

        // Proventos dos últimos meses.
        foreach ([['ITSA4', 'jcp', 3, 48.0], ['TAEE11', 'dividendo', 2, 92.4], ['HGLG11', 'rendimento', 1, 22.0], ['HGLG11', 'rendimento', 0, 22.0], ['KNRI11', 'rendimento', 0, 18.9], ['BBSE3', 'dividendo', 1, 75.6]] as [$ticker, $tipo, $meses, $valor]) {
            $ativo = $user->assets()->where('ticker', $ticker)->first();
            $data = now()->subMonths($meses)->day(15)->toDateString();
            $receita = $user->transactions()->create(['data' => $data, 'tipo' => 'receita', 'categoria' => 'Dividendos', 'descricao' => Dividend::TIPOS[$tipo].' '.$ticker, 'valor' => $valor, 'account_id' => $corretora->id]);
            $user->dividends()->create(['asset_id' => $ativo->id, 'ticker' => $ticker, 'tipo' => $tipo, 'data' => $data, 'valor' => $valor, 'account_id' => $corretora->id, 'transaction_id' => $receita->id]);
        }

        // Financiamento da casa na Caixa, ainda na fase de obra.
        $casa = $user->financings()->create([
            'nome' => 'Casa própria', 'banco' => 'Caixa', 'valor_imovel' => 380000, 'valor_financiado' => 285000,
            'recursos_proprios' => 95000, 'taxa_juros_anual' => 10.99, 'sistema' => 'sac', 'prazo_meses' => 420,
            'data_contrato' => now()->subMonths(5)->toDateString(), 'primeira_parcela' => now()->subMonths(2)->addDays(5)->toDateString(),
            'seguro_mensal' => 96.4, 'taxa_administracao' => 25, 'observacoes' => 'Contrato de exemplo da conta de demonstração.',
        ]);
        $casa->liberacoes()->createMany([
            ['data' => now()->subMonths(5)->toDateString(), 'valor' => 120000, 'descricao' => 'Terreno + 1ª medição'],
            ['data' => now()->subMonths(3)->toDateString(), 'valor' => 60000, 'descricao' => '2ª medição'],
            ['data' => now()->subMonth()->toDateString(), 'valor' => 45000, 'descricao' => '3ª medição'],
        ]);
        foreach ([
            ['Fundação e estrutura', 'Materiais', 'Casa do Construtor', 38500, 'financiamento', 4],
            ['Pedreiro (empreitada 1ª etapa)', 'Mão de obra', 'Seu Zé', 22000, 'financiamento', 4],
            ['Projeto arquitetônico e ART', 'Projeto e engenharia', 'Studio Arq', 6800, 'proprio', 5],
            ['Registro e ITBI', 'Documentação e cartório', 'Cartório', 9200, 'proprio', 5],
            ['Tijolos, cimento e ferro', 'Materiais', 'Leroy Merlin', 27400, 'financiamento', 2],
            ['Instalação elétrica', 'Mão de obra', 'Eletricista', 7900, 'financiamento', 1],
            ['Porcelanato e revestimentos', 'Acabamento', 'Portobello Shop', 14300, 'proprio', 0],
        ] as [$descricao, $categoria, $fornecedor, $valor, $origem, $meses]) {
            $casa->gastos()->create(['data' => now()->subMonths($meses)->toDateString(), 'descricao' => $descricao, 'categoria' => $categoria, 'fornecedor' => $fornecedor, 'valor' => $valor, 'origem' => $origem]);
        }
        $cronograma = app(FinanciamentoImovelService::class)->cronograma($casa)['linhas'];
        foreach ([0, 1] as $k) {
            $casa->pagamentos()->create(['tipo' => 'parcela', 'numero' => $k + 1, 'data' => $cronograma[$k]['data']->toDateString(), 'valor' => round($cronograma[$k]['total'], 2)]);
        }
    }
}
