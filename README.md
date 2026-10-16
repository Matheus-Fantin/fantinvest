# FantInvest

Painel financeiro pessoal: carteira de investimentos com cotações automáticas, metas por classe, regra de aporte por nota, orçamento mensal com a regra 50/30/20 e importação de extrato, objetivos (viagem, carro…), simulador de juros compostos com inflação e simulador de financiamento (Price × SAC).

Feito com a mesma base do Juari Eventos: **Laravel 12**, **Breeze** (login), **Blade + Alpine.js**, **Tailwind CSS 4**, **Vite**, **SQLite** e **PHPUnit**. Os gráficos usam **Chart.js**.

## Módulos

| Módulo | O que faz |
|---|---|
| Visão geral | Patrimônio, resultado da carteira, sobra e taxa de poupança do mês, contas e faturas, compromissos futuros, alocação atual × meta, receitas × gastos dos últimos 6 meses e reserva de emergência. |
| Patrimônio | Tudo o que você tem (contas, investimentos, imóvel) menos o que deve (faturas, parcelas, financiamento), renda passiva e evolução mês a mês. |
| Contas | Conta corrente, poupança, corretora e cripto com saldo calculado, transferências entre contas. |
| Cartões | Limite, dia de fechamento e vencimento, faturas (aberta, fechada, atrasada, paga, futura) e pagamento de fatura que não conta o gasto duas vezes. |
| Carteira | Ativos com quantidade, preço médio, cotação e nota (0 a 10). Cotação e variação do dia atualizadas pela internet (B3 e cripto). A nota pode vir de um checklist de 10 critérios por classe. Metas de alocação por classe. |
| Bolsa | Pesquisa de ações, FIIs, ETFs e BDRs (brapi), página da ação com gráfico de 3 meses, compras e vendas com preço médio e lucro realizado. |
| Dividendos | Proventos por ativo e por mês, yield on cost; cada provento vira receita no orçamento. |
| Meu financiamento | Contrato da Caixa (SAC ou Price): cronograma, parcelas pagas, saldo devedor, juros, amortizações extras, liberações do banco e gastos da obra. |
| Regra de aporte | Diz quantas cotas comprar de cada ativo: primeiro as classes mais abaixo da meta, depois os ativos com maior nota e mais abaixo do peso. Registra a compra (quantidade e preço médio) e lança o aporte no orçamento. |
| Orçamento | Receitas e gastos por mês, em conta ou cartão, compras parceladas (3/10), gráfico do que já está comprometido nos próximos meses, regra 50/30/20 e importação de extrato OFX ou CSV. |
| Objetivos | Metas como viagem, carro ou casa: quanto falta, quanto guardar por mês (contando o rendimento) e depósitos que podem ir direto para o orçamento. |
| Juros compostos | Valor final bruto e em poder de compra de hoje (descontando inflação e IR), reajuste anual do aporte, renda passiva e "quanto aportar para chegar lá". |
| Financiamento | Price × SAC, total de juros, CET aproximado, veredito (bom / atenção / ruim) e comparação "financiar × investir e comprar à vista". |

## Cotações automáticas

