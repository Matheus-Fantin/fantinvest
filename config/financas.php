<?php

return [

    /*
    | Classes de ativo. A ordem define a cor de cada classe nos gráficos.
    */
    'classes' => [
        'acoes' => ['nome' => 'Ações BR', 'cor' => 's1', 'fracionavel' => false],
        'fii' => ['nome' => 'FIIs', 'cor' => 's2', 'fracionavel' => false],
        'exterior' => ['nome' => 'Exterior', 'cor' => 's3', 'fracionavel' => false],
        'rf' => ['nome' => 'Renda fixa', 'cor' => 's4', 'fracionavel' => true],
        'cripto' => ['nome' => 'Cripto', 'cor' => 's5', 'fracionavel' => true],
    ],

    'metas_padrao' => ['acoes' => 35, 'fii' => 20, 'exterior' => 20, 'rf' => 25, 'cripto' => 0],

    /*
    | Categorias do orçamento. Cada despesa pertence a um grupo da regra 50/30/20.
    */
    'categorias_receita' => ['Salário', 'Renda extra', 'Dividendos', 'Outras receitas'],

    'categorias_despesa' => [
        'Moradia' => 'ess',
        'Contas da casa' => 'ess',
        'Mercado' => 'ess',
        'Transporte' => 'ess',
        'Saúde' => 'ess',
        'Educação' => 'ess',
        'Lazer' => 'des',
        'Restaurantes' => 'des',
        'Compras' => 'des',
        'Assinaturas' => 'des',
        'Viagem' => 'des',
        'Outros gastos' => 'des',
        'Investimentos' => 'inv',
        'Reserva de emergência' => 'inv',
    ],

    'grupos' => [
        'ess' => ['nome' => 'Essenciais', 'alvo' => 50],
        'des' => ['nome' => 'Estilo de vida', 'alvo' => 30],
        'inv' => ['nome' => 'Investimentos', 'alvo' => 20],
    ],

    /*
    | Checklist de critérios que gera a nota (0 a 10) de cada ativo.
    */
    'criterios' => [
        'acoes' => [
            'ROE acima de 10% nos últimos anos',
            'Dívida líquida menor que 2× o EBITDA',
            'Lucro crescente nos últimos 5 anos',
            'Teve lucro em todos os últimos 10 anos',
            'Paga dividendos com regularidade',
            'Mais de 10 anos de bolsa',
            'Setor perene (energia, saneamento, bancos, seguros…)',
            'Não depende do governo nem é estatal',
            'Boa governança (Novo Mercado ou tag along 100%)',
            'Líder do setor ou com vantagem competitiva clara',
        ],
        'fii' => [
            'Vacância abaixo de 10%',
            'P/VP entre 0,8 e 1,1',
            'Mais de 5 imóveis',
            'Inquilinos diversificados',
            'Contratos longos ou atípicos',
            'Imóveis em regiões de alta demanda',
            'Gestora com bom histórico',
            'Liquidez diária acima de R$ 500 mil',
            'Dividendos estáveis nos últimos 2 anos',
            'Patrimônio acima de R$ 500 milhões',
        ],
        'exterior' => [
            'ROE acima de 15%',
            'Dívida controlada (Net Debt / EBITDA < 2)',
            'Receita crescente nos últimos 5 anos',
            'Lucro em todos os últimos 10 anos',
            'Margem líquida estável ou crescente',
            'Recompra de ações ou dividendos crescentes',
            'Mais de 20 anos de mercado',
            'Líder global no que faz',
            'Receita diversificada por região',
            'Setor com demanda duradoura',
        ],
    ],
];
