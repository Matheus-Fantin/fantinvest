<?php

namespace App\Http\Controllers;

use App\Services\CarteiraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AporteController extends Controller
{
    public function index(Request $request, CarteiraService $carteira): View
    {
        $user = $request->user();
        $valor = $this->valor($request, 3000);
        $metas = $user->metas();

        return view('aporte.index', [
            'valor' => $valor,
            'plano' => $carteira->planoAporte($user->assets()->get(), $metas, $valor),
            'somaMetas' => array_sum($metas),
            'contas' => $user->accounts()->where('arquivada', false)->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request, CarteiraService $carteira): RedirectResponse
    {
        $user = $request->user();
        $valor = $this->valor($request, 0);
        abort_if($valor <= 0, 422, 'Informe o valor do aporte.');

        $contaId = $request->filled('account_id') ? $user->accounts()->findOrFail($request->integer('account_id'))->id : null;
        $plano = $carteira->planoAporte($user->assets()->get(), $user->metas(), $valor);
        $total = $carteira->registrarAporte($user, $plano, $contaId);

        return redirect()->route('aporte.index', ['valor' => valor_input($valor)])
            ->with('sucesso', 'Aporte de '.brl($total).' registrado na carteira e no orçamento.');
    }

    private function valor(Request $request, float $padrao): float
    {
        $texto = (string) $request->input('valor', '');
        if ($texto === '') {
            return $padrao;
        }

        return max(0, min(100_000_000, (float) ler_numero($texto)));
    }
}
