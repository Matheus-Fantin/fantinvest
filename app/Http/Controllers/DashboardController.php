<?php

namespace App\Http\Controllers;

use App\Services\CarteiraService;
use App\Services\OrcamentoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, CarteiraService $carteira, OrcamentoService $orcamento): View
    {
        $user = $request->user();
        $mes = now()->format('Y-m');
        $resumo = $carteira->resumo($user->assets()->get());
        $metas = $user->metas();

        $classes = collect(config('financas.classes'))
            ->map(fn ($info, $id) => [
                'id' => $id,
                'nome' => $info['nome'],
                'cor' => $info['cor'],
                'atual' => $resumo['percentual'][$id] ?? 0,
                'meta' => $metas[$id] ?? 0,
            ])
            ->values();

        $mesAtual = $orcamento->resumoDoMes($user, $mes);
        $metaReserva = $mesAtual['ess'] * $user->reserva_meses;

        return view('dashboard', [
            'mes' => $mes,
            'resumo' => $resumo,
            'quantidadeAtivos' => $user->assets()->count(),
            'mesAtual' => $mesAtual,
            'classes' => $classes,
            'faltam' => $classes->where('meta', '>', 0)->sortByDesc(fn ($c) => $c['meta'] - $c['atual'])->values(),
            'historico' => $orcamento->historico($user, $mes),
            'objetivos' => $user->goals()->orderByRaw('data_alvo is null')->orderBy('data_alvo')->limit(4)->get(),
            'reserva' => [
                'atual' => $user->reserva_atual,
                'meses' => $user->reserva_meses,
                'meta' => $metaReserva,
                'percentual' => $metaReserva > 0 ? min(100, $user->reserva_atual / $metaReserva * 100) : 0,
            ],
        ]);
    }
}