| Classe | Fonte | Observação |
|---|---|---|
| Ações BR, FIIs, ETFs e BDRs | [brapi.dev](https://brapi.dev) | Precisa de token gratuito. No plano gratuito: 1 ticker por requisição, 15 mil requisições/mês e cerca de 30 min de atraso. |
| Cripto | [CoinGecko](https://www.coingecko.com/en/api) | Funciona sem chave. Use o símbolo (BTC, ETH, SOL…); a lista fica em `config/cotacoes.php`. |
| Renda fixa | — | Continua manual: informe o saldo atual como cotação. |

Como funciona:

1. Crie um token em https://brapi.dev/dashboard e coloque no `.env`: `BRAPI_TOKEN=seu_token`.
2. O agendador (`php artisan schedule:work`, já incluído no `composer run dev`) atualiza a B3 a cada 15 min durante o pregão e cripto a cada 15 min o dia todo.
3. Ao abrir Visão geral, Carteira ou Regra de aporte, a página pede atualização se a cotação tiver mais de 15 min, e o botão "Atualizar agora" força uma consulta.
4. Para atualizar pelo terminal: `php artisan cotacoes:atualizar` (ou `--classe=cripto`, `--forcar`).

Cada cotação fica 10 min em cache para economizar a cota da API. Para um ativo específico ficar manual, desmarque "Atualizar a cotação automaticamente" ao editá-lo.

Em produção, adicione ao cron do servidor: `* * * * * cd /caminho/do/fantinvest && php artisan schedule:run >> /dev/null 2>&1`.

## Como rodar na sua máquina

Pré-requisitos: PHP 8.2+ (com `pdo_sqlite`), Composer e Node.js 20+.

```bash
git clone https://github.com/Matheus-Fantin/fantinvest.git
cd fantinvest
composer run setup        # instala dependências, cria .env, gera a chave, migra o banco e compila o front
php artisan db:seed       # opcional: cria a conta de demonstração com dados de exemplo
composer run dev          # sobe o servidor, a fila, os logs, o Vite e o agendador de cotações
```

Abra http://localhost:8000.

Conta de demonstração (depois do `db:seed`): **demo@fantinvest.test** / senha **password**.

## Cadastro por convite

A primeira conta criada vira administradora. Depois disso, novas contas só entram por convite: em **Administração → Convites** você gera um link (opcionalmente preso a um e-mail) que vale uma vez. Para liberar o cadastro para qualquer pessoa, use `CADASTRO_ABERTO=true` no `.env`. Para dar acesso de administrador a uma conta existente:

```bash
php artisan usuario:admin seu@email.com
```

## Estrutura

```
app/
  Console/Commands/     cotacoes:atualizar
  Http/Controllers/     Dashboard, Asset (carteira), AllocationTarget (metas), Aporte, Cotacao, Goal (objetivos),
                        Importacao (CSV), Transaction (orçamento), Reserva
  Http/Requests/        Validação; Concerns/NormalizaNumeros aceita "1.234,56"
  Models/               Asset, AllocationTarget, Goal, Transaction, User
  Policies/             Cada usuário só mexe nos próprios ativos e lançamentos
  Services/             CarteiraService (resumo, plano de aporte, registrar aporte), OrcamentoService,
                        ExtratoCsvService (leitura de extrato), Cotacoes/ (brapi, CoinGecko e cache)
  helpers.php           brl(), pct(), numero(), ler_numero(), mes_extenso()
config/financas.php     Classes de ativo, categorias do orçamento, regra 50/30/20, critérios da nota e palavras da importação
config/cotacoes.php     Fontes de cotação, tokens, cache e moedas cripto reconhecidas
resources/
  css/app.css           Tokens de cor (claro/escuro) e componentes visuais
  js/graficos.js        Gráficos das páginas (Chart.js)
  js/simuladores/       Cálculos e componentes Alpine dos simuladores
  views/                Blade: dashboard, carteira, aporte, orcamento, simuladores
lang/pt_BR*             Tradução das telas de login e das mensagens de validação
```

Para mudar categorias, classes ou critérios da nota, edite `config/financas.php`.

## Testes

```bash
php artisan test
```

## Tema

O app segue o tema claro/escuro do sistema. O botão "Tema" na barra lateral alterna entre sistema, claro e escuro.

## Deploy no Laravel Cloud

1. **New application** → importe o repositório `Matheus-Fantin/fantinvest` (região: São Paulo, se houver; senão US East).
2. No canvas do ambiente, **adicione um banco** Laravel Serverless Postgres (ou Laravel MySQL) e ligue ao ambiente. O SQLite não serve em produção: o disco é apagado a cada deploy. As variáveis `DB_*` são preenchidas sozinhas.
3. Clique no **App cluster** e ligue o **Scheduler** (atualiza as cotações a cada 15 min).
4. **Build commands:**
   ```
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   php artisan optimize
   ```
   **Deploy command:** `php artisan migrate --force`
5. **Variáveis de ambiente:**
   ```
   APP_NAME=FantInvest
   APP_LOCALE=pt_BR
   APP_TIMEZONE=America/Sao_Paulo
   SESSION_LIFETIME=720
   QUEUE_CONNECTION=sync
   CADASTRO_ABERTO=false
   BRAPI_TOKEN=seu_token
   BRAPI_TICKERS_POR_REQUISICAO=1
   COINGECKO_API_KEY=
   ```
6. **Deploy.** Logo depois, abra `/register` e crie a sua conta: a primeira conta vira administradora e as próximas só entram por convite.

Não rode `php artisan db:seed` em produção: ele cria a conta de demonstração com a senha `password`.
