<?php

return [

    /*
    | brapi.dev — ações, FIIs, ETFs e BDRs da B3.
    | Crie um token gratuito em https://brapi.dev/dashboard e coloque em BRAPI_TOKEN no .env.
    | Plano gratuito: 1 ticker por requisição e ~30 min de atraso. Em planos pagos,
    | aumente BRAPI_TICKERS_POR_REQUISICAO (10 no Startup, 20 no Pro) para gastar menos cota.
    */
    'brapi' => [
        'url' => env('BRAPI_URL', 'https://brapi.dev/api'),
        'token' => env('BRAPI_TOKEN'),
        'tickers_por_requisicao' => (int) env('BRAPI_TICKERS_POR_REQUISICAO', 1),
    ],

    /*
    | CoinGecko — criptomoedas em reais. Funciona sem chave; com uma chave
    | "Demo" gratuita (COINGECKO_API_KEY) o limite de requisições é maior.
    */
    'coingecko' => [
        'url' => env('COINGECKO_URL', 'https://api.coingecko.com/api/v3'),
        'chave' => env('COINGECKO_API_KEY'),
        // Símbolo digitado na carteira → id da moeda na CoinGecko.
        'moedas' => [
            'BTC' => 'bitcoin',
            'ETH' => 'ethereum',
            'SOL' => 'solana',
            'BNB' => 'binancecoin',
            'XRP' => 'ripple',
            'ADA' => 'cardano',
            'DOGE' => 'dogecoin',
            'USDT' => 'tether',
            'USDC' => 'usd-coin',
            'LINK' => 'chainlink',
            'DOT' => 'polkadot',
            'AVAX' => 'avalanche-2',
            'LTC' => 'litecoin',
            'TRX' => 'tron',
            'TON' => 'the-open-network',
        ],
    ],

    // Classes atualizadas por cada fonte. Renda fixa continua manual.
    'fontes' => [
        'acoes' => 'brapi',
        'fii' => 'brapi',
        'exterior' => 'brapi',
        'cripto' => 'coingecko',
    ],

    // Por quanto tempo uma cotação buscada é reaproveitada (evita gastar cota à toa).
    'cache_minutos' => (int) env('COTACOES_CACHE_MINUTOS', 10),

    // A página pede atualização sozinha quando a cotação mais antiga passa desse tempo.
    'idade_maxima_minutos' => (int) env('COTACOES_IDADE_MAXIMA_MINUTOS', 15),

    'timeout_segundos' => 10,
];
