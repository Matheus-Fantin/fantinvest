@php
    $classes = config('financas.classes');
    $somaMetas = array_sum($metas);
    $notaCls = fn ($n) => $n >= 8 ? 'hi' : ($n >= 5 ? 'mid' : 'lo');
    $vazio = ['id' => null, 'ticker' => '', 'classe' => $filtro !== 'todas' ? $filtro : 'acoes', 'quantidade' => '', 'preco_medio' => '', 'cotacao' => '', 'nota' => '7', 'atualizacao_automatica' => true];
    $comApi = array_keys(config('cotacoes.fontes'));
    $paraForm = fn ($a) => [
        'id' => $a->id, 'ticker' => $a->ticker, 'classe' => $a->classe,
        'quantidade' => valor_input($a->quantidade, 8), 'preco_medio' => valor_input($a->preco_medio, 6),
        'cotacao' => valor_input($a->cotacao, 6), 'nota' => valor_input($a->nota, 1), 'criterios' => $a->criterios ?? [],
        'atualizacao_automatica' => $a->atualizacao_automatica,
    ];
    $inicial = $errors->hasAny(['ticker', 'classe', 'quantidade', 'preco_medio', 'cotacao', 'nota'])
        ? array_merge($vazio, old(), ['id' => old('_id') ?: null])
        : $vazio;
