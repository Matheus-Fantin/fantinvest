<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transfer;
use App\Services\CartaoService;
use App\Services\ContaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request, ContaService $contas, CartaoService $cartoes): View
    {
        $user = $request->user();
        $lista = $contas->comSaldos($user, incluirArquivadas: true);

        return view('contas.index', [
            'contas' => $lista,
            'total' => $lista->where('arquivada', false)->sum('saldo'),
            'transferencias' => $user->transfers()->with(['de', 'para', 'card'])->orderByDesc('data')->orderByDesc('id')->limit(30)->get(),
            'cartoes' => $user->cards()->get()->each(fn ($c) => $c->em_aberto = $cartoes->emAberto($c)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $conta = $request->user()->accounts()->create($this->validar($request));

        return back()->with('sucesso', "Conta \"{$conta->nome}\" criada.");
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        Gate::authorize('update', $account);

        $account->update($this->validar($request) + ['arquivada' => $request->boolean('arquivada')]);

        return back()->with('sucesso', "Conta \"{$account->nome}\" atualizada.");
    }

    public function destroy(Account $account): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $usada = $account->transactions()->exists()
            || Transfer::where('de_account_id', $account->id)->orWhere('para_account_id', $account->id)->exists();
        if ($usada) {
            $account->update(['arquivada' => true]);

            return back()->with('sucesso', "\"{$account->nome}\" tem movimentações, então foi arquivada em vez de excluída.");
        }

        $account->delete();

        return back()->with('sucesso', "Conta \"{$account->nome}\" excluída.");
    }

    /** Transferência entre duas contas do usuário (não é receita nem gasto). */
    public function transferir(Request $request): RedirectResponse
    {
        $request->merge(['valor' => ler_numero((string) $request->input('valor'))]);
        $minhas = Rule::exists('accounts', 'id')->where('user_id', $request->user()->id);
        $dados = $request->validate([
            'de_account_id' => ['required', 'integer', $minhas],
            'para_account_id' => ['required', 'integer', 'different:de_account_id', $minhas],
            'valor' => ['required', 'numeric', 'gt:0'],
            'data' => ['required', 'date'],
            'descricao' => ['nullable', 'string', 'max:80'],
        ], [], ['de_account_id' => 'conta de origem', 'para_account_id' => 'conta de destino']);

        $request->user()->transfers()->create($dados);

        return back()->with('sucesso', 'Transferência de '.brl($dados['valor']).' registrada.');
    }

    public function excluirTransferencia(Request $request, Transfer $transfer): RedirectResponse
    {
        abort_unless($transfer->user_id === $request->user()->id, 403);

        $transfer->delete();

        return back()->with('sucesso', 'Movimento excluído.');
    }

    private function validar(Request $request): array
    {
        $request->merge(['saldo_inicial' => ler_numero((string) $request->input('saldo_inicial', '0'))]);

        return $request->validate([
            'nome' => ['required', 'string', 'max:40'],
            'tipo' => ['required', Rule::in(array_keys(Account::TIPOS))],
            'saldo_inicial' => ['required', 'numeric'],
        ], [], ['saldo_inicial' => 'saldo inicial']);
    }
}
