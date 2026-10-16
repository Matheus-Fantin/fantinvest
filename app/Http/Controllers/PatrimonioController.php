<?php

namespace App\Http\Controllers;

use App\Services\ContaService;
use App\Services\PatrimonioService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatrimonioController extends Controller
{
    public function index(Request $request, PatrimonioService $patrimonio, ContaService $contas): View
    {
        $user = $request->user();
        $calculo = $patrimonio->calcular($user);

        return view('patrimonio.index', [
            'p' => $calculo,
            'evolucao' => $patrimonio->registrarEvolucao($user, $calculo),
            'contas' => $contas->comSaldos($user),
        ]);
    }
}
