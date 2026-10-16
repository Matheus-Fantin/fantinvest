@php
    $porAno = collect($linhas)->groupBy(fn ($l) => $l['data']->year)->map(fn ($g, $ano) => [
        'ano' => $ano,
        'amortizacao' => round($g->sum('amortizacao'), 2),
        'juros' => round($g->sum('juros'), 2),
        'encargos' => round($g->sum('seguro') + $g->sum('taxa'), 2),
        'saldo' => round($g->last()['saldo'], 2),
    ])->values();
    $proxima = $resumo['proxima'];
    $tipos = ['parcela' => 'Parcela', 'amortizacao' => 'Amortização extra', 'obra' => 'Encargo de obra'];
@endphp
<x-app-layout :titulo="$f->nome">
    <div class="stack" style="gap:24px"
         x-data="{ aba: ['resumo', 'parcelas', 'obra', 'contrato'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'resumo',
                   ir(a) { this.aba = a; history.replaceState(null, '', '#' + a) } }">
        <div class="page-head">
            <div>
                <div class="eyebrow">Imóvel · {{ $f->banco }} · {{ strtoupper($f->sistema) }}</div>
                <h1>{{ $f->nome }}</h1>
                <p>{{ brl($f->valor_financiado) }} financiados em {{ $f->prazo_meses }} meses a {{ pct($f->taxa_juros_anual, 2) }} a.a. ({{ pct($f->taxaMensal() * 100, 4) }} a.m.). Primeira parcela em {{ $f->primeira_parcela->format('d/m/Y') }}.</p>
            </div>
            @if ($outros->isNotEmpty())
                <a class="btn" href="{{ route('imovel.index', ['lista' => 1]) }}">Outros financiamentos</a>
            @endif
        </div>

        <div class="tabs" role="tablist">
            @foreach (['resumo' => 'Resumo', 'parcelas' => 'Parcelas', 'obra' => 'Obra e gastos', 'contrato' => 'Contrato'] as $id => $nome)
                <button type="button" role="tab" :aria-selected="aba === '{{ $id }}'" x-on:click="ir('{{ $id }}')">{{ $nome }}</button>
            @endforeach
        </div>

        {{-- ============ RESUMO ============ --}}
        <div x-show="aba === 'resumo'" class="stack" style="gap:24px">
            @if ($resumo['parcelas_atrasadas'] > 0)
                <div class="banner warn"><span><strong>{{ $resumo['parcelas_atrasadas'] }} {{ $resumo['parcelas_atrasadas'] === 1 ? 'parcela vencida' : 'parcelas vencidas' }} sem registro de pagamento.</strong> Se já pagou, registre na aba Parcelas.</span><button type="button" class="btn sm" x-on:click="ir('parcelas')">Registrar</button></div>
            @endif
            <div class="card">
                <div class="grid g4" style="align-items:end">
                    <div class="kpi"><span class="label">Saldo devedor hoje</span><span class="big-num">{{ brl($resumo['saldo_devedor']) }}</span></div>
                    <div class="kpi"><span class="label">Quitado</span><span class="value">{{ pct($resumo['percentual_quitado']) }}</span><span class="sub">{{ $resumo['parcelas_pagas'] }} de {{ $resumo['parcelas_total'] }} parcelas pagas</span></div>
                    <div class="kpi"><span class="label">Próxima parcela</span>
                        @if ($proxima)<span class="value">{{ brl($proxima['total']) }}</span><span class="sub">nº {{ $proxima['numero'] }} · vence {{ $proxima['data']->format('d/m/Y') }}</span>
                        @else<span class="value up">Quitado</span>@endif</div>
                    <div class="kpi"><span class="label">Quitação prevista</span><span class="value">{{ $resumo['quitacao']?->translatedFormat('M/Y') ?? '—' }}</span>
                        <span class="sub">@if ($resumo['meses_economizados'] > 0)<span class="up">{{ $resumo['meses_economizados'] }} meses antes do prazo</span>@else no prazo do contrato @endif</span></div>
                </div>
                <div class="meter" style="margin-top:16px;height:10px"><div style="width:{{ min(100, $resumo['percentual_quitado']) }}%"></div></div>
            </div>

            <div class="grid g4">
                <div class="card kpi"><span class="label">Total já pago</span><span class="value">{{ brl($resumo['total_pago']) }}</span><span class="sub">parcelas {{ brl($resumo['pago_parcelas']) }} · extras {{ brl($resumo['pago_extras']) }}</span></div>
                <div class="card kpi"><span class="label">Juros já pagos</span><span class="value down">{{ brl($resumo['juros_pagos']) }}</span></div>
                <div class="card kpi"><span class="label">Juros que ainda vai pagar</span><span class="value down">{{ brl($resumo['juros_a_pagar']) }}</span><span class="sub">cai com amortização extra</span></div>
                <div class="card kpi"><span class="label">Custo total do contrato</span><span class="value">{{ brl($resumo['total_contrato']) }}</span><span class="sub">{{ $f->valor_financiado > 0 ? numero($resumo['total_contrato'] / $f->valor_financiado, 2).'× o valor financiado' : '' }}</span></div>
            </div>

            <div class="grid g2">
                <div class="card">
                    <div class="card-head"><div><h2>Para onde vai cada parcela</h2><p>Por ano: amortização (abate a dívida), juros e seguro + taxa.</p></div></div>
                    <x-legenda :itens="[['s1', 'Amortização', true], ['s2', 'Juros', true], ['s4', 'Seguro e taxa', true]]" />
                    <div class="chart-box"><canvas data-grafico="financiamentoAnos" data-dados='@json($porAno)' role="img" aria-label="Composição das parcelas por ano"></canvas></div>
                </div>
                <div class="card">
                    <div class="card-head"><div><h2>Saldo devedor</h2><p>No fim de cada ano, já com as amortizações extras.</p></div></div>
                    <div class="chart-box"><canvas data-grafico="saldoDevedor" data-dados='@json($porAno)' role="img" aria-label="Saldo devedor por ano"></canvas></div>
                </div>
            </div>

            <div class="grid g3">
                <div class="card kpi"><span class="label">Liberado pelo banco</span><span class="value">{{ brl($obra['liberado']) }}</span><span class="sub">falta liberar {{ brl($obra['a_liberar']) }}</span></div>
                <div class="card kpi"><span class="label">Gasto na obra</span><span class="value">{{ brl($obra['gasto_total']) }}</span><span class="sub">{{ brl($obra['gasto_financiamento']) }} do financiamento · {{ brl($obra['gasto_proprio']) }} próprio</span></div>
                <div class="card kpi"><span class="label">Saldo do dinheiro liberado</span><span class="value {{ $obra['saldo_liberado'] < 0 ? 'down' : '' }}">{{ brl($obra['saldo_liberado']) }}</span><span class="sub">liberado − gasto com financiamento</span></div>
            </div>
        </div>

        {{-- ============ PARCELAS ============ --}}
        <div x-show="aba === 'parcelas'" x-cloak class="stack" style="gap:24px">
            <div class="grid g2">
                <form class="card stack" method="POST" action="{{ route('imovel.pagamento', $f) }}" autocomplete="off">
                    @csrf
                    <input type="hidden" name="tipo" value="parcela">
                    <h2>Registrar parcela paga</h2>
                    <div class="form-grid">
                        <x-campo nome="numero" rotulo="Número da parcela" :valor="old('numero', $proxima['numero'] ?? '')" />
                        <x-campo nome="valor" rotulo="Valor pago" pre="R$" :valor="old('valor', $proxima ? moeda_input($proxima['total']) : '')" required dica="Use o valor do boleto; pode ter diferença de centavos." />
                        <div class="field"><label for="ppData">Data do pagamento</label><input class="in" type="date" id="ppData" name="data" value="{{ now()->toDateString() }}" required></div>
                        <div class="field"><label for="ppConta">Saiu da conta</label><select class="in" id="ppConta" name="account_id"><option value="">Nenhuma</option>@foreach ($contas as $c)<option value="{{ $c->id }}">{{ $c->nome }}</option>@endforeach</select></div>
                    </div>
                    <label class="check"><input type="checkbox" name="lancar_no_orcamento" value="1" checked>Lançar no orçamento (categoria Moradia)</label>
                    <button class="btn primary" type="submit"><x-icone nome="check" />Registrar pagamento</button>
                </form>

                <form class="card stack" method="POST" action="{{ route('imovel.pagamento', $f) }}" autocomplete="off">
                    @csrf
                    <input type="hidden" name="tipo" value="amortizacao">
                    <h2>Amortização extra</h2>
                    <p class="small muted">Dinheiro a mais (FGTS, 13º, sobra) que abate direto o saldo devedor e corta juros.</p>
                    <div class="form-grid">
                        <x-campo nome="valor" rotulo="Valor" pre="R$" required />
                        <div class="field"><label for="aeData">Data</label><input class="in" type="date" id="aeData" name="data" value="{{ now()->toDateString() }}" required></div>
                        <div class="field full"><label for="aeReduzir">O que reduzir</label>
                            <select class="in" id="aeReduzir" name="reduzir">
                                <option value="prazo">Prazo (termina antes, economiza mais juros)</option>
                                <option value="parcela">Valor da parcela (mesmo prazo, parcela menor)</option>
                            </select></div>
                        <div class="field full"><label for="aeConta">Saiu da conta</label><select class="in" id="aeConta" name="account_id"><option value="">Nenhuma</option>@foreach ($contas as $c)<option value="{{ $c->id }}">{{ $c->nome }}</option>@endforeach</select></div>
                    </div>
                    <label class="check"><input type="checkbox" name="lancar_no_orcamento" value="1">Lançar no orçamento (categoria Moradia)</label>
                    @error('reduzir')<span class="erro">{{ $message }}</span>@enderror
                    <button class="btn" type="submit">Registrar amortização</button>
                </form>
            </div>
            @error('numero')<div class="banner warn"><span>{{ $message }}</span></div>@enderror

            <div class="card">
                <div class="card-head"><div><h2>Cronograma</h2><p>{{ count($linhas) }} parcelas. Seguro e taxa fixos; correção pela TR não incluída.</p></div>
                    <x-legenda :itens="[['good', 'Paga', true], ['bad', 'Vencida sem registro', true], ['accent', 'Próxima', true]]" /></div>
                <div class="table-wrap" style="max-height:520px;overflow-y:auto">
                    <table>
                        <thead style="position:sticky;top:0;background:var(--surface);z-index:1"><tr><th>Nº</th><th>Vencimento</th><th class="n">Amortização</th><th class="n">Juros</th><th class="n">Seguro + taxa</th><th class="n">Parcela</th><th class="n">Saldo devedor</th><th>Situação</th></tr></thead>
                        <tbody>
                            @foreach ($linhas as $l)
                                <tr class="{{ $l['pago'] ? 'pago' : ($l['atrasada'] ? 'atrasada' : ($proxima && $l['numero'] === $proxima['numero'] ? 'proxima' : '')) }}">
                                    <td class="num">{{ $l['numero'] }}</td>
                                    <td class="num">{{ $l['data']->format('d/m/Y') }}</td>
                                    <td class="n">{{ brl($l['amortizacao']) }}@if ($l['amortizacao_extra'] > 0)<br><span class="small up">+ {{ brl($l['amortizacao_extra']) }} extra</span>@endif</td>
                                    <td class="n">{{ brl($l['juros']) }}</td>
                                    <td class="n">{{ brl($l['seguro'] + $l['taxa']) }}</td>
                                    <td class="n"><strong>{{ brl($l['total']) }}</strong></td>
                                    <td class="n">{{ brl($l['saldo']) }}</td>
                                    <td>
                                        @if ($l['pago'])<span class="chip good"><x-icone nome="check" />{{ brl($l['valor_pago']) }} em {{ $l['data_pagamento']->format('d/m') }}</span>
                                        @elseif ($l['atrasada'])<span class="chip bad">Vencida</span>
                                        @elseif ($proxima && $l['numero'] === $proxima['numero'])<span class="chip">Próxima</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><div><h2>Pagamentos registrados</h2><p>Excluir um pagamento também remove o lançamento no orçamento.</p></div></div>
                @if ($pagamentos->isEmpty())
                    <div class="empty"><p>Nenhum pagamento registrado ainda.</p></div>
                @else
                    <div class="table-wrap"><table>
                        <thead><tr><th>Data</th><th>Tipo</th><th>Parcela</th><th>Detalhe</th><th class="n">Valor</th><th></th></tr></thead>
                        <tbody>@foreach ($pagamentos as $p)
                            <tr>
                                <td class="num faint">{{ $p->data->format('d/m/Y') }}</td>
                                <td>{{ $tipos[$p->tipo] }}</td>
                                <td class="num">{{ $p->numero ?? '—' }}</td>
                                <td class="muted small">{{ $p->tipo === 'amortizacao' ? 'reduz '.($p->reduzir === 'prazo' ? 'o prazo' : 'a parcela') : '' }}{{ $p->transaction_id ? ' · no orçamento' : '' }}</td>
                                <td class="n"><strong>{{ brl($p->valor) }}</strong></td>
                                <td><form method="POST" action="{{ route('imovel.excluir', [$f, 'pagamento', $p->id]) }}" class="row-actions">@csrf @method('DELETE')<button class="btn icon ghost" aria-label="Excluir pagamento"><x-icone nome="trash" /></button></form></td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </div>
        </div>

        {{-- ============ OBRA ============ --}}
        <div x-show="aba === 'obra'" x-cloak class="stack" style="gap:24px">
            <div class="grid g4">
                <div class="card kpi"><span class="label">Liberado pelo banco</span><span class="value">{{ brl($obra['liberado']) }}</span><span class="sub">de {{ brl($f->valor_financiado) }}</span></div>
                <div class="card kpi"><span class="label">Ainda a liberar</span><span class="value">{{ brl($obra['a_liberar']) }}</span></div>
                <div class="card kpi"><span class="label">Gasto total na obra</span><span class="value">{{ brl($obra['gasto_total']) }}</span></div>
                <div class="card kpi"><span class="label">Recursos próprios restantes</span><span class="value {{ $obra['proprio_restante'] < 0 ? 'down' : '' }}">{{ brl($obra['proprio_restante']) }}</span><span class="sub">de {{ brl($f->recursos_proprios) }} informados</span></div>
            </div>

            <div class="grid g2">
                <form class="card stack" method="POST" action="{{ route('imovel.gasto', $f) }}" autocomplete="off">
                    @csrf
                    <h2>Lançar gasto da obra</h2>
                    <div class="form-grid">
                        <div class="field full"><label for="goDesc">Descrição</label><input class="in" id="goDesc" name="descricao" value="{{ old('descricao') }}" placeholder="Ex.: cimento, areia e brita" maxlength="80" required>@error('descricao')<span class="erro">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="goCat">Categoria</label><select class="in" id="goCat" name="categoria">@foreach ($categorias as $c)<option @selected(old('categoria') === $c)>{{ $c }}</option>@endforeach</select></div>
                        <x-campo nome="valor" rotulo="Valor" pre="R$" required />
                        <div class="field"><label for="goData">Data</label><input class="in" type="date" id="goData" name="data" value="{{ now()->toDateString() }}" required></div>
                        <div class="field"><label for="goOrigem">Pago com</label><select class="in" id="goOrigem" name="origem"><option value="financiamento">Dinheiro do financiamento</option><option value="proprio">Dinheiro próprio</option></select></div>
                        <div class="field full"><label for="goForn">Fornecedor</label><input class="in" id="goForn" name="fornecedor" maxlength="60" placeholder="Ex.: Casa do Construtor"></div>
                    </div>
                    <button class="btn primary" type="submit"><x-icone nome="plus" />Lançar gasto</button>
                </form>

                <div class="stack">
                    <form class="card stack" method="POST" action="{{ route('imovel.liberacao', $f) }}" autocomplete="off">
                        @csrf
                        <h2>Liberação do banco</h2>
                        <p class="small muted">Na construção, a Caixa libera o dinheiro em etapas, conforme a medição da obra.</p>
                        <div class="form-grid">
                            <x-campo nome="valor" rotulo="Valor liberado" pre="R$" required />
                            <div class="field"><label for="lbData">Data</label><input class="in" type="date" id="lbData" name="data" value="{{ now()->toDateString() }}" required></div>
                            <div class="field full"><label for="lbDesc">Descrição</label><input class="in" id="lbDesc" name="descricao" maxlength="80" placeholder="Ex.: 2ª medição (25%)"></div>
                        </div>
                        <button class="btn" type="submit">Registrar liberação</button>
                    </form>
                    <form class="card stack" method="POST" action="{{ route('imovel.pagamento', $f) }}" autocomplete="off">
                        @csrf
                        <input type="hidden" name="tipo" value="obra">
                        <h2>Encargos da fase de obra</h2>
                        <p class="small muted">Antes das parcelas começarem, a Caixa cobra juros e encargos sobre o que já liberou.</p>
                        <div class="form-grid">
                            <x-campo nome="valor" rotulo="Valor pago" pre="R$" required />
                            <div class="field"><label for="eoData">Data</label><input class="in" type="date" id="eoData" name="data" value="{{ now()->toDateString() }}" required></div>
                        </div>
                        <label class="check"><input type="checkbox" name="lancar_no_orcamento" value="1" checked>Lançar no orçamento (categoria Moradia)</label>
                        <button class="btn" type="submit">Registrar encargo</button>
                    </form>
                </div>
            </div>

            @if (! empty($obra['por_categoria']))
                <div class="card">
                    <div class="card-head"><div><h2>Gastos por categoria</h2></div></div>
                    <div class="chart-box" style="height:{{ max(200, count($obra['por_categoria']) * 36) }}px"><canvas data-grafico="obra" data-dados='@json($obra['por_categoria'])' role="img" aria-label="Gastos da obra por categoria"></canvas></div>
                </div>
            @endif

            <div class="grid g2">
                <div class="card">
                    <div class="card-head"><div><h2>Gastos da obra</h2><p>{{ $gastos->count() }} lançamentos</p></div></div>
                    @if ($gastos->isEmpty())
                        <div class="empty"><p>Nenhum gasto lançado.</p></div>
                    @else
                        <div class="table-wrap"><table>
                            <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th class="n">Valor</th><th></th></tr></thead>
                            <tbody>@foreach ($gastos as $g)
                                <tr>
                                    <td class="num faint">{{ $g->data->format('d/m/Y') }}</td>
                                    <td>{{ $g->descricao }}<div class="small faint">{{ $g->fornecedor }}{{ $g->fornecedor ? ' · ' : '' }}{{ $g->origem === 'proprio' ? 'dinheiro próprio' : 'financiamento' }}</div></td>
                                    <td class="muted">{{ $g->categoria }}</td>
                                    <td class="n"><strong>{{ brl($g->valor) }}</strong></td>
                                    <td><form method="POST" action="{{ route('imovel.excluir', [$f, 'gasto', $g->id]) }}" class="row-actions">@csrf @method('DELETE')<button class="btn icon ghost" aria-label="Excluir gasto"><x-icone nome="trash" /></button></form></td>
                                </tr>
                            @endforeach</tbody>
                        </table></div>
                    @endif
                </div>
                <div class="card">
                    <div class="card-head"><div><h2>Liberações do banco</h2></div></div>
                    @if ($liberacoes->isEmpty())
                        <div class="empty"><p>Nenhuma liberação registrada.</p></div>
                    @else
                        <div class="table-wrap"><table>
                            <thead><tr><th>Data</th><th>Descrição</th><th class="n">Valor</th><th></th></tr></thead>
                            <tbody>@foreach ($liberacoes as $l)
                                <tr>
                                    <td class="num faint">{{ $l->data->format('d/m/Y') }}</td>
                                    <td>{{ $l->descricao ?: 'Liberação' }}</td>
                                    <td class="n"><strong>{{ brl($l->valor) }}</strong></td>
                                    <td><form method="POST" action="{{ route('imovel.excluir', [$f, 'liberacao', $l->id]) }}" class="row-actions">@csrf @method('DELETE')<button class="btn icon ghost" aria-label="Excluir liberação"><x-icone nome="trash" /></button></form></td>
                                </tr>
                            @endforeach</tbody>
                        </table></div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============ CONTRATO ============ --}}
        <div x-show="aba === 'contrato'" x-cloak class="stack" style="gap:24px">
            <form class="card stack" method="POST" action="{{ route('imovel.update', $f) }}" autocomplete="off">
                @csrf @method('PUT')
                <div class="card-head"><div><h2>Dados do contrato</h2><p>Mudar taxa, prazo ou sistema recalcula todo o cronograma.</p></div></div>
                @include('imovel._contrato', ['f' => $f])
                <div class="row between">
                    <button type="button" class="btn danger" x-on:click="$dispatch('open-modal', 'excluir-financiamento')">Excluir financiamento</button>
                    <button class="btn primary" type="submit">Salvar contrato</button>
                </div>
            </form>
            @if ($f->observacoes)
                <div class="card"><h2>Observações</h2><p class="muted" style="white-space:pre-line">{{ $f->observacoes }}</p></div>
            @endif
        </div>

        <x-modal name="excluir-financiamento">
            <form method="POST" action="{{ route('imovel.destroy', $f) }}" class="stack">
                @csrf @method('DELETE')
                <h2>Excluir "{{ $f->nome }}"?</h2>
                <p class="muted small">Apaga o contrato, as parcelas registradas, as liberações e os gastos da obra. Os lançamentos já feitos no orçamento continuam lá.</p>
                <div class="row" style="justify-content:flex-end">
                    <button type="button" class="btn" x-on:click="$dispatch('close-modal', 'excluir-financiamento')">Cancelar</button>
                    <button class="btn danger" type="submit">Excluir</button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
