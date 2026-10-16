<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Card;
use App\Models\Transfer;
use Illuminate\Support\Carbon;

class CartaoService
{
    /**
     * Faturas do cartão, da mais antiga à mais nova, com total, pago e situação:
     * aberta (ainda recebendo compras), fechada (aguardando pagamento), atrasada,
     * paga ou futura (só parcelas que ainda vão cair).
     *
     * @return array<string, array{fatura: string, total: float, pago: float, falta: float, situacao: string, fechamento: Carbon, vencimento: Carbon, compras: int}>
     */
    public function faturas(Card $card, ?Carbon $hoje = null): array
    {
        $hoje ??= now();
        $atual = $card->faturaDaCompra($hoje);

        $totais = $card->transactions()->whereNotNull('fatura')
            ->selectRaw("fatura, sum(case when tipo = 'despesa' then valor else -valor end) as total, count(*) as compras")
            ->groupBy('fatura')->get()->keyBy('fatura');
        $pagos = $card->pagamentos()->selectRaw('fatura, sum(valor) as total')->groupBy('fatura')->pluck('total', 'fatura');

        $chaves = collect($totais->keys())->merge($pagos->keys())->push($atual)->unique()->sort()->values();

        $faturas = [];
        foreach ($chaves as $f) {
            $total = round((float) ($totais[$f]->total ?? 0), 2);
            $pago = round((float) ($pagos[$f] ?? 0), 2);
            $vencimento = $card->dataVencimento($f);
            $falta = max(0, round($total - $pago, 2));

            $situacao = match (true) {
                $f > $atual => 'futura',
                $f === $atual => 'aberta',
                $falta <= 0.009 => 'paga',
                $hoje->copy()->startOfDay()->gt($vencimento) => 'atrasada',
                default => 'fechada',
            };

            $faturas[$f] = [
                'fatura' => $f,
                'total' => $total,
                'pago' => $pago,
                'falta' => $falta,
                'situacao' => $situacao,
                'fechamento' => $card->dataFechamento($f),
                'vencimento' => $vencimento,
                'compras' => (int) ($totais[$f]->compras ?? 0),
            ];
        }

        return $faturas;
    }

    /** Quanto do limite está tomado: tudo o que foi comprado (inclusive parcelas futuras) menos o que já foi pago. */
    public function limiteUsado(Card $card): float
    {
        $compras = (float) $card->transactions()->selectRaw("sum(case when tipo = 'despesa' then valor else -valor end) as t")->value('t');
        $pagos = (float) $card->pagamentos()->sum('valor');

        return max(0, round($compras - $pagos, 2));
    }

    /** Faturas que ainda não foram pagas até a fatura atual (fechadas, atrasadas e a aberta). */
    public function emAberto(Card $card): float
    {
        return collect($this->faturas($card))->whereIn('situacao', ['aberta', 'fechada', 'atrasada'])->sum('falta');
    }

    /** Pagamento de fatura: sai da conta, não é gasto (as compras já foram contadas). */
    public function pagar(Card $card, string $fatura, ?Account $conta, float $valor, string $data): Transfer
    {
        return Transfer::create([
            'user_id' => $card->user_id,
            'de_account_id' => $conta?->id,
            'card_id' => $card->id,
            'fatura' => $fatura,
            'data' => $data,
            'valor' => round($valor, 2),
            'descricao' => 'Pagamento da fatura '.$card->nome.' '.mes_extenso($fatura),
        ]);
    }
}
