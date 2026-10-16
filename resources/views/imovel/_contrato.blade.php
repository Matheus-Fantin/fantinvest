{{-- Campos do contrato de financiamento. Recebe $f (Financing ou null). --}}
@php
    $v = fn ($campo, $padrao = '') => old($campo, $f ? $f->{$campo} : $padrao);
    $m = fn ($campo, $padrao = '') => old($campo, $f ? moeda_input($f->{$campo}) : $padrao);
@endphp
<div class="form-grid">
    <div class="field full"><label for="iNome">Nome</label><input class="in" id="iNome" name="nome" value="{{ $v('nome', 'Minha casa') }}" maxlength="60" required>@error('nome')<span class="erro">{{ $message }}</span>@enderror</div>
    <div class="field"><label for="iBanco">Banco</label><input class="in" id="iBanco" name="banco" value="{{ $v('banco', 'Caixa') }}" maxlength="40" required></div>
    <div class="field"><label for="iSistema">Sistema de amortização</label>
        <select class="in" id="iSistema" name="sistema">
            <option value="sac" @selected($v('sistema', 'sac') === 'sac')>SAC (parcela cai com o tempo)</option>
            <option value="price" @selected($v('sistema') === 'price')>Price / Tabela (parcela fixa)</option>
        </select><span class="hint">A Caixa usa SAC na maioria dos contratos.</span></div>
    <x-campo nome="valor_imovel" rotulo="Valor do imóvel (avaliação)" pre="R$" :valor="$m('valor_imovel')" />
    <x-campo nome="valor_financiado" rotulo="Valor financiado pelo banco" pre="R$" :valor="$m('valor_financiado')" required />
    <x-campo nome="recursos_proprios" rotulo="Recursos próprios (entrada, FGTS)" pre="R$" :valor="$m('recursos_proprios')" />
    <x-campo nome="taxa_juros_anual" rotulo="Taxa de juros efetiva" suf="% a.a." :valor="old('taxa_juros_anual', $f ? valor_input($f->taxa_juros_anual, 4) : '')" required dica="Está no contrato como taxa efetiva anual." />
    <x-campo nome="prazo_meses" rotulo="Prazo" suf="meses" :valor="$v('prazo_meses', 420)" required />
    <div class="field"><label for="iPrimeira">Data da 1ª parcela</label><input class="in" type="date" id="iPrimeira" name="primeira_parcela" value="{{ old('primeira_parcela', $f?->primeira_parcela?->format('Y-m-d')) }}" required>@error('primeira_parcela')<span class="erro">{{ $message }}</span>@enderror</div>
    <div class="field"><label for="iContrato">Data do contrato</label><input class="in" type="date" id="iContrato" name="data_contrato" value="{{ old('data_contrato', $f?->data_contrato?->format('Y-m-d')) }}"></div>
    <x-campo nome="seguro_mensal" rotulo="Seguros (MIP + DFI) por mês" pre="R$" :valor="$m('seguro_mensal', '0,00')" />
    <x-campo nome="taxa_administracao" rotulo="Taxa de administração por mês" pre="R$" :valor="$m('taxa_administracao', '25,00')" />
    <div class="field full"><label for="iObs">Observações</label><textarea class="in" id="iObs" name="observacoes" rows="3" maxlength="2000" placeholder="Número do contrato, agência, gerente, construtora…">{{ old('observacoes', $f?->observacoes) }}</textarea></div>
</div>
