<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\CarteiraService;
use App\Services\Cotacoes\BrapiCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class BolsaController extends Controller
{
    private const CLASSES_BOLSA = ['acoes', 'fii', 'exterior'];

    public function index(Request $request, BrapiCliente $brapi): View
    {
        $user = $request->user();
        $termo = trim((string) $request->query('q', ''));
        $resultados = $termo !== '' ? $brapi->pesquisar($termo) : [];

        // Rankings do dia, sem precisar pesquisar (guardados por 10 minutos para não gastar a cota da API).
        $rankings = [];
        if ($termo === '') {
            foreach ([
                'negociadas' => ['volume', 'desc', 'stock'],
                'altas' => ['change', 'desc', 'stock'],
                'baixas' => ['change', 'asc', 'stock'],
                'fiis' => ['volume', 'desc', 'fund'],
            ] as $chave => [$por, $ordem, $tipo]) {
                $rankings[$chave] = Cache::get("bolsa:ranking:$chave") ?? tap($brapi->ranking($por, $ordem, $tipo, 20), function ($lista) use ($chave) {
                    if ($lista !== []) {
                        Cache::put("bolsa:ranking:$chave", $lista, now()->addMinutes(10));
                    }
                });
            }
        }

        $posicoes = $user->assets()->whereIn('classe', self::CLASSES_BOLSA)->where('quantidade', '>', 0)->get()
            ->sortByDesc(fn (Asset $a) => $a->valor())->values();

        $ano = now()->startOfYear()->toDateString();
        $mes = now()->startOfMonth()->toDateString();
        $vendasAcoesMes = (float) $user->operations()->where('tipo', 'venda')->where('data', '>=', $mes)
            ->whereHas('asset', fn ($q) => $q->where('classe', 'acoes'))
            ->sum(DB::raw('quantidade * preco'));

        return view('bolsa.index', [
            'termo' => $termo,
            'resultados' => $resultados,
            'rankings' => $rankings,
            'buscou' => $termo !== '',
            'posicoes' => $posicoes,
            'total' => $posicoes->sum(fn (Asset $a) => $a->valor()),
            'custo' => $posicoes->sum(fn (Asset $a) => $a->custo()),
            'lucroRealizadoAno' => (float) $user->operations()->where('tipo', 'venda')->where('data', '>=', $ano)->sum('resultado'),
            'vendasAcoesMes' => $vendasAcoesMes,
            'operacoes' => $user->operations()->with('asset')->orderByDesc('data')->orderByDesc('id')->limit(15)->get(),
            'semToken' => blank(config('cotacoes.brapi.token')),
        ]);
    }

    public function show(Request $request, string $ticker, BrapiCliente $brapi): View
    {
        $ticker = mb_strtoupper(preg_replace('/[^A-Za-z0-9.]/', '', $ticker));
        abort_if($ticker === '', 404);

        $detalhe = Cache::remember("bolsa:detalhe:$ticker", now()->addMinutes(5), fn () => $brapi->detalhe($ticker));
        $user = $request->user();
        $ativo = $user->assets()->where('ticker', $ticker)->first();

        return view('bolsa.show', [
            'ticker' => $ticker,
            'detalhe' => $detalhe,
            'ativo' => $ativo,
            'operacoes' => $ativo ? $ativo->operations()->orderByDesc('data')->orderByDesc('id')->get() : collect(),
            'proventos' => $user->dividends()->where('ticker', $ticker)->orderByDesc('data')->limit(12)->get(),
            'contas' => $user->accounts()->where('arquivada', false)->orderBy('nome')->get(),
            'classeSugerida' => $ativo?->classe ?? (str_ends_with($ticker, '11') ? 'fii' : (preg_match('/3[2-5]$/', $ticker) ? 'exterior' : 'acoes')),
        ]);
    }

    /** Registra uma compra ou venda; cria o ativo na carteira se ainda não existir. */
    public function operacao(Request $request, string $ticker, CarteiraService $carteira): RedirectResponse
    {
        $ticker = mb_strtoupper(preg_replace('/[^A-Za-z0-9.]/', '', $ticker));
        foreach (['quantidade', 'preco', 'taxas'] as $campo) {
            $request->merge([$campo => ler_numero((string) $request->input($campo, '0'))]);
        }
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(['compra', 'venda'])],
            'classe' => ['required', Rule::in(array_keys(config('financas.classes')))],
            'data' => ['required', 'date', 'before_or_equal:today'],
            'quantidade' => ['required', 'numeric', 'gt:0'],
            'preco' => ['required', 'numeric', 'gt:0'],
            'taxas' => ['nullable', 'numeric', 'min:0'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', $request->user()->id)],
        ], [], ['preco' => 'preço', 'account_id' => 'conta']);

        $user = $request->user();

        try {
            DB::transaction(function () use ($user, $ticker, $dados, $carteira) {
                $ativo = $user->assets()->firstOrCreate(
                    ['ticker' => $ticker],
                    ['classe' => $dados['classe'], 'quantidade' => 0, 'preco_medio' => 0, 'cotacao' => $dados['preco'], 'nota' => 7, 'atualizacao_automatica' => true],
                );
                $taxas = (float) ($dados['taxas'] ?? 0);
                $carteira->aplicarOperacao($ativo, $dados['tipo'], $dados['data'], (float) $dados['quantidade'], (float) $dados['preco'], $taxas, $dados['account_id'] ?? null);

                // A compra entra no orçamento como investimento (e sai da conta escolhida).
                if ($dados['tipo'] === 'compra') {
                    $user->transactions()->create([
                        'data' => $dados['data'],
                        'tipo' => 'despesa',
                        'categoria' => 'Investimentos',
                        'descricao' => "Compra de {$ticker}",
                        'valor' => round($dados['quantidade'] * $dados['preco'] + $taxas, 2),
                        'account_id' => $dados['account_id'] ?? null,
                    ]);
                }
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['quantidade' => $e->getMessage()])->withInput();
        }

        $verbo = $dados['tipo'] === 'compra' ? 'Compra' : 'Venda';

        return redirect()->route('bolsa.show', $ticker)
            ->with('sucesso', "$verbo de ".numero($dados['quantidade'], 8)." $ticker registrada.");
    }
}
