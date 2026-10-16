<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Saldo de cada conta = saldo inicial + receitas − gastos (lançados na conta)
 * + transferências recebidas − transferências enviadas (inclui pagamento de fatura)
 * + vendas de ativos creditadas na conta. Só conta movimentos até hoje.
 */
class ContaService
{
    /** @return Collection<int, Account> contas com o atributo "saldo" preenchido */
    public function comSaldos(User $user, bool $incluirArquivadas = false): Collection
    {
        $hoje = now()->endOfDay()->toDateTimeString(); // datas gravadas com hora: inclui o dia de hoje inteiro
        $contas = $user->accounts()->when(! $incluirArquivadas, fn ($q) => $q->where('arquivada', false))->orderBy('nome')->get();

        $lancamentos = $user->transactions()->whereNotNull('account_id')->where('data', '<=', $hoje)
            ->selectRaw("account_id, sum(case when tipo = 'receita' then valor else -valor end) as total")
            ->groupBy('account_id')->pluck('total', 'account_id');

        $entradas = $user->transfers()->whereNotNull('para_account_id')->where('data', '<=', $hoje)
            ->selectRaw('para_account_id as conta, sum(valor) as total')->groupBy('para_account_id')->pluck('total', 'conta');

        $saidas = $user->transfers()->whereNotNull('de_account_id')->where('data', '<=', $hoje)
            ->selectRaw('de_account_id as conta, sum(valor) as total')->groupBy('de_account_id')->pluck('total', 'conta');

        $vendas = $user->operations()->where('tipo', 'venda')->whereNotNull('account_id')->where('data', '<=', $hoje)
            ->selectRaw('account_id, sum(quantidade * preco - taxas) as total')->groupBy('account_id')->pluck('total', 'account_id');

        return $contas->each(function (Account $c) use ($lancamentos, $entradas, $saidas, $vendas) {
            $c->saldo = round($c->saldo_inicial
                + (float) ($lancamentos[$c->id] ?? 0)
                + (float) ($entradas[$c->id] ?? 0)
                - (float) ($saidas[$c->id] ?? 0)
                + (float) ($vendas[$c->id] ?? 0), 2);
        });
    }

    public function saldoTotal(User $user): float
    {
        return $this->comSaldos($user)->sum('saldo');
    }
}
