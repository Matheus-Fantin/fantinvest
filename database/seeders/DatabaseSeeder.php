<?php

namespace Database\Seeders;

use App\Models\User;
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

        $user->assets()->delete();
        $user->transactions()->delete();
        $user->allocationTargets()->delete();
        $user->goals()->delete();

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
            $lancar = fn (int $dia, string $tipo, string $categoria, string $descricao, float $valor) => $user->transactions()->create([
                'data' => $mes->copy()->day($dia)->toDateString(),
                'tipo' => $tipo,
                'categoria' => $categoria,
                'descricao' => $descricao,
                'valor' => round($valor, 2),
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
    }
}
