<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InviteController extends Controller
{
    public function index(): View
    {
        return view('admin.convites', [
            'convites' => Invite::with(['usuario', 'criador'])->latest()->limit(100)->get(),
            'usuarios' => User::orderBy('name')->get(['id', 'name', 'email', 'is_admin', 'created_at']),
            'cadastroAberto' => (bool) config('fantinvest.cadastro_aberto'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'dias' => ['required', 'integer', 'between:1,90'],
        ]);

        $convite = Invite::create([
            'codigo' => Str::lower(Str::random(24)),
            'email' => $dados['email'] ?? null,
            'criado_por' => $request->user()->id,
            'expira_em' => now()->addDays((int) $dados['dias']),
        ]);

        return back()->with('sucesso', 'Convite criado. Copie o link e envie para a pessoa.')->with('convite_novo', $convite->codigo);
    }

    public function destroy(Invite $invite): RedirectResponse
    {
        $invite->delete();

        return back()->with('sucesso', 'Convite cancelado.');
    }
}
