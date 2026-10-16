<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Cadastro de contas. Com CADASTRO_ABERTO=false (padrão), só entra quem tem um
 * convite válido; a primeira conta do sistema é sempre liberada e vira administradora.
 */
class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $codigo = (string) $request->query('convite', '');
        $convite = $codigo !== '' ? Invite::where('codigo', $codigo)->first() : null;

        return view('auth.register', [
            'liberado' => $this->cadastroLivre() || $convite?->valido(),
            'convite' => $convite?->valido() ? $convite : null,
            'conviteInvalido' => $codigo !== '' && ! $convite?->valido(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'convite' => ['nullable', 'string', 'max:32'],
        ]);

        $primeiraConta = ! User::exists();
        $convite = null;
        if (! $this->cadastroLivre()) {
            $convite = Invite::where('codigo', (string) $request->input('convite'))->first();
            if (! $convite?->valido($request->email)) {
                throw ValidationException::withMessages([
                    'email' => $convite && $convite->email
                        ? 'Este convite foi feito para outro e-mail.'
                        : 'O cadastro é só por convite. Peça um link de convite ao administrador.',
                ]);
            }
        }

        $user = DB::transaction(function () use ($request, $primeiraConta, $convite) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
            if ($primeiraConta) {
                $user->forceFill(['is_admin' => true])->save();
            }
            $convite?->update(['usado_por' => $user->id, 'usado_em' => now()]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    private function cadastroLivre(): bool
    {
        return config('fantinvest.cadastro_aberto') || ! User::exists();
    }
}
