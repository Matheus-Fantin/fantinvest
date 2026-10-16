<x-app-layout titulo="Convites">
    <div class="page-head">
        <div>
            <div class="eyebrow">Administração</div>
            <h1>Convites</h1>
            <p>{{ $cadastroAberto ? 'O cadastro está aberto para qualquer pessoa (CADASTRO_ABERTO=true no .env).' : 'O cadastro é só por convite. Gere um link e envie para a pessoa; ele vale uma vez.' }}</p>
        </div>
    </div>

    @if (session('convite_novo'))
        @php $link = route('register', ['convite' => session('convite_novo')]); @endphp
        <div class="banner" x-data="{ copiado: false }">
            <span style="min-width:0;overflow-wrap:anywhere"><strong>Link do convite:</strong> <span class="mono small">{{ $link }}</span></span>
            <button type="button" class="btn sm primary" x-on:click="navigator.clipboard.writeText(@js($link)).then(() => copiado = true)" x-text="copiado ? 'Copiado' : 'Copiar link'"></button>
        </div>
    @endif

    <div class="grid g-side">
        <form class="card stack" method="POST" action="{{ route('admin.convites.store') }}" autocomplete="off">
            @csrf
            <h2>Novo convite</h2>
            <div class="field"><label for="cvEmail">E-mail da pessoa (opcional)</label><input class="in" type="email" id="cvEmail" name="email" placeholder="Só esse e-mail poderá usar">@error('email')<span class="erro">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="cvDias">Vale por</label><div class="in-group"><input class="in" type="number" id="cvDias" name="dias" value="7" min="1" max="90"><span class="affix">dias</span></div></div>
            <button class="btn primary" type="submit"><x-icone nome="plus" />Gerar convite</button>
        </form>

        <div class="card">
            <div class="card-head"><div><h2>Convites</h2></div></div>
            @if ($convites->isEmpty())
                <div class="empty"><p>Nenhum convite gerado.</p></div>
            @else
                <div class="table-wrap"><table>
                    <thead><tr><th>Criado</th><th>Para</th><th>Situação</th><th>Link</th><th></th></tr></thead>
                    <tbody>@foreach ($convites as $c)
                        <tr>
                            <td class="num faint">{{ $c->created_at->format('d/m/Y') }}</td>
                            <td>{{ $c->email ?: 'qualquer e-mail' }}</td>
                            <td>
                                @if ($c->usado_em)<span class="chip good">Usado por {{ $c->usuario?->name ?? 'conta excluída' }}</span>
                                @elseif ($c->expira_em && $c->expira_em->isPast())<span class="chip">Expirado</span>
                                @else<span class="chip warn">Válido até {{ $c->expira_em?->format('d/m') }}</span>@endif
                            </td>
                            <td>@if (! $c->usado_em && ! ($c->expira_em && $c->expira_em->isPast()))<span class="mono small">…/register?convite={{ substr($c->codigo, 0, 6) }}…</span>@endif</td>
                            <td>@if (! $c->usado_em)<form method="POST" action="{{ route('admin.convites.destroy', $c) }}" class="row-actions">@csrf @method('DELETE')<button class="btn icon ghost" aria-label="Cancelar convite"><x-icone nome="trash" /></button></form>@endif</td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-head"><div><h2>Contas no sistema</h2><p>Para dar acesso de administrador a alguém: <span class="mono">php artisan usuario:admin email@exemplo.com</span></p></div></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Nome</th><th>E-mail</th><th>Desde</th><th></th></tr></thead>
            <tbody>@foreach ($usuarios as $u)
                <tr><td>{{ $u->name }}</td><td class="muted">{{ $u->email }}</td><td class="num faint">{{ $u->created_at?->format('d/m/Y') }}</td><td>@if ($u->is_admin)<span class="chip good">Administrador</span>@endif</td></tr>
            @endforeach</tbody>
        </table></div>
    </div>
</x-app-layout>
