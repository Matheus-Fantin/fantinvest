<?php

namespace App\Services;

use App\Models\Account;
use App\Models\NetWorthSnapshot;
use App\Models\User;

/**
 * Tudo o que você tem menos tudo o que deve.
 * Reserva de emergência e objetivos não entram na soma: esse dinheiro já está
 * guardado em alguma conta ou investimento, e somar de novo duplicaria o valor.
 */
class PatrimonioService
{
    public function __construct(
        private ContaService $contas,
        private CarteiraService $carteira,
        private CartaoService $cartoes,
        private FinanciamentoImovelService $financiamentos,
    ) {}

    public function calcular(User $user): array
    {
        $contas = $this->contas->comSaldos($user);
        $resumo = $this->carteira->resumo($user->assets()->get());
        $classes = config('financas.classes');

        $bens = [];
        foreach ($contas->groupBy('tipo') as $tipo => $grupo) {
            $bens[] = ['grupo' => 'Contas', 'nome' => Account::TIPOS[$tipo] ?? $tipo, 'valor' => $grupo->sum('saldo')];
        }
        foreach ($resumo['por_classe'] as $classe => $valor) {
            if ($valor > 0) {
                $bens[] = ['grupo' => 'Investimentos', 'nome' => $classes[$classe]['nome'], 'valor' => $valor];
            }
        }

        $dividas = [];
        foreach ($user->cards()->get() as $card) {
            $usado = $this->cartoes->limiteUsado($card);
            if ($usado > 0) {
                $dividas[] = ['grupo' => 'Cartões', 'nome' => 'Cartão '.$card->nome, 'valor' => $usado];
            }
        }
        $parcelasConta = (float) $user->transactions()->where('tipo', 'despesa')->whereNull('card_id')
            ->whereNotNull('parcelamento_id')->where('data', '>', now()->endOfDay()->toDateTimeString())->sum('valor');
        if ($parcelasConta > 0) {
            $dividas[] = ['grupo' => 'Parcelas', 'nome' => 'Parcelas futuras em boleto/conta', 'valor' => $parcelasConta];
        }
        foreach ($user->financings()->with('pagamentos')->get() as $f) {
            if ($f->valor_imovel > 0) {
                $bens[] = ['grupo' => 'Imóveis', 'nome' => $f->nome, 'valor' => $f->valor_imovel];
            }
            $saldo = $this->financiamentos->cronograma($f)['resumo']['saldo_devedor'];
            if ($saldo > 0) {
                $dividas[] = ['grupo' => 'Financiamentos', 'nome' => $f->nome.' ('.$f->banco.')', 'valor' => $saldo];
            }
        }

        $totalBens = array_sum(array_column($bens, 'valor'));
        $totalDividas = array_sum(array_column($dividas, 'valor'));

        $inicio12 = now()->startOfMonth()->subMonths(11)->toDateString();
        $dividendos12 = (float) $user->dividends()->where('data', '>=', $inicio12)->where('data', '<=', now()->endOfDay()->toDateTimeString())->sum('valor');

        return [
            'bens' => $bens,
            'dividas' => $dividas,
            'total_bens' => $totalBens,
            'total_dividas' => $totalDividas,
            'liquido' => $totalBens - $totalDividas,
            'liquidez' => $contas->sum('saldo'),
            'investido' => $resumo['total'],
            'dividendos_12m' => $dividendos12,
            'renda_passiva_mes' => $dividendos12 / 12,
            'reserva' => $user->reserva_atual,
            'objetivos' => (float) $user->goals()->sum('valor_atual'),
        ];
    }

    /** Guarda a foto do mês atual (uma por mês, sempre a mais recente) e devolve a evolução. */
    public function registrarEvolucao(User $user, array $calculo): array
    {
        NetWorthSnapshot::updateOrCreate(
            ['user_id' => $user->id, 'mes' => now()->format('Y-m')],
            ['ativos' => round($calculo['total_bens'], 2), 'dividas' => round($calculo['total_dividas'], 2)],
        );

        return NetWorthSnapshot::where('user_id', $user->id)->orderBy('mes')->limit(36)->get()
            ->map(fn ($s) => ['mes' => $s->mes, 'liquido' => $s->ativos - $s->dividas, 'ativos' => $s->ativos, 'dividas' => $s->dividas])
            ->all();
    }
}
