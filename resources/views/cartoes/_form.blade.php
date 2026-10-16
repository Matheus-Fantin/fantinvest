{{-- Campos do cadastro de cartão (usado em "novo" e "editar"). Espera o objeto Alpine "cartao". --}}
<div class="form-grid">
    <div class="field full"><label for="kNome">Nome</label><input class="in" id="kNome" name="nome" x-model="cartao.nome" placeholder="Ex.: Nubank, Inter Gold" maxlength="40" required></div>
    <x-campo nome="limite" rotulo="Limite" id="kLimite" pre="R$" x-model="cartao.limite" />
    <div class="field"><label for="kConta">Pago pela conta</label>
        <select class="in" id="kConta" name="account_id" x-model="cartao.account_id">
            <option value="">Escolher na hora</option>
            @foreach ($contas as $c)<option value="{{ $c->id }}">{{ $c->nome }}</option>@endforeach
        </select></div>
    <div class="field"><label for="kFech">Dia de fechamento</label><input class="in" id="kFech" name="dia_fechamento" type="number" min="1" max="31" x-model="cartao.dia_fechamento" required>
        <span class="hint">Compras a partir desse dia vão para a fatura seguinte.</span></div>
    <div class="field"><label for="kVenc">Dia de vencimento</label><input class="in" id="kVenc" name="dia_vencimento" type="number" min="1" max="31" x-model="cartao.dia_vencimento" required></div>
</div>
