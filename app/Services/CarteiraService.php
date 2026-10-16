<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CarteiraService
{
    /**
     * Totais da carteira: valor de mercado, custo e valor por classe.
     *
     * @param  Collection<int, Asset>  $ativos
     */
    public function resumo(Collection $ativos): array
    {
        $porClasse = array_fill_keys(array_keys(config('financas.classes')), 0.0);

        foreach ($ativos as $ativo) {
            $porClasse[$ativo->classe] = ($porClasse[$ativo->classe] ?? 0) + $ativo->valor();
        }

        $total = array_sum($porClasse);
        $custo = $ativos->sum(fn (Asset $a) => $a->custo());

        return [
            'total' => $total,
            'custo' => $custo,
            'resultado' => $total - $custo,
            'por_classe' => $porClasse,
            'percentual' => array_map(fn ($v) => $total > 0 ? $v / $total * 100 : 0, $porClasse),
        ];
    }

    /**
     * Regra de aporte:
     * 1. O valor vai para as classes mais abaixo da meta (proporcional à diferença).
     * 2. Dentro da classe, o peso ideal de cada ativo é proporcional à nota,
     *    e quem está mais abaixo do peso recebe primeiro.
     * 3. Ativos de cota inteira compram cotas inteiras; a sobra tenta mais uma cota.
     * Nada é vendido. Ativos com nota 0 ou sem cotação ficam de fora.
     *
     * @param  Collection<int, Asset>  $ativos
     * @param  array<string, float>  $metas
     */
    public function planoAporte(Collection $ativos, array $metas, float $valor): array
    {
        $resumo = $this->resumo($ativos);
        $depois = $resumo['total'] + $valor;
        $elegiveis = $ativos->filter(fn (Asset $a) => $a->nota > 0 && $a->cotacao > 0);

        $classes = [];
        foreach (config('financas.classes') as $id => $info) {
            $meta = ($metas[$id] ?? 0) / 100;
            $atual = $resumo['por_classe'][$id] ?? 0;
            $temAtivo = $elegiveis->contains('classe', $id);
            $classes[$id] = [
                'id' => $id,
                'nome' => $info['nome'],
                'cor' => $info['cor'],
                'meta' => $meta,
                'atual' => $atual,
                'elegivel' => $temAtivo,
                'gap' => $temAtivo ? max(0, $meta * $depois - $atual) : 0,
                'valor' => 0.0,
            ];
        }

        $somaGaps = array_sum(array_column($classes, 'gap'));
        if ($somaGaps > 0) {
            foreach ($classes as &$c) {
                $c['valor'] = $valor * $c['gap'] / $somaGaps;
            }
        } else {
            // Todas as classes já estão na meta: divide pelo percentual da meta.
            $somaMetas = array_sum(array_map(fn ($c) => $c['elegivel'] ? $c['meta'] : 0, $classes));
            foreach ($classes as &$c) {
                $c['valor'] = $somaMetas > 0 && $c['elegivel'] ? $valor * $c['meta'] / $somaMetas : 0;
            }
        }
        unset($c);

        $compras = [];
        $usado = 0.0;
        foreach ($classes as $c) {
            if ($c['valor'] <= 0) {
                continue;
            }
            $daClasse = $elegiveis->where('classe', $c['id'])->values();
            $somaNotas = $daClasse->sum('nota');
            $classeDepois = $c['atual'] + $c['valor'];
            $fracionavel = config("financas.classes.{$c['id']}.fracionavel");

            $gaps = $daClasse->map(fn (Asset $a) => [
                'ativo' => $a,
                'gap' => max(0, $classeDepois * $a->nota / $somaNotas - $a->valor()),
            ]);
            $somaG = $gaps->sum('gap');
            if ($somaG <= 0) {
                $gaps = $daClasse->map(fn (Asset $a) => ['ativo' => $a, 'gap' => $a->nota]);
                $somaG = $somaNotas;
            }

            foreach ($gaps as $g) {
                $ideal = $c['valor'] * $g['gap'] / $somaG;
                if ($ideal <= 0.009) {
                    continue;
                }
                $ativo = $g['ativo'];
                if ($fracionavel) {
                    $compras[] = ['ativo' => $ativo, 'cotas' => null, 'valor' => $ideal, 'ideal' => $ideal];
                    $usado += $ideal;
                } else {
                    $cotas = (int) floor($ideal / $ativo->cotacao);
                    $compras[] = ['ativo' => $ativo, 'cotas' => $cotas, 'valor' => $cotas * $ativo->cotacao, 'ideal' => $ideal];
                    $usado += $cotas * $ativo->cotacao;
                }
            }
        }

        // A sobra compra mais uma cota de quem ficou mais longe do ideal, enquanto couber.
        $sobra = $valor - $usado;
        for ($i = 0; $i < 50 && $sobra > 0; $i++) {
            $melhor = null;
            foreach ($compras as $k => $x) {
                if ($x['cotas'] === null || $x['ativo']->cotacao > $sobra || $x['ideal'] - $x['valor'] <= 0) {
                    continue;
                }
                if ($melhor === null || ($x['ideal'] - $x['valor']) > ($compras[$melhor]['ideal'] - $compras[$melhor]['valor'])) {
                    $melhor = $k;
                }
            }
            if ($melhor === null) {
                break;
            }
            $compras[$melhor]['cotas']++;
            $compras[$melhor]['valor'] += $compras[$melhor]['ativo']->cotacao;
            $sobra -= $compras[$melhor]['ativo']->cotacao;
        }

        $compras = array_values(array_filter($compras, fn ($x) => $x['valor'] > 0.009));
        usort($compras, fn ($a, $b) => $b['valor'] <=> $a['valor']);

        $realPorClasse = [];
        foreach ($compras as $x) {
            $realPorClasse[$x['ativo']->classe] = ($realPorClasse[$x['ativo']->classe] ?? 0) + $x['valor'];
        }
        $totalDepois = $resumo['total'] + ($valor - $sobra);
        foreach ($classes as &$c) {
            $c['real'] = $realPorClasse[$c['id']] ?? 0;
            $c['pct_atual'] = $resumo['total'] > 0 ? $c['atual'] / $resumo['total'] * 100 : 0;
            $c['pct_depois'] = $totalDepois > 0 ? ($c['atual'] + $c['real']) / $totalDepois * 100 : 0;
        }
        unset($c);

        return [
            'classes' => array_values($classes),
            'compras' => $compras,
            'sobra' => max(0, $sobra),
            'investido' => $valor - max(0, $sobra),
        ];
    }

    /**
     * Aplica o plano na carteira (quantidade e preço médio) e lança o aporte no orçamento.
     */
    public function registrarAporte(User $user, array $plano, ?int $contaId = null): float
    {
        return DB::transaction(function () use ($user, $plano, $contaId) {
            $total = 0.0;

            foreach ($plano['compras'] as $compra) {
                /** @var Asset $ativo */
                $ativo = $compra['ativo'];
                // Cotas inteiras (B3) ou valor livre (renda fixa e cripto).
                $quantidade = $compra['cotas'] ?? $compra['valor'] / $ativo->cotacao;
                $this->aplicarOperacao($ativo, 'compra', now()->toDateString(), $quantidade, $ativo->cotacao, 0, $contaId);
                $total += $compra['valor'];
            }

            if ($total > 0) {
                $user->transactions()->create([
                    'data' => now()->toDateString(),
                    'tipo' => 'despesa',
                    'categoria' => 'Investimentos',
                    'descricao' => 'Aporte na carteira',
                    'valor' => round($total, 2),
                    'account_id' => $contaId,
                ]);
            }

            return $total;
        });
    }

    /**
     * Registra uma compra ou venda e atualiza a posição do ativo.
     * Compra: o preço médio vira a média ponderada (taxas entram no custo).
     * Venda: a quantidade cai, o preço médio se mantém e o lucro realizado fica na operação.
     */
    public function aplicarOperacao(Asset $ativo, string $tipo, string $data, float $quantidade, float $preco, float $taxas = 0, ?int $contaId = null): Operation
    {
        if ($tipo === 'venda' && $quantidade > $ativo->quantidade + 1e-9) {
            throw new \InvalidArgumentException('Você tem '.numero($ativo->quantidade, 8)." de {$ativo->ticker}; não dá para vender mais que isso.");
        }

        $resultado = null;
        if ($tipo === 'compra') {
            $novaQtd = $ativo->quantidade + $quantidade;
            $ativo->preco_medio = $novaQtd > 0 ? ($ativo->quantidade * $ativo->preco_medio + $quantidade * $preco + $taxas) / $novaQtd : 0;
            $ativo->quantidade = $novaQtd;
        } else {
            $resultado = round(($preco - $ativo->preco_medio) * $quantidade - $taxas, 2);
            $ativo->quantidade = max(0, $ativo->quantidade - $quantidade);
        }
        $ativo->save();

        return $ativo->user->operations()->create([
            'asset_id' => $ativo->id,
            'tipo' => $tipo,
            'data' => $data,
            'quantidade' => $quantidade,
            'preco' => $preco,
            'taxas' => $taxas,
            'resultado' => $resultado,
            'account_id' => $contaId,
        ]);
    }
}
