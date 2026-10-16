<?php

namespace App\Services\Cotacoes;

use App\Models\Asset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Atualiza a cotação dos ativos da carteira consultando a fonte de cada classe
 * (brapi para B3, CoinGecko para cripto). Renda fixa e ativos marcados como
 * "manual" não são tocados. Cada cotação buscada fica em cache por alguns
 * minutos e é reaproveitada por todos os usuários que têm o mesmo ativo.
 */
class CotacaoService
{
    public function __construct(
        private BrapiCliente $brapi,
        private CoinGeckoCliente $coingecko,
    ) {}

    /** Ativos que esta integração sabe atualizar. */
    public function atualizaveis(Collection $ativos): Collection
    {
        $fontes = config('cotacoes.fontes');

        return $ativos->filter(fn (Asset $a) => $a->atualizacao_automatica && isset($fontes[$a->classe]));
    }

    /**
     * @param  Collection<int, Asset>  $ativos
     * @return array{atualizados: int, falhas: array<int, string>}
     */
    public function atualizar(Collection $ativos, bool $ignorarCache = false): array
    {
        $ativos = $this->atualizaveis($ativos);
        if ($ativos->isEmpty()) {
            return ['atualizados' => 0, 'falhas' => []];
        }

        $fontes = config('cotacoes.fontes');
        $porFonte = $ativos->groupBy(fn (Asset $a) => $fontes[$a->classe])
            ->map(fn (Collection $grupo) => $grupo->pluck('ticker')->map(fn ($t) => strtoupper(trim($t)))->unique()->values()->all());

        $cotacoes = [];
        foreach ($porFonte as $fonte => $tickers) {
            $cotacoes += $this->buscarComCache($fonte, $tickers, $ignorarCache);
        }

        $atualizados = 0;
        $falhas = [];
        foreach ($ativos as $ativo) {
            $cotacao = $cotacoes[strtoupper(trim($ativo->ticker))] ?? null;
            if (! $cotacao) {
                $falhas[] = $ativo->ticker;

                continue;
            }
            $ativo->forceFill([
                'cotacao' => $cotacao->preco,
                'variacao_dia' => $cotacao->variacaoDia,
                'cotacao_atualizada_em' => now(),
            ])->save();
            $atualizados++;
        }

        return ['atualizados' => $atualizados, 'falhas' => array_values(array_unique($falhas))];
    }

    /** @return array<string, Cotacao> */
    private function buscarComCache(string $fonte, array $tickers, bool $ignorarCache): array
    {
        $chave = fn (string $t) => "cotacao:$fonte:$t";
        $encontradas = [];
        $faltando = [];

        foreach ($tickers as $ticker) {
            $salva = $ignorarCache ? null : Cache::get($chave($ticker));
            if (is_array($salva)) {
                $encontradas[$ticker] = new Cotacao($ticker, $salva['preco'], $salva['variacao']);
            } else {
                $faltando[] = $ticker;
            }
        }

        if ($faltando !== []) {
            $novas = $fonte === 'coingecko' ? $this->coingecko->buscar($faltando) : $this->brapi->buscar($faltando);
            foreach ($novas as $ticker => $cotacao) {
                Cache::put($chave($ticker), ['preco' => $cotacao->preco, 'variacao' => $cotacao->variacaoDia], now()->addMinutes(config('cotacoes.cache_minutos')));
            }
            $encontradas += $novas;
        }

        return $encontradas;
    }
}
