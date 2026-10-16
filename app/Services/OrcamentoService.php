<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OrcamentoService
{
    /**
     * Totais de um mês. "Gastos" não incluem investimentos;
     * a sobra é o que fica depois de gastos e investimentos.
     *
     * @param  Collection<int, Transaction>  $lancamentos
     */
    public function resumo(Collection $lancamentos): array
    {
        $r = ['receitas' => 0.0, 'gastos' => 0.0, 'ess' => 0.0, 'des' => 0.0, 'inv' => 0.0, 'categorias' => []];

        foreach ($lancamentos as $t) {
            if ($t->tipo === 'receita') {
                $r['receitas'] += $t->valor;

                continue;
            }
            $grupo = $t->grupo();
            $r[$grupo] += $t->valor;
            if ($grupo !== 'inv') {
                $r['gastos'] += $t->valor;
                $r['categorias'][$t->categoria] = ($r['categorias'][$t->categoria] ?? 0) + $t->valor;
            }
        }

        arsort($r['categorias']);
        $r['sobra'] = $r['receitas'] - $r['gastos'] - $r['inv'];
        $r['taxa_poupanca'] = $r['receitas'] > 0 ? ($r['inv'] + max(0, $r['sobra'])) / $r['receitas'] * 100 : 0;

        return $r;
    }

    public function resumoDoMes(User $user, string $mes): array
    {
        return $this->resumo($user->transactions()->doMes($mes)->get());
    }

    /** Receitas e gastos dos últimos N meses, terminando no mês informado. */
    public function historico(User $user, string $ateMes, int $meses = 6): array
    {
        $fim = Carbon::createFromFormat('Y-m-d', $ateMes.'-01')->startOfMonth();
        $inicio = $fim->copy()->subMonths($meses - 1);

        $lancamentos = $user->transactions()
            ->whereBetween('data', [$inicio->toDateString(), $fim->copy()->endOfMonth()->toDateTimeString()])
            ->get()
            ->groupBy(fn (Transaction $t) => $t->data->format('Y-m'));

        $saida = [];
        for ($m = $inicio->copy(); $m <= $fim; $m->addMonth()) {
            $chave = $m->format('Y-m');
            $resumo = $this->resumo($lancamentos->get($chave, collect()));
            $saida[] = ['mes' => $chave, 'receitas' => $resumo['receitas'], 'gastos' => $resumo['gastos']];
        }

        return $saida;
    }
}
