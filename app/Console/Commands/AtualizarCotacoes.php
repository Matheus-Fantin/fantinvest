<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\Cotacoes\CotacaoService;
use Illuminate\Console\Command;

class AtualizarCotacoes extends Command
{
    protected $signature = 'cotacoes:atualizar
                            {--classe=* : Atualiza só estas classes (acoes, fii, exterior, cripto)}
                            {--forcar : Ignora o cache e consulta as APIs de novo}';

    protected $description = 'Atualiza a cotação dos ativos de todos os usuários pelas APIs (brapi e CoinGecko)';

    public function handle(CotacaoService $cotacoes): int
    {
        $ativos = Asset::query()
            ->when($this->option('classe'), fn ($q, $classes) => $q->whereIn('classe', $classes))
            ->get();

        $resultado = $cotacoes->atualizar($ativos, (bool) $this->option('forcar'));

        $this->info("Cotações atualizadas: {$resultado['atualizados']}.");
        if ($resultado['falhas']) {
            $this->warn('Sem cotação para: '.implode(', ', $resultado['falhas']));
        }

        return self::SUCCESS;
    }
}
