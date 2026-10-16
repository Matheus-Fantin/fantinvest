<x-guest-layout>
    @if (! $liberado)
        <div class="stack">
            <h2>Cadastro por convite</h2>
            @if ($conviteInvalido)
                <div class="banner warn"><span>Este link de convite é inválido, já foi usado ou expirou. Peça um novo ao administrador.</span></div>
            @else
                <p class="muted">O FantInvest é de uso pessoal. Para criar uma conta, peça um link de convite a quem administra o sistema.</p>
            @endif
            <a class="btn primary" href="{{ route('login') }}">Já tenho conta: entrar</a>
        </div>
    @else
        <form method="POST" action="{{ route('register') }}" class="stack">
            @csrf
            <div>
                <h2>Criar sua conta</h2>
                @if ($convite)
                    <p class="small muted">Você foi convidado{{ $convite->email ? ' com o e-mail '.$convite->email : '' }}. O convite vale até {{ $convite->expira_em?->format('d/m/Y') }}.</p>
                    <input type="hidden" name="convite" value="{{ $convite->codigo }}">
                @endif
            </div>

            <div class="field">
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div class="field">
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" type="email" name="email" :value="old('email', $convite?->email)" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div class="field">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
                <span class="hint">Pelo menos 8 caracteres.</span>
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div class="field">
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" />
            </div>

            <div class="row between">
                <a class="link small" href="{{ route('login') }}">{{ __('Already registered?') }}</a>
                <x-primary-button>{{ __('Register') }}</x-primary-button>
            </div>
        </form>
    @endif
</x-guest-layout>
