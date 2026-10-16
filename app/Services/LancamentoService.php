<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cria lançamentos do orçamento: à vista ou parcelados, numa conta ou num cartão.
 * Uma compra parcelada vira N lançamentos ligados pelo mesmo parcelamento_id,
 * um por mês, cada um na fatura certa quando é no cartão.
 */
class LancamentoService
{
    /**
     * @param  array{tipo: string, data: string, categoria: string, descricao?: ?string, valor: float|string, account_id?: ?int, card_id?: ?int, parcelas?: ?int, external_id?: ?string}  $dados
     * @return Collection<int, Transaction>
     */
    public function criar(User $user, array $dados): Collection
    {
        $parcelas = $dados['tipo'] === 'despesa' ? max(1, (int) ($dados['parcelas'] ?? 1)) : 1;
        $card = ! empty($dados['card_id']) ? $user->cards()->findOrFail($dados['card_id']) : null;
        $total = round((float) $dados['valor'], 2);
        $data = Carbon::parse($dados['data']);

        // Divide em centavos; a diferença do arredondamento vai na primeira parcela.
        $base = floor($total / $parcelas * 100) / 100;
        $primeira = round($total - $base * ($parcelas - 1), 2);
        $grupo = $parcelas > 1 ? (string) Str::uuid() : null;
        $faturaInicial = $card?->faturaDaCompra($data);

        return DB::transaction(function () use ($user, $dados, $parcelas, $card, $base, $primeira, $grupo, $faturaInicial, $data) {
            $criados = collect();
            for ($k = 0; $k < $parcelas; $k++) {
                $criados->push($user->transactions()->create([
                    'tipo' => $dados['tipo'],
                    'data' => $data->copy()->addMonthsNoOverflow($k)->toDateString(),
                    'categoria' => $dados['categoria'],
                    'descricao' => $dados['descricao'] ?? null,
                    'valor' => $k === 0 ? $primeira : $base,
                    'account_id' => $card ? null : ($dados['account_id'] ?? null),
                    'card_id' => $card?->id,
                    'fatura' => $faturaInicial ? Carbon::createFromFormat('Y-m-d', $faturaInicial.'-01')->addMonths($k)->format('Y-m') : null,
                    'parcelamento_id' => $grupo,
                    'parcela' => $parcelas > 1 ? $k + 1 : null,
                    'parcelas_total' => $parcelas > 1 ? $parcelas : null,
                    'external_id' => $dados['external_id'] ?? null,
                ]));
            }

            return $criados;
        });
    }

    /** Atualiza um lançamento; se mudar de cartão ou de data, recalcula a fatura. */
    public function atualizar(Transaction $t, array $dados): Transaction
    {
        $card = ! empty($dados['card_id']) ? Card::where('user_id', $t->user_id)->findOrFail($dados['card_id']) : null;

        $t->fill([
            'tipo' => $dados['tipo'],
            'data' => $dados['data'],
            'categoria' => $dados['categoria'],
            'descricao' => $dados['descricao'] ?? null,
            'valor' => $dados['valor'],
            'account_id' => $card ? null : ($dados['account_id'] ?? null),
            'card_id' => $card?->id,
        ]);
        if (! $card) {
            $t->fatura = null;
        } elseif ($t->isDirty(['card_id', 'data']) || ! $t->fatura) {
            $t->fatura = $card->faturaDaCompra($dados['data']);
        }
        $t->save();

        return $t;
    }

    /**
     * Gastos já comprometidos nos próximos meses: parcelas futuras (cartão e boleto)
     * e parcelas previstas dos financiamentos.
     *
     * @return array<int, array{mes: string, parcelas: float, financiamento: float}>
     */
    public function compromissosFuturos(User $user, int $meses = 12): array
    {
        $inicio = now()->startOfMonth();
        $fim = $inicio->copy()->addMonths($meses - 1)->endOfMonth();

        $porMes = [];
        for ($m = $inicio->copy(); $m <= $fim; $m->addMonth()) {
            $porMes[$m->format('Y-m')] = ['mes' => $m->format('Y-m'), 'parcelas' => 0.0, 'financiamento' => 0.0];
        }

        $user->transactions()->where('tipo', 'despesa')->whereNotNull('parcelamento_id')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateTimeString()])
            ->get(['data', 'valor'])
            ->each(function ($t) use (&$porMes) {
                $porMes[$t->data->format('Y-m')]['parcelas'] += $t->valor;
            });

        $financiamentos = app(FinanciamentoImovelService::class);
        foreach ($user->financings()->with('pagamentos')->get() as $f) {
            foreach ($financiamentos->cronograma($f)['linhas'] as $linha) {
                $mes = $linha['data']->format('Y-m');
                if (isset($porMes[$mes]) && ! $linha['pago']) {
                    $porMes[$mes]['financiamento'] += $linha['total'];
                }
            }
        }

        return array_values(array_map(fn ($m) => [
            'mes' => $m['mes'],
            'parcelas' => round($m['parcelas'], 2),
            'financiamento' => round($m['financiamento'], 2),
        ], $porMes));
    }
}
