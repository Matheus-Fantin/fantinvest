<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssetRequest;
use App\Models\Asset;
use App\Services\CarteiraService;
use App\Services\Cotacoes\CotacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(Request $request, CarteiraService $carteira): View
    {
        $user = $request->user();
        $ativos = $user->assets()->get();
        $filtro = $request->query('classe', 'todas');

        $grupos = collect(config('financas.classes'))
            ->map(fn ($info, $id) => [
                'id' => $id,
                'nome' => $info['nome'],
                'cor' => $info['cor'],
                'ativos' => $ativos->where('classe', $id)->sortByDesc(fn (Asset $a) => $a->valor())->values(),
            ])
            ->filter(fn ($g) => $g['ativos']->isNotEmpty() && ($filtro === 'todas' || $filtro === $g['id']));

        return view('carteira.index', [
            'ativos' => $ativos,
            'grupos' => $grupos,
            'filtro' => $filtro,
            'resumo' => $carteira->resumo($ativos),
            'metas' => $user->metas(),
        ]);
    }

    public function store(StoreAssetRequest $request, CotacaoService $cotacoes): RedirectResponse
    {
        $dados = $request->validated();
        $dados['cotacao'] = ($dados['cotacao'] ?? 0) ?: $dados['preco_medio'];

        $ativo = $request->user()->assets()->create($dados);

        return back()->with('sucesso', "{$ativo->ticker} adicionado à carteira.".$this->buscarCotacao($ativo, $cotacoes));
    }

    public function update(StoreAssetRequest $request, Asset $asset, CotacaoService $cotacoes): RedirectResponse
    {
        Gate::authorize('update', $asset);

        $dados = $request->validated();
        $dados['cotacao'] = ($dados['cotacao'] ?? 0) ?: $dados['preco_medio'];
        $asset->update($dados);

        return back()->with('sucesso', "{$asset->ticker} atualizado.".$this->buscarCotacao($asset, $cotacoes));
    }

    /** Busca a cotação na hora para ativos com atualização automática; devolve um complemento para a mensagem. */
    private function buscarCotacao(Asset $ativo, CotacaoService $cotacoes): string
    {
        if (! $ativo->atualizacao_automatica || ! $ativo->temFonteAutomatica()) {
            return '';
        }

        $resultado = $cotacoes->atualizar(collect([$ativo]));

        return $resultado['atualizados'] > 0
            ? ' Cotação atual: '.brl($ativo->fresh()->cotacao).'.'
            : ' Não encontrei a cotação de '.$ativo->ticker.' na API; ficou a cotação digitada.';
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        Gate::authorize('delete', $asset);

        $asset->delete();

        return back()->with('sucesso', "{$asset->ticker} excluído da carteira.");
    }

    /** Calcula a nota a partir do checklist de critérios da classe. */
    public function nota(Request $request, Asset $asset): RedirectResponse
    {
        Gate::authorize('update', $asset);

        $criterios = config("financas.criterios.{$asset->classe}", []);
        abort_if(empty($criterios), 404);

        $marcados = $request->validate([
            'criterios' => ['array'],
            'criterios.*' => ['integer', Rule::in(array_keys($criterios))],
        ])['criterios'] ?? [];

        $marcados = array_values(array_unique(array_map('intval', $marcados)));
        $asset->update([
            'criterios' => $marcados,
            'nota' => round(count($marcados) / count($criterios) * 10, 1),
        ]);

        return back()->with('sucesso', "Nota de {$asset->ticker}: ".numero($asset->nota, 1).'.');
    }
}
