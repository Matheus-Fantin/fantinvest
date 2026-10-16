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

    /**
     * Pesquisa ativos da B3 pelo código ou nome (endpoint /quote/list).
     *
     * @return array<int, array{ticker: string, nome: string, preco: ?float, variacao: ?float, tipo: ?string, setor: ?string, logo: ?string}>
     */
    public function pesquisar(string $termo, int $limite = 20): array
    {
        $resposta = $this->get('/quote/list', ['search' => $termo, 'limit' => $limite, 'sortBy' => 'volume', 'sortOrder' => 'desc']);
        if (! $resposta) {
            return [];
        }

        return collect((array) $resposta->json('stocks', []))
            ->map(fn ($a) => [
                'ticker' => strtoupper((string) ($a['stock'] ?? '')),
                'nome' => (string) ($a['name'] ?? ''),
                'preco' => is_numeric($a['close'] ?? null) ? (float) $a['close'] : null,
                'variacao' => is_numeric($a['change'] ?? null) ? (float) $a['change'] : null,
                'tipo' => $a['type'] ?? null,
                'setor' => $a['sector'] ?? null,
                'logo' => $a['logo'] ?? null,
            ])
            ->filter(fn ($a) => $a['ticker'] !== '')
            ->values()
            ->all();
    }

    /**
     * Cotação detalhada com histórico diário (o plano gratuito libera até 3 meses).
     *
     * @return array{ticker: string, nome: string, preco: float, variacao: ?float, abertura: ?float, maxima: ?float, minima: ?float, volume: ?float, logo: ?string, historico: array<int, array{data: string, fechamento: float}>}|null
     */
    public function detalhe(string $ticker, string $periodo = '3mo'): ?array
    {
        $resposta = $this->get('/quote/'.rawurlencode($ticker), ['range' => $periodo, 'interval' => '1d']);
        $item = $resposta?->json('results.0');
        if (! is_array($item) || ! is_numeric($item['regularMarketPrice'] ?? null)) {
            return null;
        }

        $historico = collect((array) ($item['historicalDataPrice'] ?? []))
            ->filter(fn ($h) => is_numeric($h['close'] ?? null) && isset($h['date']))
            ->map(fn ($h) => ['data' => date('Y-m-d', (int) $h['date']), 'fechamento' => (float) $h['close']])
            ->sortBy('data')->values()->all();

        $num = fn ($k) => is_numeric($item[$k] ?? null) ? (float) $item[$k] : null;

        return [
            'ticker' => strtoupper((string) ($item['symbol'] ?? $ticker)),
            'nome' => (string) ($item['longName'] ?? $item['shortName'] ?? $ticker),
            'preco' => (float) $item['regularMarketPrice'],
            'variacao' => $num('regularMarketChangePercent'),
            'abertura' => $num('regularMarketOpen'),
            'maxima' => $num('regularMarketDayHigh'),
            'minima' => $num('regularMarketDayLow'),
            'volume' => $num('regularMarketVolume'),
            'logo' => $item['logourl'] ?? null,
            'historico' => $historico,
        ];
    }

    private function get(string $caminho, array $query = []): ?Response
    {
        $config = config('cotacoes.brapi');
        try {
            $requisicao = Http::acceptJson()->timeout(config('cotacoes.timeout_segundos'));
            if ($config['token']) {
                $requisicao = $requisicao->withToken($config['token']);
            }
            $resposta = $requisicao->get(rtrim($config['url'], '/').$caminho, $query);
        } catch (\Throwable $e) {
            Log::warning('brapi: falha de conexão', ['erro' => $e->getMessage()]);

            return null;
        }

        if ($resposta->failed()) {
            Log::warning('brapi: resposta com erro', ['caminho' => $caminho, 'status' => $resposta->status()]);

            return null;
        }

        return $resposta;
    }
}
