<?php

namespace App\Http\Controllers;

use App\Models\Dividend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DividendController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $hoje = now();
        $inicio = $hoje->copy()->startOfMonth()->subMonths(11);

        $todos = $user->dividends()->with(['asset', 'account'])->orderByDesc('data')->orderByDesc('id')->get();
        $ultimos12 = $todos->filter(fn ($d) => $d->data->between($inicio, $hoje->copy()->endOfDay()));

        $meses = [];
        for ($m = $inicio->copy(); $m <= $hoje; $m->addMonth()) {
            $doMes = $ultimos12->filter(fn ($d) => $d->data->format('Y-m') === $m->format('Y-m'));
            $meses[] = [
                'mes' => $m->format('Y-m'),
                'dividendo' => round($doMes->where('tipo', 'dividendo')->sum('valor'), 2),
                'jcp' => round($doMes->where('tipo', 'jcp')->sum('valor'), 2),
                'rendimento' => round($doMes->where('tipo', 'rendimento')->sum('valor'), 2),
            ];
        }

        $ativos = $user->assets()->orderBy('ticker')->get();
        $porAtivo = $ultimos12->groupBy('ticker')->map(function ($grupo, $ticker) use ($ativos) {
            $ativo = $ativos->firstWhere('ticker', $ticker);
            $total = $grupo->sum('valor');

            return [
                'ticker' => $ticker,
                'total' => $total,
                'pagamentos' => $grupo->count(),
                'yoc' => $ativo && $ativo->custo() > 0 ? $total / $ativo->custo() * 100 : null,
            ];
        })->sortByDesc('total')->values();

        $custoCarteira = $ativos->sum(fn ($a) => $a->custo());

        return view('dividendos.index', [
            'proventos' => $todos->take(60),
            'meses' => $meses,
            'porAtivo' => $porAtivo,
            'totalMes' => $todos->filter(fn ($d) => $d->data->format('Y-m') === $hoje->format('Y-m'))->sum('valor'),
            'total12' => $ultimos12->sum('valor'),
            'totalAno' => $todos->filter(fn ($d) => $d->data->year === $hoje->year)->sum('valor'),
            'yocCarteira' => $custoCarteira > 0 ? $ultimos12->sum('valor') / $custoCarteira * 100 : 0,
            'ativos' => $ativos,
            'contas' => $user->accounts()->where('arquivada', false)->orderBy('nome')->get(),
        ]);
    }

    /** Registra o provento e lança a receita no orçamento (categoria Dividendos). */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['valor' => ler_numero((string) $request->input('valor'))]);
        $user = $request->user();
        $dados = $request->validate([
            'asset_id' => ['required', 'integer', Rule::exists('assets', 'id')->where('user_id', $user->id)],
            'tipo' => ['required', Rule::in(array_keys(Dividend::TIPOS))],
            'data' => ['required', 'date'],
            'valor' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('user_id', $user->id)],
        ], [], ['asset_id' => 'ativo', 'account_id' => 'conta']);

        $ativo = $user->assets()->findOrFail($dados['asset_id']);

        DB::transaction(function () use ($user, $ativo, $dados) {
            $receita = $user->transactions()->create([
                'data' => $dados['data'],
                'tipo' => 'receita',
                'categoria' => 'Dividendos',
                'descricao' => Dividend::TIPOS[$dados['tipo']].' '.$ativo->ticker,
                'valor' => $dados['valor'],
                'account_id' => $dados['account_id'] ?? null,
            ]);
            $user->dividends()->create($dados + ['ticker' => $ativo->ticker, 'transaction_id' => $receita->id]);
        });

        return redirect()->route('dividendos.index')
            ->with('sucesso', brl($dados['valor'])." de {$ativo->ticker} registrado em ".Carbon::parse($dados['data'])->format('d/m/Y').'.');
    }

    public function destroy(Dividend $dividend): RedirectResponse
    {
        Gate::authorize('delete', $dividend);

        DB::transaction(function () use ($dividend) {
            $dividend->transaction?->delete();
            $dividend->delete();
        });

        return back()->with('sucesso', 'Provento excluído (e a receita no orçamento também).');
    }
}
