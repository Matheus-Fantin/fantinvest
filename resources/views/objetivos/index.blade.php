@php
    $vazio = ['id' => null, 'nome' => '', 'valor_alvo' => '', 'valor_atual' => '', 'data_alvo' => '', 'rendimento_anual' => '10'];
    $campos = ['nome', 'valor_alvo', 'valor_atual', 'data_alvo', 'rendimento_anual'];
    $inicial = $errors->hasAny($campos) ? array_merge($vazio, old(), ['id' => old('_id') ?: null]) : $vazio;
    $paraForm = fn ($g) => [
        'id' => $g->id, 'nome' => $g->nome, 'valor_alvo' => moeda_input($g->valor_alvo), 'valor_atual' => moeda_input($g->valor_atual),
        'data_alvo' => $g->data_alvo?->format('Y-m-d') ?? '', 'rendimento_anual' => valor_input($g->rendimento_anual),
    ];
@endphp
<x-app-layout titulo="Objetivos">
    <div class="stack" style="gap:24px"
         x-data="{
            objetivo: @js($inicial), vazio: @js($vazio), deposito: { id: null, nome: '', valor: '', retirar: false },
            novo() { this.objetivo = { ...this.vazio }; $dispatch('open-modal', 'objetivo') },
            editar(g) { this.objetivo = { ...g }; $dispatch('open-modal', 'objetivo') },
            guardar(g, retirar = false) { this.deposito = { id: g.id, nome: g.nome, valor: '', retirar }; $dispatch('open-modal', 'deposito') },
            excluir(g) { this.objetivo = { ...g }; $dispatch('open-modal', 'excluir-objetivo') },
         }"
         x-init="@if ($errors->hasAny($campos)) $nextTick(() => $dispatch('open-modal', 'objetivo')) @endif">

        <div class="page-head">
            <div>
                <div class="eyebrow">Objetivos</div>
                <h1>Para onde vai o seu dinheiro</h1>
                <p>Viagem, carro, casa, troca do celular. Defina quanto e quando, e o FantInvest diz quanto guardar por mês, já contando o rendimento.</p>
            </div>
            <button type="button" class="btn primary" x-on:click="novo()"><x-icone nome="plus" />Novo objetivo</button>
        </div>

        @if ($objetivos->isNotEmpty())
            <div class="grid g3">
                <div class="card kpi"><span class="label">Guardado nos objetivos</span><span class="value">{{ brl($totalGuardado) }}</span><span class="sub">de {{ brl($totalAlvo) }} no total</span></div>
                <div class="card kpi"><span class="label">Progresso geral</span><span class="value">{{ pct($totalAlvo > 0 ? min(100, $totalGuardado / $totalAlvo * 100) : 0, 0) }}</span><span class="sub">{{ $objetivos->filter->concluido()->count() }} de {{ $objetivos->count() }} concluídos</span></div>
                <div class="card kpi"><span class="label">Guardar por mês</span><span class="value" style="color:var(--accent)">{{ brl($totalMensal) }}</span><span class="sub">somando os objetivos com data</span></div>
            </div>
        @endif

        @if ($objetivos->isEmpty())
            <div class="card">
                <div class="empty">
                    <x-icone nome="flag" width="28" height="28" />
                    <p>Nenhum objetivo ainda. Crie o primeiro, por exemplo "Viagem para o Chile, R$ 12.000 até dezembro de 2027".</p>
                    <button type="button" class="btn primary" x-on:click="novo()"><x-icone nome="plus" />Novo objetivo</button>
                </div>
            </div>
        @else
            <div class="goal-grid">
                @foreach ($objetivos as $g)
                    @php $mensal = $g->aporteMensalNecessario(); @endphp
                    <div class="card goal">
                        <div class="row between" style="align-items:flex-start">
                            <div style="min-width:0">
                                <h2 style="overflow-wrap:anywhere">{{ $g->nome }}</h2>
                                <span class="small muted">
                                    @if ($g->data_alvo) até {{ $g->data_alvo->translatedFormat('M \d\e Y') }} @else sem data definida @endif
                                    @if ($g->rendimento_anual > 0) · rende {{ pct($g->rendimento_anual) }} a.a. @endif
                                </span>
                            </div>
                            @if ($g->concluido())
                                <span class="chip good"><x-icone nome="check" />Concluído</span>
                            @elseif ($g->prazoVencido())
                                <span class="chip bad"><x-icone nome="alert" />Prazo vencido</span>
                            @else
                                <span class="chip">{{ pct($g->percentual(), 0) }}</span>
                            @endif
                        </div>
                        <div class="row between">
                            <span class="kpi"><span class="label">Guardado</span><span class="value">{{ brl($g->valor_atual) }}</span></span>
                            <span class="kpi" style="text-align:right"><span class="label">Meta</span><span class="value">{{ brl($g->valor_alvo) }}</span></span>
                        </div>
                        <div class="meter"><div style="width:{{ $g->percentual() }}%"></div></div>
                        <div class="row between small">
                            @if ($g->concluido())
                                <span class="muted">Meta alcançada.</span>
                            @elseif ($mensal !== null)
                                <span class="muted">Guardar por mês</span>
                                <strong class="num" style="color:var(--accent)">{{ brl($mensal) }} · {{ $g->mesesRestantes() }} {{ $g->mesesRestantes() === 1 ? 'mês' : 'meses' }}</strong>
                            @else
                                <span class="muted">Faltam {{ brl($g->valor_alvo - $g->valor_atual) }}. Defina uma data para ver quanto guardar por mês.</span>
                            @endif
                        </div>
                        <div class="row" style="justify-content:space-between">
                            <span class="row" style="gap:6px">
                                <button type="button" class="btn sm primary" x-on:click="guardar(@js($paraForm($g)))"><x-icone nome="plus" />Guardar</button>
                                <button type="button" class="btn sm" x-on:click="guardar(@js($paraForm($g)), true)">Retirar</button>
                            </span>
                            <span class="row-actions">
                                <button type="button" class="btn icon ghost" x-on:click="editar(@js($paraForm($g)))" aria-label="Editar {{ $g->nome }}"><x-icone nome="edit" /></button>
                                <button type="button" class="btn icon ghost" x-on:click="excluir(@js($paraForm($g)))" aria-label="Excluir {{ $g->nome }}"><x-icone nome="trash" /></button>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <x-modal name="objetivo">
            <form method="POST" :action="objetivo.id ? '{{ url('objetivos') }}/' + objetivo.id : '{{ route('objetivos.store') }}'" class="stack" autocomplete="off">
                @csrf
                <template x-if="objetivo.id"><input type="hidden" name="_method" value="PUT"></template>
                <input type="hidden" name="_id" :value="objetivo.id">
                <div class="row between">
                    <h2 x-text="objetivo.id ? 'Editar objetivo' : 'Novo objetivo'"></h2>
                    <button type="button" class="btn icon ghost" x-on:click="$dispatch('close-modal', 'objetivo')" aria-label="Fechar"><x-icone nome="x" /></button>
                </div>
                <div class="form-grid">
                    <div class="field full">
                        <label for="gNome">Nome</label>
                        <input class="in @error('nome') invalido @enderror" id="gNome" name="nome" x-model="objetivo.nome" placeholder="Ex.: Viagem para o Chile" maxlength="60" required>
                        @error('nome')<span class="erro">{{ $message }}</span>@enderror
                    </div>
                    <x-campo nome="valor_alvo" rotulo="Quanto precisa" id="gAlvo" pre="R$" x-model="objetivo.valor_alvo" required />
                    <x-campo nome="valor_atual" rotulo="Já guardado" id="gAtual" pre="R$" x-model="objetivo.valor_atual" />
                    <div class="field">
                        <label for="gData">Até quando</label>
                        <input class="in @error('data_alvo') invalido @enderror" type="date" id="gData" name="data_alvo" x-model="objetivo.data_alvo">
                        @error('data_alvo')<span class="erro">{{ $message }}</span>@enderror
                    </div>
                    <x-campo nome="rendimento_anual" rotulo="Rendimento do dinheiro guardado" id="gRend" suf="% a.a." x-model="objetivo.rendimento_anual" dica="Ex.: 10% num CDB de liquidez diária. Use 0 se ficar parado." />
                </div>
                <div class="row" style="justify-content:flex-end">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'objetivo')">Cancelar</button>
                    <button class="btn primary" type="submit" x-text="objetivo.id ? 'Salvar' : 'Criar objetivo'"></button>
                </div>
            </form>
        </x-modal>

        <x-modal name="deposito">
            <form method="POST" :action="'{{ url('objetivos') }}/' + deposito.id + '/depositar'" class="stack" autocomplete="off"
                  x-on:submit="$refs.valorReal.value = (deposito.retirar ? '-' : '') + deposito.valor">
                @csrf
                <div class="row between">
                    <h2 x-text="(deposito.retirar ? 'Retirar de ' : 'Guardar em ') + deposito.nome"></h2>
                    <button type="button" class="btn icon ghost" x-on:click="$dispatch('close-modal', 'deposito')" aria-label="Fechar"><x-icone nome="x" /></button>
                </div>
                <input type="hidden" name="valor" x-ref="valorReal">
                <div class="field">
                    <label for="dValor">Valor</label>
                    <div class="in-group"><span class="affix">R$</span><input class="in" data-moeda id="dValor" inputmode="decimal" x-model="deposito.valor" required></div>
                    @error('valor')<span class="erro">{{ $message }}</span>@enderror
                </div>
                <label class="check" x-show="!deposito.retirar"><input type="checkbox" name="lancar_no_orcamento" value="1" checked>Lançar no orçamento deste mês (categoria Objetivos)</label>
                <div class="row" style="justify-content:flex-end">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'deposito')">Cancelar</button>
                    <button class="btn primary" type="submit" x-text="deposito.retirar ? 'Retirar' : 'Guardar'"></button>
                </div>
            </form>
        </x-modal>

        <x-modal name="excluir-objetivo">
            <form method="POST" :action="'{{ url('objetivos') }}/' + objetivo.id" class="stack">
                @csrf
                @method('DELETE')
                <h2 x-text="'Excluir o objetivo ' + objetivo.nome + '?'"></h2>
                <p class="muted small">Os lançamentos já feitos no orçamento continuam lá.</p>
                <div class="row" style="justify-content:flex-end">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'excluir-objetivo')">Cancelar</button>
                    <button class="btn danger" type="submit">Excluir</button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
