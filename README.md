# FantInvest

Painel financeiro pessoal: carteira de investimentos com metas por classe, regra de aporte por nota, orçamento mensal com a regra 50/30/20, simulador de juros compostos com inflação e simulador de financiamento (Price × SAC).

Feito com a mesma base do Juari Eventos: **Laravel 12**, **Breeze** (login), **Blade + Alpine.js**, **Tailwind CSS 4**, **Vite**, **SQLite** e **PHPUnit**. Os gráficos usam **Chart.js**.

## Módulos

| Módulo | O que faz |
|---|---|
| Visão geral | Patrimônio, resultado da carteira, sobra e taxa de poupança do mês, alocação atual × meta, receitas × gastos dos últimos 6 meses e reserva de emergência. |
| Carteira | Ativos com quantidade, preço médio, cotação e nota (0 a 10). A nota pode vir de um checklist de 10 critérios por classe. Metas de alocação por classe. |
| Regra de aporte | Diz quantas cotas comprar de cada ativo: primeiro as classes mais abaixo da meta, depois os ativos com maior nota e mais abaixo do peso. Registra a compra (quantidade e preço médio) e lança o aporte no orçamento. |
| Orçamento | Receitas e gastos por mês e categoria, regra 50/30/20 e gráfico por categoria. |
| Juros compostos | Valor final bruto e em poder de compra de hoje (descontando inflação e IR), reajuste anual do aporte, renda passiva e "quanto aportar para chegar lá". |
| Financiamento | Price × SAC, total de juros, CET aproximado, veredito (bom / atenção / ruim) e comparação "financiar × investir e comprar à vista". |

As cotações são digitadas por você: o sistema não consulta a bolsa.

## Como rodar na sua máquina

Pré-requisitos: PHP 8.2+ (com `pdo_sqlite`), Composer e Node.js 20+.

```bash
git clone https://github.com/Matheus-Fantin/fantinvest.git
cd fantinvest
composer run setup        # instala dependências, cria .env, gera a chave, migra o banco e compila o front
php artisan db:seed       # opcional: cria a conta de demonstração com dados de exemplo
composer run dev          # sobe o servidor, a fila, os logs e o Vite juntos
```

Abra http://localhost:8000.

Conta de demonstração (depois do `db:seed`): **demo@fantinvest.test** / senha **password**. Para usar com seus dados, crie sua conta em "Criar conta".

## Estrutura

```
app/
  Http/Controllers/     Dashboard, Asset (carteira), AllocationTarget (metas), Aporte, Transaction (orçamento), Reserva
  Http/Requests/        Validação; Concerns/NormalizaNumeros aceita "1.234,56"
  Models/               Asset, AllocationTarget, Transaction, User
  Policies/             Cada usuário só mexe nos próprios ativos e lançamentos
  Services/             CarteiraService (resumo, plano de aporte, registrar aporte), OrcamentoService
  helpers.php           brl(), pct(), numero(), ler_numero(), mes_extenso()
config/financas.php     Classes de ativo, categorias do orçamento, regra 50/30/20 e critérios da nota
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
