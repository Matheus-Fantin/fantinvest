<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Services\CartaoService;
use App\Services\LancamentoService;
use App\Services\OrcamentoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request, OrcamentoService $orcamento, LancamentoService $lancamentos, CartaoService $cartoes): View
    {
        $mes = $request->query('mes');
        if (! is_string($mes) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $mes = now()->format('Y-m');
        }
        $base = Carbon::createFromFormat('Y-m-d', $mes.'-01');
        $user = $request->user();

        $lista = $user->transactions()->with(['account', 'card'])->doMes($mes)->orderByDesc('data')->orderByDesc('id')->get();

        return view('orcamento.index', [
            'mes' => $mes,
            'anterior' => $base->copy()->subMonth()->format('Y-m'),
            'proximo' => $base->copy()->addMonth()->format('Y-m'),
            'lancamentos' => $lista,
            'resumo' => $orcamento->resumo($lista),
            'tipo' => $request->query('tipo') === 'receita' ? 'receita' : 'despesa',
            'contas' => $user->accounts()->where('arquivada', false)->orderBy('nome')->get(),
            'cartoes' => $user->cards()->orderBy('nome')->get(),
            'faturas' => $user->cards()->orderBy('nome')->get()->map(fn ($c) => [
                'cartao' => $c,
                'atual' => $cartoes->faturas($c)[$c->faturaDaCompra(now())],
                'em_aberto' => $cartoes->emAberto($c),
            ]),
            'compromissos' => $lancamentos->compromissosFuturos($user),
        ]);
    }

    public function store(StoreTransactionRequest $request, LancamentoService $lancamentos): RedirectResponse
    {
        $criados = $lancamentos->criar($request->user(), $request->validated());
        $primeiro = $criados->first();

        $mensagem = $criados->count() > 1
            ? "Compra lançada em {$criados->count()} parcelas de ".brl($criados->last()->valor).'.'
            : 'Lançamento adicionado.';

        return redirect()->route('orcamento.index', ['mes' => $primeiro->data->format('Y-m'), 'tipo' => $primeiro->tipo])
            ->with('sucesso', $mensagem);
    }

    public function update(StoreTransactionRequest $request, Transaction $transaction, LancamentoService $lancamentos): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $lancamentos->atualizar($transaction, $request->validated());

        return redirect()->route('orcamento.index', ['mes' => $transaction->data->format('Y-m')])
            ->with('sucesso', 'Lançamento atualizado.');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('delete', $transaction);

        if ($request->boolean('todas') && $transaction->parcelamento_id) {
            $quantidade = $request->user()->transactions()->where('parcelamento_id', $transaction->parcelamento_id)->delete();

            return back()->with('sucesso', "Compra parcelada excluída ($quantidade parcelas).");
        }

        $transaction->delete();

        return back()->with('sucesso', 'Lançamento excluído.');
    }
}