@endphp
<x-app-layout titulo="Carteira">
    <div x-data="{
            ativo: @js($inicial),
            vazio: @js($vazio),
            criterios: @js(config('financas.criterios')),
            marcados: [],
            novo() { this.ativo = { ...this.vazio }; $dispatch('open-modal', 'ativo') },
            editar(a) { this.ativo = { ...a }; $dispatch('open-modal', 'ativo') },
            nota(a) { this.ativo = { ...a }; this.marcados = (a.criterios || []).map(String); $dispatch('open-modal', this.criterios[a.classe] ? 'nota' : 'ativo') },
            excluir(a) { this.ativo = { ...a }; $dispatch('open-modal', 'excluir') },
         }"
         x-init="@if ($errors->hasAny(['ticker', 'classe', 'quantidade', 'preco_medio', 'cotacao', 'nota'])) $nextTick(() => $dispatch('open-modal', 'ativo')) @endif"
         class="stack" style="gap:24px">

        <div class="page-head">
            <div>
                <div class="eyebrow">Carteira</div>
                <h1>Seus ativos</h1>
                <p>Cadastre cada ativo com quantidade, preço médio e cotação atual. A nota de 0 a 10 define o peso do ativo dentro da classe na regra de aporte.</p>
            </div>
            <button type="button" class="btn primary" x-on:click="novo()"><x-icone nome="plus" />Adicionar ativo</button>
        </div>

        <x-cotacoes-status />

        <div class="grid g-side">
            <form class="card" method="POST" action="{{ route('metas.update') }}"
                  x-data="{ metas: @js(array_map(fn ($v) => valor_input($v, 1), old('metas', $metas))) }">
                @csrf
                @method('PUT')
                <div class="card-head"><div><h2>Metas por classe</h2><p>Quanto do patrimônio cada classe deve ter.</p></div></div>
                <div class="stack">
                    <div class="alloc-bar" aria-hidden="true">
                        @foreach ($classes as $id => $c)
                            <div style="width:{{ $resumo['percentual'][$id] }}%;background:var(--{{ $c['cor'] }})"></div>
                        @endforeach
                    </div>
                    <span class="note">Barra acima: alocação atual.</span>
                    @foreach ($classes as $id => $c)
                        <div class="row between">
                            <span class="small"><span class="dot" style="background:var(--{{ $c['cor'] }})"></span>{{ $c['nome'] }}<br><span class="faint num">atual {{ pct($resumo['percentual'][$id]) }}</span></span>
                            <div class="in-group" style="width:110px">
                                <input class="in" name="metas[{{ $id }}]" inputmode="decimal" x-model="metas.{{ $id }}" aria-label="Meta {{ $c['nome'] }}">
                                <span class="affix">%</span>
                            </div>
                        </div>
                    @endforeach
                    <div class="divider"></div>
                    <div class="row between small" x-data="{ get soma() { return Object.values(metas).reduce((s, v) => s + fmt.parseNum(v), 0) } }">
                        <span class="muted">Soma das metas</span>
                        <span class="chip" :class="Math.abs(soma - 100) < 0.01 ? 'good' : 'bad'" x-text="fmt.pct(soma, 0)">{{ pct($somaMetas, 0) }}</span>
                    </div>
                    @error('metas')<span class="erro">{{ $message }}</span>@enderror
                    <button class="btn" type="submit">Salvar metas</button>
                </div>
            </form>

            <div class="card">
                <div class="card-head">
                    <div><h2>Ativos</h2><p>Total {{ brl($resumo['total']) }} · custo {{ brl($resumo['custo']) }}</p></div>
                    @if ($ativos->isNotEmpty())
                        <div class="seg" role="group" aria-label="Filtrar classe">
                            <a href="{{ route('carteira.index') }}" aria-pressed="{{ $filtro === 'todas' ? 'true' : 'false' }}">Todas</a>
                            @foreach ($classes as $id => $c)
                                @if ($ativos->contains('classe', $id))
                                    <a href="{{ route('carteira.index', ['classe' => $id]) }}" aria-pressed="{{ $filtro === $id ? 'true' : 'false' }}">{{ $c['nome'] }}</a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($ativos->isEmpty())
                    <div class="empty">
                        <x-icone nome="wallet" width="28" height="28" />
                        <p>Nenhum ativo ainda. Adicione o primeiro para ver a alocação e receber sugestões de aporte.</p>
                        <button type="button" class="btn primary" x-on:click="novo()"><x-icone nome="plus" />Adicionar ativo</button>
                    </div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Ativo</th><th class="n">Qtd</th><th class="n">Preço médio</th><th class="n">Cotação</th><th class="n">Valor</th><th class="n">% carteira</th><th class="n">Resultado</th><th class="n">Nota</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($grupos as $g)
                                    @php $totalGrupo = $g['ativos']->sum(fn ($a) => $a->valor()); @endphp
                                    <tr class="group">
                                        <td colspan="4"><span class="dot" style="background:var(--{{ $g['cor'] }})"></span>{{ $g['nome'] }}</td>
                                        <td class="n num">{{ brl($totalGrupo) }}</td>
                                        <td class="n num">{{ pct($resumo['total'] > 0 ? $totalGrupo / $resumo['total'] * 100 : 0) }}</td>
                                        <td colspan="3"></td>
                                    </tr>
                                    @foreach ($g['ativos'] as $a)
                                        @php $r = $a->resultadoPercentual(); @endphp
                                        <tr>
                                            <td><span class="ticker">{{ $a->ticker }}</span></td>
                                            <td class="n">{{ numero($a->quantidade, 6) }}</td>
                                            <td class="n">{{ brl($a->preco_medio) }}</td>
                                            <td class="n">
                                                {{ brl($a->cotacao) }}
                                                @if ($a->atualizacao_automatica && $a->temFonteAutomatica() && $a->variacao_dia !== null)
                                                    <span class="var-dia {{ $a->variacao_dia >= 0 ? 'up' : 'down' }}" title="Variação do dia{{ $a->cotacao_atualizada_em ? ' · atualizada '.$a->cotacao_atualizada_em->diffForHumans() : '' }}">{{ $a->variacao_dia >= 0 ? '▲' : '▼' }} {{ pct(abs($a->variacao_dia), 2) }}</span>
                                                @elseif (! $a->atualizacao_automatica || ! $a->temFonteAutomatica())
                                                    <span class="var-dia faint" title="Cotação digitada por você">manual</span>
                                                @endif
                                            </td>
                                            <td class="n"><strong>{{ brl($a->valor()) }}</strong></td>
                                            <td class="n">{{ pct($resumo['total'] > 0 ? $a->valor() / $resumo['total'] * 100 : 0) }}</td>
                                            <td class="n {{ $r >= 0 ? 'up' : 'down' }}">{{ $r >= 0 ? '+' : '' }}{{ pct($r) }}</td>
                                            <td class="n">
                                                <button type="button" class="nota {{ $notaCls($a->nota) }}" x-on:click="nota(@js($paraForm($a)))" title="Calcular nota com critérios">{{ numero($a->nota, 1) }}</button>
                                            </td>
                                            <td>
                                                <div class="row-actions">
                                                    <button type="button" class="btn icon ghost" x-on:click="editar(@js($paraForm($a)))" aria-label="Editar {{ $a->ticker }}"><x-icone nome="edit" /></button>
                                                    <button type="button" class="btn icon ghost" x-on:click="excluir(@js($paraForm($a)))" aria-label="Excluir {{ $a->ticker }}"><x-icone nome="trash" /></button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="note" style="margin-top:12px">Toque na nota para responder o checklist de critérios. Nota 0 tira o ativo dos aportes, sem vendê-lo.</p>
            </div>
        </div>

        {{-- Adicionar / editar ativo --}}
        <x-modal name="ativo">
            <form method="POST" :action="ativo.id ? '{{ url('carteira') }}/' + ativo.id : '{{ route('carteira.store') }}'" class="stack" autocomplete="off">
                @csrf
                <template x-if="ativo.id"><input type="hidden" name="_method" value="PUT"></template>
                <input type="hidden" name="_id" :value="ativo.id">
                <div class="row between">
                    <h2 x-text="ativo.id ? 'Editar ' + ativo.ticker : 'Adicionar ativo'"></h2>
                    <button type="button" class="btn icon ghost" x-on:click="$dispatch('close-modal', 'ativo')" aria-label="Fechar"><x-icone nome="x" /></button>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label for="aTicker">Ativo</label>
                        <input class="in @error('ticker') invalido @enderror" id="aTicker" name="ticker" x-model="ativo.ticker" placeholder="Ex.: ITSA4" maxlength="40" required>
                        @error('ticker')<span class="erro">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="aClasse">Classe</label>
                        <select class="in" id="aClasse" name="classe" x-model="ativo.classe">
                            @foreach ($classes as $id => $c)<option value="{{ $id }}">{{ $c['nome'] }}</option>@endforeach
                        </select>
                    </div>
                    <x-campo nome="quantidade" rotulo="Quantidade" id="aQtd" x-model="ativo.quantidade" dica="Para renda fixa, use 1" />
                    <x-campo nome="preco_medio" rotulo="Preço médio" id="aPm" pre="R$" x-model="ativo.preco_medio" />
                    <x-campo nome="cotacao" rotulo="Cotação atual" id="aCot" pre="R$" x-model="ativo.cotacao" dica="Para renda fixa, o saldo atual" />
                    <x-campo nome="nota" rotulo="Nota (0 a 10)" id="aNota" x-model="ativo.nota" dica="0 = não aportar" />
                    <div class="field full" x-show="@js($comApi).includes(ativo.classe)">
                        <label class="check"><input type="checkbox" name="atualizacao_automatica" value="1" x-model="ativo.atualizacao_automatica">Atualizar a cotação automaticamente pela internet</label>
                        <span class="hint" x-text="ativo.classe === 'cripto' ? 'Use o símbolo da moeda: BTC, ETH, SOL…' : 'Use o código da B3: ITSA4, HGLG11, IVVB11…'"></span>
                    </div>
                </div>
                <div class="row" style="justify-content:flex-end">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'ativo')">Cancelar</button>
                    <button class="btn primary" type="submit" x-text="ativo.id ? 'Salvar' : 'Adicionar'"></button>
                </div>
            </form>
        </x-modal>

        {{-- Checklist de critérios (nota) --}}
        <x-modal name="nota">
            <form method="POST" :action="'{{ url('carteira') }}/' + ativo.id + '/nota'" class="stack">
                @csrf
                @method('PUT')
                <div class="row between">
                    <div><div class="eyebrow" x-text="@js(array_map(fn ($c) => $c['nome'], $classes))[ativo.classe]"></div><h2 x-text="'Nota de ' + ativo.ticker"></h2></div>
                    <button type="button" class="btn icon ghost" x-on:click="$dispatch('close-modal', 'nota')" aria-label="Fechar"><x-icone nome="x" /></button>
                </div>
                <p class="muted small">Marque o que o ativo cumpre. Cada critério vale 1 ponto. Pesquise os dados em sites de análise antes de responder.</p>
                <div class="check-list">
                    <template x-for="(c, i) in (criterios[ativo.classe] || [])" :key="i">
                        <label><input type="checkbox" name="criterios[]" :value="i" x-model="marcados"><span x-text="c"></span></label>
                    </template>
                </div>
                <div class="row between">
                    <span class="row"><span class="score num" x-text="marcados.length"></span><span class="muted" x-text="'/ ' + (criterios[ativo.classe] || []).length"></span></span>
                    <span class="row">
                        <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'nota')">Cancelar</button>
                        <button class="btn primary" type="submit">Aplicar nota</button>
                    </span>
                </div>
            </form>
        </x-modal>

        {{-- Confirmação de exclusão --}}
        <x-modal name="excluir">
            <form method="POST" :action="'{{ url('carteira') }}/' + ativo.id" class="stack">
                @csrf
                @method('DELETE')
                <h2 x-text="'Excluir ' + ativo.ticker + ' da carteira?'"></h2>
                <p class="muted small">O ativo sai da carteira e dos cálculos de aporte. Isso não pode ser desfeito.</p>
                <div class="row" style="justify-content:flex-end">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'excluir')">Cancelar</button>
                    <button class="btn danger" type="submit">Excluir</button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
