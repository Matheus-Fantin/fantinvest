<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Lê arquivos OFX (padrão bancário exportado por quase todos os bancos).
 * Funciona com o formato antigo (SGML, sem tags de fechamento) e com o XML.
 */
class OfxService
{
    public static function pareceOfx(string $conteudo): bool
    {
        return (bool) preg_match('/<OFX>|OFXHEADER/i', substr($conteudo, 0, 4000));
    }

    /**
     * @return array{cartao: bool, transacoes: array<int, array{data: string, valor: float, descricao: string, id: ?string}>}
     */
    public function ler(string $conteudo): array
    {
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
        }

        preg_match_all('/<STMTTRN>(.*?)(?:<\/STMTTRN>|(?=<STMTTRN>)|(?=<\/BANKTRANLIST>))/is', $conteudo, $blocos);

        $transacoes = [];
        foreach ($blocos[1] as $bloco) {
            $campo = function (string $tag) use ($bloco): ?string {
                return preg_match('/<'.$tag.'>([^<\r\n]*)/i', $bloco, $m) ? trim(html_entity_decode($m[1])) : null;
            };

            $data = $campo('DTPOSTED');
            $valor = $campo('TRNAMT');
            if (! $data || $valor === null || ! preg_match('/^(\d{4})(\d{2})(\d{2})/', $data, $d)) {
                continue;
            }
            $valor = (float) str_replace(',', '.', $valor);
            if ($valor == 0.0) {
                continue;
            }

            $memo = $campo('MEMO');
            $nome = $campo('NAME');
            $descricao = trim($memo && $nome && ! str_contains($memo, $nome) ? "$nome $memo" : ($memo ?: $nome ?: ''));

            $transacoes[] = [
                'data' => "{$d[1]}-{$d[2]}-{$d[3]}",
                'valor' => $valor,
                'descricao' => mb_substr($descricao, 0, 80),
                'id' => $campo('FITID'),
            ];
        }

        if ($transacoes === []) {
            throw new InvalidArgumentException('Não encontrei lançamentos neste arquivo OFX.');
        }

        return ['cartao' => (bool) preg_match('/<CREDITCARDMSGSRSV1>|<CCSTMTRS>/i', $conteudo), 'transacoes' => $transacoes];
    }
}
