<?php

namespace App\Http\Controllers;

use App\Models\Financing;
use App\Models\FinancingExpense;
use App\Models\FinancingPayment;
use App\Models\FinancingRelease;
use App\Models\Transaction;
use App\Services\FinanciamentoImovelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanciamentoImovelController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $lista = $request->user()->financings()->orderBy('nome')->get();
        if ($lista->count() === 1 && ! $request->boolean('lista')) {
            return redirect()->route('imovel.show', $lista->first());
        }

        return view('imovel.index', ['financiamentos' => $lista]);
    }

    public function show(Request $request, Financing $financing, FinanciamentoImovelService $servico): View
    {
        Gate::authorize('update', $financing);

        $financing->load('pagamentos');
        $cronograma = $servico->cronograma($financing);

        return view('imovel.show', [
            'f' => $financing,
            'linhas' => $cronograma['linhas'],
            'resumo' => $cronograma['resumo'],
            'obra' => $servico->obra($financing),
            'liberacoes' => $financing->liberacoes()->get(),
            'gastos' => $financing->gastos()->get(),
            'pagamentos' => $financing->pagamentos()->get()->sortByDesc('data')->values(),
            'categorias' => FinanciamentoImovelService::CATEGORIAS_OBRA,
            'contas' => $request->user()->accounts()->where('arquivada', false)->orderBy('nome')->get(),
            'outros' => $request->user()->financings()->where('id', '!=', $financing->id)->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $f = $request->user()->financings()->create($this->validar($request));

        return redirect()->route('imovel.show', $f)->with('sucesso', "Financiamento \"{$f->nome}\" cadastrado.");
    }

    public function update(Request $request, Financing $financing): RedirectResponse
    {
        Gate::authorize('update', $financing);

        $financing->update($this->validar($request));

        return back()->with('sucesso', 'Dados do contrato atualizados.');
    }

    public function destroy(Financing $financing): RedirectResponse
    {
        Gate::authorize('delete', $financing);

        $financing->delete();

        return redirect()->route('imovel.index', ['lista' => 1])->with('sucesso', "Financiamento \"{$financing->nome}\" excluído.");
    }

    public function liberacao(Request $request, Financing $financing): RedirectResponse
    {
        Gate::authorize('update', $financing);

        $request->merge(['valor' => ler_numero((string) $request->input('valor'))]);
        $financing->liberacoes()->create($request->validate([
            'data' => ['required', 'date'],
            'valor' => ['required', 'numeric', 'gt:0'],
            'descricao' => ['nullable', 'string', 'max:80'],
        ]));

        return $this->voltar($financing, 'obra', 'Liberação do banco registrada.');
    }

    public function gasto(Request $request, Financing $financing): RedirectResponse
    {
        Gate::authorize('update', $financing);

        $request->merge(['valor' => ler_numero((string) $request->input('valor'))]);
        $financing->gastos()->create($request->validate([
            'data' => ['required', 'date'],
            'descricao' => ['required', 'string', 'max:80'],
            'categoria' => ['required', Rule::in(FinanciamentoImovelService::CATEGORIAS_OBRA)],
            'fornecedor' => ['nullable', 'string', 'max:60'],
            'valor' => ['required', 'numeric', 'gt:0'],
            'origem' => ['required', Rule::in(['financiamento', 'proprio'])],
        ], [], ['descricao' => 'descrição']));

        return $this->voltar($financing, 'obra', 'Gasto da obra registrado.');
    }

    /** Parcela paga, amortização extra ou encargo da fase de obra; pode lançar no orçamento. */
    public function pagamento(Request $request, Financing $financing, FinanciamentoImovelService $servico): RedirectResponse
    {
        Gate::authorize('update', $financing);

        $request->merge(['valor' => ler_numero((string) $request->input('valor'))]);
        $user = $request->user();
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(['parcela', 'amortizacao', 'obra'])],
            'numero' => [
                'nullable', 'required_if:tipo,parcela', 'integer', 'min:1', 'max:'.$financing->prazo_meses,
                Rule::unique('financing_payments', 'numero')->where('financing_id', $financing->id)->where('tipo', 'parcela'),
            ],
            'data' => ['required', 'date'],
            'valor' => ['required', 'numeric', 'gt:0'],
            'reduzir' => ['nullable', 'required_if:tipo,amortizacao', Rule::in(['prazo', 'parcela'])],
            'lancar_no_orcamento' => ['boolean'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
        ], ['numero.unique' => 'Essa parcela já está registrada como paga.'], ['numero' => 'número da parcela', 'reduzir' => 'o que reduzir']);

        DB::transaction(function () use ($user, $financing, $dados, $request) {
            $lancamento = null;
            if ($request->boolean('lancar_no_orcamento')) {
                $descricao = match ($dados['tipo']) {
                    'parcela' => "Parcela {$dados['numero']}/{$financing->prazo_meses} {$financing->nome}",
                    'amortizacao' => "Amortização extra {$financing->nome}",
                    default => "Encargos de obra {$financing->nome}",
                };
                $lancamento = $user->transactions()->create([
                    'data' => $dados['data'],
                    'tipo' => 'despesa',
                    'categoria' => 'Moradia',
                    'descricao' => mb_substr($descricao, 0, 80),
                    'valor' => $dados['valor'],
                    'account_id' => $dados['account_id'] ?? null,
                ]);
            }

            $financing->pagamentos()->create([
                'tipo' => $dados['tipo'],
                'numero' => $dados['tipo'] === 'parcela' ? $dados['numero'] : null,
                'data' => $dados['data'],
                'valor' => $dados['valor'],
                'reduzir' => $dados['tipo'] === 'amortizacao' ? $dados['reduzir'] : null,
                'transaction_id' => $lancamento?->id,
            ]);
        });

        $mensagem = match ($dados['tipo']) {
            'parcela' => "Parcela {$dados['numero']} registrada como paga.",
            'amortizacao' => 'Amortização extra de '.brl($dados['valor']).' registrada. O cronograma foi recalculado.',
            default => 'Encargo da fase de obra registrado.',
        };

        return $this->voltar($financing, $dados['tipo'] === 'obra' ? 'obra' : 'parcelas', $mensagem);
    }

    public function excluirItem(Request $request, Financing $financing, string $tipo, int $id): RedirectResponse
    {
        Gate::authorize('update', $financing);

        $modelo = match ($tipo) {
            'liberacao' => FinancingRelease::class,
            'gasto' => FinancingExpense::class,
            'pagamento' => FinancingPayment::class,
            default => abort(404),
        };
        $item = $modelo::where('financing_id', $financing->id)->findOrFail($id);

        DB::transaction(function () use ($item) {
            if ($item instanceof FinancingPayment && $item->transaction_id) {
                $item->transaction_id && Transaction::whereKey($item->transaction_id)->delete();
            }
            $item->delete();
        });

        return $this->voltar($financing, $tipo === 'pagamento' ? 'parcelas' : 'obra', 'Registro excluído.');
    }

    private function voltar(Financing $f, string $aba, string $mensagem): RedirectResponse
    {
        return redirect()->to(route('imovel.show', $f).'#'.$aba)->with('sucesso', $mensagem);
    }

    private function validar(Request $request): array
    {
        foreach (['valor_imovel', 'valor_financiado', 'recursos_proprios', 'taxa_juros_anual', 'seguro_mensal', 'taxa_administracao'] as $campo) {
            $request->merge([$campo => ler_numero((string) $request->input($campo, '0'))]);
        }

        return $request->validate([
            'nome' => ['required', 'string', 'max:60'],
            'banco' => ['required', 'string', 'max:40'],
            'valor_imovel' => ['nullable', 'numeric', 'min:0'],
            'valor_financiado' => ['required', 'numeric', 'gt:0'],
            'recursos_proprios' => ['nullable', 'numeric', 'min:0'],
            'taxa_juros_anual' => ['required', 'numeric', 'between:0,50'],
            'sistema' => ['required', Rule::in(['sac', 'price'])],
            'prazo_meses' => ['required', 'integer', 'between:1,480'],
            'data_contrato' => ['nullable', 'date'],
            'primeira_parcela' => ['required', 'date'],
            'seguro_mensal' => ['nullable', 'numeric', 'min:0'],
            'taxa_administracao' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'valor_financiado' => 'valor financiado', 'taxa_juros_anual' => 'taxa de juros', 'prazo_meses' => 'prazo',
            'primeira_parcela' => 'data da 1ª parcela', 'valor_imovel' => 'valor do imóvel',
        ]);
    }
}
