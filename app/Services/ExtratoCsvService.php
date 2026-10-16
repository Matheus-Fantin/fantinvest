<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Lê extratos e faturas exportados pelos bancos em CSV (Nubank, Inter, Itaú, C6…) ou OFX.
 * O CSV precisa de uma linha de cabeçalho com colunas de data, valor e descrição;
 * os nomes mais comuns em português e inglês são reconhecidos.
 */
class ExtratoCsvService
{
    public const MAXIMO_LINHAS = 1000;

    private const COLUNAS = [
        'data' => ['data', 'date', 'dt', 'data lancamento', 'data do lancamento', 'data da transacao', 'data movimento'],
        'valor' => ['valor', 'amount', 'valor (r$)', 'valor r$', 'quantia', 'montante'],
        'descricao' => ['descricao', 'title', 'titulo', 'historico', 'description', 'lancamento', 'estabelecimento', 'detalhes', 'memo'],
    ];

    /**
     * Lê um CSV ou OFX e devolve as linhas para revisão.
     *
     * @param  bool  $fatura  true para fatura de cartão: os gastos vêm marcados e pagamentos/estornos desmarcados
     * @return array<int, array{data: string, descricao: string, valor: float, tipo: string, categoria: string, incluir: bool, aviso: ?string, external_id: ?string}>
     */
    public function ler(string $conteudo, bool $fatura, User $user): array
    {
        $existentes = $user->transactions()
            ->get(['data', 'valor', 'descricao', 'external_id'])
            ->flatMap(fn ($t) => array_filter([
                $t->data->format('Y-m-d').'|'.number_format($t->valor, 2, '.', '').'|'.mb_strtolower((string) $t->descricao),
                $t->external_id ? 'id:'.$t->external_id : null,
            ]))
            ->flip();

        if (OfxService::pareceOfx($conteudo)) {
            $ofx = app(OfxService::class)->ler($conteudo);
            $linhas = [];
            foreach (array_slice($ofx['transacoes'], 0, self::MAXIMO_LINHAS) as $t) {
                // No OFX, débito é sempre negativo (inclusive compras no cartão).
                $linhas[] = $this->montarLinha($t['data'], $t['valor'], $t['descricao'], $fatura || $ofx['cartao'], false, $existentes, $t['id']);
            }

            return $linhas;
        }

        return $this->lerCsv($conteudo, $fatura, $existentes);
    }

    private function lerCsv(string $conteudo, bool $fatura, $existentes): array
    {
        $conteudo = $this->paraUtf8($conteudo);
        $linhas = preg_split('/\r\n|\r|\n/', trim($conteudo));
        if (! $linhas || count($linhas) < 2) {
            throw new InvalidArgumentException('O arquivo está vazio ou só tem o cabeçalho.');
        }

        $separador = substr_count($linhas[0], ';') > substr_count($linhas[0], ',') ? ';' : ',';
        $cabecalho = array_map(fn ($c) => $this->normalizar($c), str_getcsv($linhas[0], $separador, '"', ''));
        $indices = $this->localizarColunas($cabecalho);

        $saida = [];
        foreach (array_slice($linhas, 1, self::MAXIMO_LINHAS) as $linha) {
            if (trim($linha) === '') {
                continue;
            }
            $campos = str_getcsv($linha, $separador, '"', '');
            $data = $this->lerData($campos[$indices['data']] ?? '');
            $bruto = trim((string) ($campos[$indices['valor']] ?? ''));
            if ($data === null || $bruto === '' || ! is_numeric(ler_numero($bruto))) {
                continue;
            }
            $valor = (float) ler_numero($bruto);
            if ($valor == 0.0) {
                continue;
            }
            $descricao = mb_substr(trim((string) ($campos[$indices['descricao']] ?? '')), 0, 80);

            // Na fatura em CSV (ex.: Nubank) o gasto vem positivo; no extrato, negativo.
            $saida[] = $this->montarLinha($data, $valor, $descricao, $fatura, $fatura, $existentes, null);
        }

        if ($saida === []) {
            throw new InvalidArgumentException('Nenhuma linha com data e valor válidos foi encontrada.');
        }

        return $saida;
    }

    /**
     * @param  bool  $gastoPositivo  true quando o arquivo traz gastos com sinal positivo
     */
    private function montarLinha(string $data, float $valor, string $descricao, bool $fatura, bool $gastoPositivo, $existentes, ?string $externalId): array
    {
        $ehGasto = $gastoPositivo ? $valor > 0 : $valor < 0;
        $aviso = null;
        $incluir = true;

        if ($fatura) {
            $tipo = 'despesa';
            if (! $ehGasto) {
                $incluir = false;
                $aviso = 'Pagamento ou estorno';
            }
        } else {
            $tipo = $ehGasto ? 'despesa' : 'receita';
        }
        $valor = round(abs($valor), 2);

        $chave = $data.'|'.number_format($valor, 2, '.', '').'|'.mb_strtolower($descricao);
        if (isset($existentes[$chave]) || ($externalId && isset($existentes['id:'.$externalId]))) {
            $incluir = false;
            $aviso = 'Já lançado';
        }

        return [
            'data' => $data,
            'descricao' => $descricao,
            'valor' => $valor,
            'tipo' => $tipo,
            'categoria' => $this->sugerirCategoria($descricao, $tipo),
            'incluir' => $incluir,
            'aviso' => $aviso,
            'external_id' => $externalId,
        ];
    }

    public function sugerirCategoria(string $descricao, string $tipo): string
    {
        $texto = ' '.$this->normalizar($descricao).' ';
        $permitidas = $tipo === 'receita'
            ? config('financas.categorias_receita')
            : array_keys(config('financas.categorias_despesa'));

        foreach (config('financas.palavras_categoria') as $categoria => $palavras) {
            if (! in_array($categoria, $permitidas, true)) {
                continue;
            }
            foreach ($palavras as $palavra) {
                if (str_contains($texto, $this->normalizar($palavra))) {
                    return $categoria;
                }
            }
        }

        return $tipo === 'receita' ? 'Outras receitas' : 'Outros gastos';
    }

    private function localizarColunas(array $cabecalho): array
    {
        $indices = [];
        foreach (self::COLUNAS as $campo => $nomes) {
            foreach ($cabecalho as $i => $coluna) {
                if (in_array($coluna, $nomes, true)) {
                    $indices[$campo] = $i;
                    break;
                }
            }
        }

        $faltando = array_diff(['data', 'valor'], array_keys($indices));
        if ($faltando) {
            throw new InvalidArgumentException('Não encontrei a(s) coluna(s) '.implode(' e ', $faltando).' no cabeçalho. O arquivo precisa ter colunas como "Data", "Valor" e "Descrição".');
        }
        $indices['descricao'] ??= -1;

        return $indices;
    }

    private function lerData(string $texto): ?string
    {
        $texto = trim($texto);
        foreach (['d/m/Y', 'Y-m-d', 'd/m/y', 'd-m-Y', 'Y/m/d'] as $formato) {
            try {
                $data = Carbon::createFromFormat('!'.$formato, $texto);
            } catch (\Throwable) {
                continue;
            }
            if ($data && $data->format($formato) === $texto) {
                return $data->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizar(string $texto): string
    {
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);

        return trim(mb_strtolower(Str::ascii($texto)));
    }

    private function paraUtf8(string $conteudo): string
    {
        return mb_check_encoding($conteudo, 'UTF-8') ? $conteudo : mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
    }
}
