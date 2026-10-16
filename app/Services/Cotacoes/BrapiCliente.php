<?php

namespace App\Services\Cotacoes;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Cotações da B3 pela brapi.dev (https://brapi.dev/docs). */
class BrapiCliente
{
    /**
     * @param  array<int, string>  $tickers
     * @return array<string, Cotacao> indexado pelo ticker
     */
    public function buscar(array $tickers): array
    {
        $config = config('cotacoes.brapi');
        $lotes = array_chunk($tickers, max(1, $config['tickers_por_requisicao']));

        $respostas = Http::pool(fn (Pool $pool) => array_map(
            function (array $lote) use ($pool, $config) {
                $requisicao = $pool->acceptJson()->timeout(config('cotacoes.timeout_segundos'));
                if ($config['token']) {
                    $requisicao = $requisicao->withToken($config['token']);
                }

                return $requisicao->get(rtrim($config['url'], '/').'/quote/'.implode(',', array_map('rawurlencode', $lote)));
            },
            $lotes,
        ));

        $cotacoes = [];
        foreach ($respostas as $resposta) {
            if (! $resposta instanceof Response || $resposta->failed()) {
                Log::warning('brapi: falha ao buscar cotação', [
                    'status' => $resposta instanceof Response ? $resposta->status() : null,
                    'mensagem' => $resposta instanceof Response ? $resposta->json('message') : (string) $resposta,
                ]);

                continue;
            }

            foreach ((array) $resposta->json('results', []) as $item) {
                $ticker = strtoupper((string) ($item['symbol'] ?? ''));
                $preco = $item['regularMarketPrice'] ?? null;
                if ($ticker === '' || ! is_numeric($preco) || $preco <= 0) {
                    continue;
                }
                $variacao = $item['regularMarketChangePercent'] ?? null;
                $cotacoes[$ticker] = new Cotacao($ticker, (float) $preco, is_numeric($variacao) ? (float) $variacao : null);
            }
        }

        return $cotacoes;
    }
}
