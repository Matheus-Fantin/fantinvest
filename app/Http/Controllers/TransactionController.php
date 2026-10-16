<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Services\OrcamentoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request, OrcamentoService $orcamento): View
    {
        $mes = $request->query('mes');
        if (! is_string($mes) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $mes = now()->format('Y-m');
        }
        $base = Carbon::createFromFormat('Y-m-d', $mes.'-01');

        $lancamentos = $request->user()->transactions()->doMes($mes)->orderByDesc('data')->orderByDesc('id')->get();

        return view('orcamento.index', [
            'mes' => $mes,
            'anterior' => $base->copy()->subMonth()->format('Y-m'),
            'proximo' => $base->copy()->addMonth()->format('Y-m'),
            'lancamentos' => $lancamentos,
            'resumo' => $orcamento->resumo($lancamentos),
            'tipo' => $request->query('tipo') === 'receita' ? 'receita' : 'despesa',
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $lancamento = $request->user()->transactions()->create($request->validated());

        return redirect()->route('orcamento.index', ['mes' => $lancamento->data->format('Y-m'), 'tipo' => $lancamento->tipo])
            ->with('sucesso', 'Lançamento adicionado.');
    }

    public function update(StoreTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $transaction);

        $transaction->update($request->validated());

        return redirect()->route('orcamento.index', ['mes' => $transaction->data->format('Y-m')])
            ->with('sucesso', 'Lançamento atualizado.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        Gate::authorize('delete', $transaction);

        $transaction->delete();

        return back()->with('sucesso', 'Lançamento excluído.');
    }
}
