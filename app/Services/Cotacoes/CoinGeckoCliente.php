<?php

namespace App\Services\Cotacoes;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Cotações de criptomoedas em reais pela CoinGecko (https://docs.coingecko.com). */
class CoinGeckoCliente
{
    /**
     * @param  array<int, string>  $simbolos  ex.: ['BTC', 'ETH']
     * @return array<string, Cotacao> indexado pelo símbolo
     */
    public function buscar(array $simbolos): array
    {
        $config = config('cotacoes.coingecko');
        $ids = [];
        foreach ($simbolos as $simbolo) {
            if (isset($config['moedas'][$simbolo])) {
                $ids[$config['moedas'][$simbolo]] = $simbolo;
            }
        }
        if ($ids === []) {
            return [];
        }

        $requisicao = Http::acceptJson()->timeout(config('cotacoes.timeout_segundos'));
        if ($config['chave']) {
            $requisicao = $requisicao->withHeaders(['x-cg-demo-api-key' => $config['chave']]);
        }

        try {
            $resposta = $requisicao->get(rtrim($config['url'], '/').'/simple/price', [
                'ids' => implode(',', array_keys($ids)),
                'vs_currencies' => 'brl',
                'include_24hr_change' => 'true',
            ]);
        } catch (\Throwable $e) {
            Log::warning('CoinGecko: falha de conexão', ['erro' => $e->getMessage()]);

            return [];
        }

        if ($resposta->failed()) {
            Log::warning('CoinGecko: falha ao buscar cotação', ['status' => $resposta->status()]);

            return [];
        }

        $cotacoes = [];
        foreach ($ids as $id => $simbolo) {
            $preco = $resposta->json("$id.brl");
            if (is_numeric($preco) && $preco > 0) {
                $variacao = $resposta->json("$id.brl_24h_change");
                $cotacoes[$simbolo] = new Cotacao($simbolo, (float) $preco, is_numeric($variacao) ? (float) $variacao : null);
            }
        }

        return $cotacoes;
    }
}
