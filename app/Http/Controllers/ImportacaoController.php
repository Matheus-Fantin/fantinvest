<?php

namespace App\Http\Controllers;

use App\Services\ExtratoCsvService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class ImportacaoController extends Controller
{
    public function create(): View
    {
        return view('orcamento.importar');
    }

    /** Lê o arquivo e mostra a prévia para revisar antes de salvar. */
    public function preview(Request $request, ExtratoCsvService $extrato): View|RedirectResponse
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'fatura' => ['boolean'],
        ], [], ['arquivo' => 'arquivo']);

        try {
            $linhas = $extrato->ler($request->file('arquivo')->get(), $request->boolean('fatura'), $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['arquivo' => $e->getMessage()]);
        }

        return view('orcamento.importar-preview', ['linhas' => $linhas]);
    }

    public function store(Request $request): RedirectResponse
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
        ]);

        $selecionadas = array_filter($dados['linhas'], fn ($l) => ! empty($l['incluir']));
        foreach ($selecionadas as $i => $linha) {
            $validas = $linha['tipo'] === 'receita' ? $receitas : $despesas;
            if (! in_array($linha['categoria'], $validas, true)) {
                throw ValidationException::withMessages(["linhas.$i.categoria" => "Categoria inválida na linha de {$linha['data']}."]);
            }
        }

        $user = $request->user();
        DB::transaction(function () use ($user, $selecionadas) {
            foreach ($selecionadas as $linha) {
                $user->transactions()->create([
                    'data' => $linha['data'],
                    'tipo' => $linha['tipo'],
                    'categoria' => $linha['categoria'],
                    'descricao' => $linha['descricao'] ?? null,
                    'valor' => $linha['valor'],
                ]);
            }
        });

        $quantidade = count($selecionadas);
        $mes = $quantidade ? substr(max(array_column($selecionadas, 'data')), 0, 7) : now()->format('Y-m');

        return redirect()->route('orcamento.index', ['mes' => $mes])
            ->with('sucesso', $quantidade === 1 ? '1 lançamento importado.' : "$quantidade lançamentos importados.");
    }
}
