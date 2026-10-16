<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoalRequest;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GoalController extends Controller
{
    public function index(Request $request): View
    {
        $objetivos = $request->user()->goals()->orderByRaw('data_alvo is null')->orderBy('data_alvo')->get();

        return view('objetivos.index', [
            'objetivos' => $objetivos,
            'totalAlvo' => $objetivos->sum('valor_alvo'),
            'totalGuardado' => $objetivos->sum('valor_atual'),
            'totalMensal' => $objetivos->sum(fn (Goal $g) => $g->aporteMensalNecessario() ?? 0),
        ]);
    }

    public function store(StoreGoalRequest $request): RedirectResponse
    {
        $objetivo = $request->user()->goals()->create($this->dados($request));

        return back()->with('sucesso', "Objetivo \"{$objetivo->nome}\" criado.");
    }

    public function update(StoreGoalRequest $request, Goal $goal): RedirectResponse
    {
        Gate::authorize('update', $goal);

        $goal->update($this->dados($request));

        return back()->with('sucesso', "Objetivo \"{$goal->nome}\" atualizado.");
    }

    public function destroy(Goal $goal): RedirectResponse
    {
        Gate::authorize('delete', $goal);

        $goal->delete();

        return back()->with('sucesso', "Objetivo \"{$goal->nome}\" excluído.");
    }

    /** Guarda (ou retira, com valor negativo) dinheiro no objetivo e, se pedido, lança no orçamento. */
    public function depositar(Request $request, Goal $goal): RedirectResponse
    {
        Gate::authorize('update', $goal);

        $request->merge(['valor' => ler_numero((string) $request->input('valor'))]);
        $dados = $request->validate([
            'valor' => ['required', 'numeric', 'not_in:0'],
            'lancar_no_orcamento' => ['boolean'],
        ]);

        $valor = (float) $dados['valor'];
        $goal->update(['valor_atual' => max(0, $goal->valor_atual + $valor)]);

        if ($valor > 0 && $request->boolean('lancar_no_orcamento')) {
            $request->user()->transactions()->create([
                'data' => now()->toDateString(),
                'tipo' => 'despesa',
                'categoria' => 'Objetivos',
                'descricao' => mb_substr("Objetivo: {$goal->nome}", 0, 80),
                'valor' => $valor,
            ]);
        }

        $acao = $valor > 0 ? 'guardado em' : 'retirado de';

        return back()->with('sucesso', brl(abs($valor))." $acao \"{$goal->nome}\".");
    }

    private function dados(StoreGoalRequest $request): array
    {
        $dados = $request->validated();
        $dados['valor_atual'] ??= 0;
        $dados['rendimento_anual'] ??= 0;

        return $dados;
    }
}
