<x-app-layout titulo="Importar extrato">
    <div class="page-head">
        <div>
            <div class="eyebrow">Orçamento</div>
            <h1>Importar extrato</h1>
            <p>Envie o OFX ou CSV exportado pelo app do seu banco ou cartão. Antes de salvar, você revisa cada linha, ajusta as categorias e desmarca o que não quer lançar.</p>
        </div>
        <a class="btn" href="{{ route('orcamento.index') }}"><x-icone nome="left" />Voltar ao orçamento</a>
    </div>

    <div class="grid g-side">
        <form class="card stack" method="POST" action="{{ route('orcamento.importar.preview') }}" enctype="multipart/form-data"
              x-data="{ nome: '', over: false }">
            @csrf
            <h2>Arquivo</h2>
            <label class="dropzone" :class="{ over }" x-on:dragover.prevent="over = true" x-on:dragleave="over = false" x-on:drop="over = false">
                <x-icone nome="upload" width="26" height="26" />
                <strong x-text="nome || 'Escolha ou arraste o arquivo .ofx ou .csv'"></strong>
                <span class="small faint">Até 2 MB e {{ \App\Services\ExtratoCsvService::MAXIMO_LINHAS }} linhas</span>
                <input type="file" name="arquivo" accept=".ofx,.csv,text/csv,text/plain,application/x-ofx" required class="sr-only" x-on:change="nome = $event.target.files[0]?.name || ''">
            </label>
            @error('arquivo')<span class="erro">{{ $message }}</span>@enderror
            <x-select-origem :contas="$contas" :cartoes="$cartoes" id="impOrigem" rotulo="Lançar em" />
            <span class="hint">Escolhendo um cartão, cada compra vai para a fatura certa e pagamentos ficam de fora.</span>
            <label class="check"><input type="checkbox" name="fatura" value="1">É fatura de cartão em CSV (gastos vêm com valor positivo, como no Nubank)</label>
            <button class="btn primary" type="submit">Ler arquivo</button>
        </form>

        <div class="card stack">
            <h2>Como exportar</h2>
            <div class="stack small">
                <p><strong>OFX (recomendado)</strong>: quase todo banco exporta. No internet banking, procure "Exportar extrato" e escolha OFX, Money ou Quicken. Ele traz um código único por lançamento, então importar o mesmo arquivo duas vezes não duplica nada.</p>
                <p><strong>Nubank</strong>: no app, abra a conta ou a fatura, toque em "Exportar" e escolha CSV. A fatura já vem com as colunas date, title e amount.</p>
                <p><strong>Inter, Itaú, C6 e outros</strong>: no internet banking, exporte o extrato em CSV (às vezes chamado de "Excel CSV").</p>
                <p class="muted">O arquivo precisa de uma linha de cabeçalho com <strong>data</strong>, <strong>valor</strong> e, se possível, <strong>descrição</strong>. Valores negativos viram gastos e positivos viram receitas. Datas como 31/10/2026 ou 2026-10-31 e valores como 1.234,56 ou 1234.56 são aceitos.</p>
                <p class="muted">Linhas iguais a lançamentos que você já tem (mesma data, valor e descrição) aparecem desmarcadas para evitar duplicidade.</p>
            </div>
            <div class="divider"></div>
            <span class="small muted">Exemplo de arquivo aceito:</span>
            <pre class="mono small" style="margin:0;padding:12px;background:var(--surface-2);border-radius:10px;overflow-x:auto">Data;Descrição;Valor
05/10/2026;Salário;8.200,00
06/10/2026;Aluguel;-2.100,00
12/10/2026;Supermercado Assaí;-389,90</pre>
        </div>
    </div>
</x-app-layout>
