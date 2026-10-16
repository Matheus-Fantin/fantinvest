<?php

namespace App\Http\Controllers;

use App\Services\ExtratoCsvService;
use App\Services\LancamentoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class ImportacaoController extends Controller
{
    public function create(Request $request): View
    {
        return view('orcamento.importar', [
            'contas' => $request->user()->accounts()->where('arquivada', false)->orderBy('nome')->get(),
            'cartoes' => $request->user()->cards()->orderBy('nome')->get(),
        ]);
    }

    /** Lê o arquivo e mostra a prévia para revisar antes de salvar. */
    public function preview(Request $request, ExtratoCsvService $extrato): View|RedirectResponse
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'extensions:csv,txt,ofx', 'max:2048'],
            'fatura' => ['boolean'],
            'origem' => ['nullable', 'string', 'max:20'],
        ], [], ['arquivo' => 'arquivo']);

        $origem = (string) $request->input('origem', '');
        $ehCartao = str_starts_with($origem, 'cartao:');

        try {
            $linhas = $extrato->ler($request->file('arquivo')->get(), $request->boolean('fatura') || $ehCartao, $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['arquivo' => $e->getMessage()]);
        }

        return view('orcamento.importar-preview', ['linhas' => $linhas, 'origem' => $origem, 'destino' => $this->nomeDestino($request, $origem)]);
    }

    public function store(Request $request, LancamentoService $lancamentos): RedirectResponse
    {
        $receitas = config('financas.categorias_receita');
        $despesas = array_keys(config('financas.categorias_despesa'));

        $dados = $request->validate([
            'linhas' => ['required', 'array', 'max:'.ExtratoCsvService::MAXIMO_LINHAS],
            'linhas.*.incluir' => ['nullable', 'boolean'],
            'linhas.*.data' => ['required', 'date'],
            'linhas.*.descricao' => ['nullable', 'string', 'max:80'],
            'linhas.*.valor' => ['required', 'numeric', 'gt:0'],
            'linhas.*.tipo' => ['required', 'in:receita,despesa'],
            'linhas.*.categoria' => ['required', 'string'],
            'linhas.*.external_id' => ['nullable', 'string', 'max:120'],
            'origem' => ['nullable', 'string', 'max:20'],
        ]);

        [$tipoOrigem, $idOrigem] = array_pad(explode(':', (string) ($dados['origem'] ?? ''), 2), 2, null);
        $contaId = $tipoOrigem === 'conta' ? $request->user()->accounts()->findOrFail($idOrigem)->id : null;
        $cartaoId = $tipoOrigem === 'cartao' ? $request->user()->cards()->findOrFail($idOrigem)->id : null;

        $selecionadas = array_filter($dados['linhas'], fn ($l) => ! empty($l['incluir']));
        foreach ($selecionadas as $i => $linha) {
            $validas = $linha['tipo'] === 'receita' ? $receitas : $despesas;
            if (! in_array($linha['categoria'], $validas, true)) {
                throw ValidationException::withMessages(["linhas.$i.categoria" => "Categoria inválida na linha de {$linha['data']}."]);
            }
        }

        $user = $request->user();
        DB::transaction(function () use ($user, $selecionadas, $lancamentos, $contaId, $cartaoId) {
            foreach ($selecionadas as $linha) {
                $lancamentos->criar($user, [
                    'data' => $linha['data'],
                    'tipo' => $linha['tipo'],
                    'categoria' => $linha['categoria'],
                    'descricao' => $linha['descricao'] ?? null,
                    'valor' => $linha['valor'],
                    'account_id' => $contaId,
                    'card_id' => $cartaoId,
                    'external_id' => $linha['external_id'] ?? null,
                ]);
            }
        });

        $quantidade = count($selecionadas);
        $mes = $quantidade ? substr(max(array_column($selecionadas, 'data')), 0, 7) : now()->format('Y-m');

        return redirect()->route('orcamento.index', ['mes' => $mes])
            ->with('sucesso', $quantidade === 1 ? '1 lançamento importado.' : "$quantidade lançamentos importados.");
    }

    private function nomeDestino(Request $request, string $origem): ?string
    {
        [$tipo, $id] = array_pad(explode(':', $origem, 2), 2, null);

        return match ($tipo) {
            'conta' => optional($request->user()->accounts()->find($id))->nome,
            'cartao' => ($c = $request->user()->cards()->find($id)) ? 'Cartão '.$c->nome : null,
            default => null,
        };
    }
}
