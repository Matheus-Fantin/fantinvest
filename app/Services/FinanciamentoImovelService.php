<?php

namespace App\Services;

use App\Models\Financing;
use Illuminate\Support\Carbon;

/**
 * Cronograma do financiamento habitacional (SAC ou Price), com seguro e taxa de
 * administração mensais fixos, parcelas pagas e amortizações extras.
 *
 * Amortização extra reduzindo o PRAZO mantém a amortização (SAC) ou a parcela (Price)
 * e o contrato termina antes. Reduzindo a PARCELA mantém o prazo e recalcula o valor.
 * A correção pela TR não é considerada (ela está perto de zero há anos).
 */
class FinanciamentoImovelService
{
    public const CATEGORIAS_OBRA = [
        'Materiais', 'Mão de obra', 'Projeto e engenharia', 'Documentação e cartório',
        'Taxas da Caixa', 'Acabamento', 'Móveis e eletros', 'Outros',
    ];

    public function cronograma(Financing $f, ?Carbon $hoje = null): array
    {
        $hoje ??= now();
        $i = $f->taxaMensal();
        $n = max(1, $f->prazo_meses);
        $pagamentos = $f->relationLoaded('pagamentos') ? $f->pagamentos : $f->pagamentos()->get();

        $pagas = $pagamentos->where('tipo', 'parcela')->keyBy('numero');
        $extras = $pagamentos->where('tipo', 'amortizacao')->groupBy(fn ($p) => $this->numeroDaParcela($f, $p->data));

        $saldo = $f->valor_financiado;
        $amortSac = $saldo / $n;
        $pmt = $this->prestacao($saldo, $i, $n);

        $linhas = [];
        for ($k = 1; $k <= $n && $saldo > 0.005; $k++) {
            $juros = $saldo * $i;
            if ($f->sistema === 'sac') {
                $amort = min($saldo, $amortSac);
                $prestacao = $amort + $juros;
            } else {
                $prestacao = min($pmt, $saldo + $juros);
                $amort = $prestacao - $juros;
            }
            $saldo = max(0, $saldo - $amort);
            $data = $f->primeira_parcela->copy()->addMonthsNoOverflow($k - 1);
            $pagamento = $pagas->get($k);

            $extra = 0.0;
            foreach ($extras->get($k, collect()) as $e) {
                $extra += min($saldo, $e->valor);
                $saldo = max(0, $saldo - $e->valor);
                if ($e->reduzir === 'parcela' && $n - $k > 0) {
                    $amortSac = $saldo / ($n - $k);
                    $pmt = $this->prestacao($saldo, $i, $n - $k);
                }
            }

            $linhas[] = [
                'numero' => $k,
                'data' => $data,
                'amortizacao' => $amort,
                'juros' => $juros,
                'prestacao' => $prestacao,
                'seguro' => $f->seguro_mensal,
                'taxa' => $f->taxa_administracao,
                'total' => $prestacao + $f->seguro_mensal + $f->taxa_administracao,
                'amortizacao_extra' => $extra,
                'saldo' => $saldo,
                'pago' => (bool) $pagamento,
                'valor_pago' => $pagamento?->valor,
                'data_pagamento' => $pagamento?->data,
                'atrasada' => ! $pagamento && $data->lt($hoje->copy()->startOfDay()),
            ];
        }

        return ['linhas' => $linhas, 'resumo' => $this->resumo($f, $linhas, $pagamentos)];
    }

    private function resumo(Financing $f, array $linhas, $pagamentos): array
    {
        $pagas = array_values(array_filter($linhas, fn ($l) => $l['pago']));
        $abertas = array_values(array_filter($linhas, fn ($l) => ! $l['pago']));
        $proxima = $abertas[0] ?? null;

        $extras = $pagamentos->where('tipo', 'amortizacao')->sum('valor');
        $saldoAtual = max(0, $f->valor_financiado - array_sum(array_column($pagas, 'amortizacao')) - $extras);

        return [
            'parcelas_total' => count($linhas),
            'parcelas_pagas' => count($pagas),
            'parcelas_atrasadas' => count(array_filter($linhas, fn ($l) => $l['atrasada'])),
            'proxima' => $proxima,
            'saldo_devedor' => $saldoAtual,
            'total_pago' => $pagamentos->sum('valor'),
            'pago_parcelas' => $pagamentos->where('tipo', 'parcela')->sum('valor'),
            'pago_extras' => $extras,
            'pago_obra' => $pagamentos->where('tipo', 'obra')->sum('valor'),
            'juros_pagos' => array_sum(array_column($pagas, 'juros')),
            'juros_a_pagar' => array_sum(array_column($abertas, 'juros')),
            'total_contrato' => array_sum(array_column($linhas, 'total')),
            'juros_contrato' => array_sum(array_column($linhas, 'juros')),
            'quitacao' => $linhas ? end($linhas)['data'] : null,
            'meses_economizados' => max(0, $f->prazo_meses - count($linhas)),
            'percentual_quitado' => $f->valor_financiado > 0 ? (1 - $saldoAtual / $f->valor_financiado) * 100 : 0,
        ];
    }

    /** Situação da obra: quanto o banco liberou, quanto foi gasto e com que dinheiro. */
    public function obra(Financing $f): array
    {
        $liberado = (float) $f->liberacoes()->sum('valor');
        $gastos = $f->gastos()->get();
        $gastoFinanciamento = $gastos->where('origem', 'financiamento')->sum('valor');
        $gastoProprio = $gastos->where('origem', 'proprio')->sum('valor');

        $porCategoria = $gastos->groupBy('categoria')->map->sum('valor')->sortDesc();

        return [
            'liberado' => $liberado,
            'a_liberar' => max(0, $f->valor_financiado - $liberado),
            'gasto_total' => $gastos->sum('valor'),
            'gasto_financiamento' => $gastoFinanciamento,
            'gasto_proprio' => $gastoProprio,
            'saldo_liberado' => $liberado - $gastoFinanciamento,
            'proprio_restante' => $f->recursos_proprios - $gastoProprio,
            'por_categoria' => $porCategoria->map(fn ($v, $c) => ['nome' => $c, 'valor' => round($v, 2)])->values()->all(),
        ];
    }

    /** Em qual parcela (1, 2, …) cai uma data, contando a partir da primeira parcela. */
    public function numeroDaParcela(Financing $f, Carbon|string $data): int
    {
        $data = Carbon::parse($data)->startOfMonth();
        $inicio = $f->primeira_parcela->copy()->startOfMonth();

        return max(1, (int) $inicio->diffInMonths($data, false) + 1);
    }

    private function prestacao(float $pv, float $i, int $n): float
    {
        return $i > 0 ? $pv * $i / (1 - (1 + $i) ** -$n) : $pv / max(1, $n);
    }
}
