<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OfxTest extends TestCase
{
    use RefreshDatabase;

    private const OFX = <<<'OFX'
OFXHEADER:100
DATA:OFXSGML
VERSION:102
<OFX>
<BANKMSGSRSV1><STMTTRNRS><STMTRS>
<BANKTRANLIST>
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20261005120000[-3:BRT]
<TRNAMT>8200.00
<FITID>A1
<MEMO>SALARIO EMPRESA X
</STMTTRN>
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20261008
<TRNAMT>-62.90
<FITID>A2
<MEMO>IFOOD *PEDIDO
</STMTTRN>
</BANKTRANLIST>
</STMTRS></STMTTRNRS></BANKMSGSRSV1>
</OFX>
OFX;

    public function test_importa_ofx_numa_conta_e_nao_duplica(): void
    {
        $user = User::factory()->create();
        $conta = $user->accounts()->create(['nome' => 'Inter', 'tipo' => 'corrente', 'saldo_inicial' => 0]);

        $linhas = $this->actingAs($user)
            ->post('/orcamento/importar', ['arquivo' => UploadedFile::fake()->createWithContent('extrato.ofx', self::OFX), 'origem' => 'conta:'.$conta->id])
            ->assertOk()->viewData('linhas');

        $this->assertSame(['2026-10-05', 'receita', 'Salário', 8200.0, 'A1'], [$linhas[0]['data'], $linhas[0]['tipo'], $linhas[0]['categoria'], $linhas[0]['valor'], $linhas[0]['external_id']]);
        $this->assertSame(['despesa', 'Restaurantes', 62.9], [$linhas[1]['tipo'], $linhas[1]['categoria'], $linhas[1]['valor']]);

        $enviar = array_map(fn ($l) => $l + ['incluir' => '1'], $linhas);
        $this->actingAs($user)->post('/orcamento/importar/confirmar', ['linhas' => $enviar, 'origem' => 'conta:'.$conta->id])->assertRedirect();
        $this->assertSame(2, $user->transactions()->where('account_id', $conta->id)->count());

        $denovo = $this->actingAs($user)
            ->post('/orcamento/importar', ['arquivo' => UploadedFile::fake()->createWithContent('extrato.ofx', self::OFX)])
            ->viewData('linhas');
        $this->assertFalse($denovo[0]['incluir']);
        $this->assertSame('Já lançado', $denovo[1]['aviso']);
    }

    public function test_importa_fatura_no_cartao_com_fatura_calculada(): void
    {
        $user = User::factory()->create();
        $cartao = $user->cards()->create(['nome' => 'Nubank', 'limite' => 5000, 'dia_fechamento' => 3, 'dia_vencimento' => 10]);

        $this->actingAs($user)->post('/orcamento/importar/confirmar', ['origem' => 'cartao:'.$cartao->id, 'linhas' => [
            ['incluir' => '1', 'data' => '2026-10-08', 'descricao' => 'Ifood', 'valor' => '62.9', 'tipo' => 'despesa', 'categoria' => 'Restaurantes'],
        ]])->assertRedirect();

        $this->assertSame('2026-11', $user->transactions()->first()->fatura);
    }
}
