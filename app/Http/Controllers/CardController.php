<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Services\CartaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CardController extends Controller
{
    public function index(Request $request, CartaoService $cartoes): View
    {
        $user = $request->user();
        $lista = $user->cards()->with('account')->orderBy('nome')->get()->map(function (Card $c) use ($cartoes) {
            $faturas = $cartoes->faturas($c);
            $atual = $faturas[$c->faturaDaCompra(now())];

            return [
                'cartao' => $c,
                'usado' => $cartoes->limiteUsado($c),
                'atual' => $atual,
                'pendentes' => collect($faturas)->whereIn('situacao', ['fechada', 'atrasada'])->values(),
            ];
        });

        return view('cartoes.index', ['cartoes' => $lista, 'contas' => $user->accounts()->where('arquivada', false)->orderBy('nome')->get()]);
    }

    public function show(Request $request, Card $card, CartaoService $cartoes): View
    {
        Gate::authorize('update', $card);

        $faturas = $cartoes->faturas($card);
        $selecionada = $request->query('fatura');
        if (! is_string($selecionada) || ! isset($faturas[$selecionada])) {
            $selecionada = $card->faturaDaCompra(now());
        }

        return view('cartoes.show', [
            'cartao' => $card,
            'faturas' => $faturas,
            'fatura' => $faturas[$selecionada],
            'compras' => $card->transactions()->where('fatura', $selecionada)->orderBy('data')->orderBy('id')->get(),
            'pagamentos' => $card->pagamentos()->with('de')->where('fatura', $selecionada)->orderBy('data')->get(),
            'usado' => $cartoes->limiteUsado($card),
            'contas' => $request->user()->accounts()->where('arquivada', false)->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cartao = $request->user()->cards()->create($this->validar($request));

        return back()->with('sucesso', "Cartão \"{$cartao->nome}\" criado.")->with('origem_nova', 'cartao:'.$cartao->id);
    }

    public function update(Request $request, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card);

        $card->update($this->validar($request));

        return back()->with('sucesso', "Cartão \"{$card->nome}\" atualizado.");
    }

    public function destroy(Card $card): RedirectResponse
    {
        Gate::authorize('delete', $card);

        if ($card->transactions()->exists()) {
            return back()->with('sucesso', "O cartão \"{$card->nome}\" tem compras lançadas. Exclua as compras antes de excluir o cartão.");
        }
        $card->delete();

        return redirect()->route('cartoes.index')->with('sucesso', "Cartão \"{$card->nome}\" excluído.");
    }

    public function pagar(Request $request, Card $card, CartaoService $cartoes): RedirectResponse
    {
        Gate::authorize('update', $card);

        $request->merge(['valor' => ler_numero((string) $request->input('valor'))]);
        $dados = $request->validate([
            'fatura' => ['required', 'date_format:Y-m'],
            'valor' => ['required', 'numeric', 'gt:0'],
            'data' => ['required', 'date'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', $request->user()->id)],
        ]);

        $conta = $dados['account_id'] ? $request->user()->accounts()->find($dados['account_id']) : null;
        $cartoes->pagar($card, $dados['fatura'], $conta, (float) $dados['valor'], $dados['data']);

        return back()->with('sucesso', 'Pagamento de '.brl($dados['valor']).' registrado na fatura de '.mes_extenso($dados['fatura']).'.');
    }

    private function validar(Request $request): array
    {
        $request->merge(['limite' => ler_numero((string) ($request->input('limite') ?: '0'))]);

        return $request->validate([
            'nome' => ['required', 'string', 'max:40'],
            'limite' => ['required', 'numeric', 'min:0'],
            'dia_fechamento' => ['required', 'integer', 'between:1,31'],
            'dia_vencimento' => ['required', 'integer', 'between:1,31'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', $request->user()->id)],
        ], [], ['dia_fechamento' => 'dia de fechamento', 'dia_vencimento' => 'dia de vencimento', 'account_id' => 'conta de pagamento']);
    }
}
