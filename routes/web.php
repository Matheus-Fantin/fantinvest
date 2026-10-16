<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\InviteController;
use App\Http\Controllers\AllocationTargetController;
use App\Http\Controllers\AporteController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BolsaController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CotacaoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DividendController;
use App\Http\Controllers\FinanciamentoImovelController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\ImportacaoController;
use App\Http\Controllers\PatrimonioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/patrimonio', [PatrimonioController::class, 'index'])->name('patrimonio.index');

    Route::get('/contas', [AccountController::class, 'index'])->name('contas.index');
    Route::post('/contas', [AccountController::class, 'store'])->name('contas.store');
    Route::put('/contas/{account}', [AccountController::class, 'update'])->name('contas.update');
    Route::delete('/contas/{account}', [AccountController::class, 'destroy'])->name('contas.destroy');
    Route::post('/transferencias', [AccountController::class, 'transferir'])->name('transferencias.store');
    Route::delete('/transferencias/{transfer}', [AccountController::class, 'excluirTransferencia'])->name('transferencias.destroy');

    Route::get('/cartoes', [CardController::class, 'index'])->name('cartoes.index');
    Route::post('/cartoes', [CardController::class, 'store'])->name('cartoes.store');
    Route::get('/cartoes/{card}', [CardController::class, 'show'])->name('cartoes.show');
    Route::put('/cartoes/{card}', [CardController::class, 'update'])->name('cartoes.update');
    Route::delete('/cartoes/{card}', [CardController::class, 'destroy'])->name('cartoes.destroy');
    Route::post('/cartoes/{card}/pagar', [CardController::class, 'pagar'])->name('cartoes.pagar');

    Route::get('/bolsa', [BolsaController::class, 'index'])->name('bolsa.index');
    Route::get('/bolsa/{ticker}', [BolsaController::class, 'show'])->name('bolsa.show');
    Route::post('/bolsa/{ticker}/operacao', [BolsaController::class, 'operacao'])->name('bolsa.operacao');

    Route::get('/dividendos', [DividendController::class, 'index'])->name('dividendos.index');
    Route::post('/dividendos', [DividendController::class, 'store'])->name('dividendos.store');
    Route::delete('/dividendos/{dividend}', [DividendController::class, 'destroy'])->name('dividendos.destroy');

    Route::get('/financiamento-imovel', [FinanciamentoImovelController::class, 'index'])->name('imovel.index');
    Route::post('/financiamento-imovel', [FinanciamentoImovelController::class, 'store'])->name('imovel.store');
    Route::get('/financiamento-imovel/{financing}', [FinanciamentoImovelController::class, 'show'])->name('imovel.show');
    Route::put('/financiamento-imovel/{financing}', [FinanciamentoImovelController::class, 'update'])->name('imovel.update');
    Route::delete('/financiamento-imovel/{financing}', [FinanciamentoImovelController::class, 'destroy'])->name('imovel.destroy');
    Route::post('/financiamento-imovel/{financing}/liberacoes', [FinanciamentoImovelController::class, 'liberacao'])->name('imovel.liberacao');
    Route::post('/financiamento-imovel/{financing}/gastos', [FinanciamentoImovelController::class, 'gasto'])->name('imovel.gasto');
    Route::post('/financiamento-imovel/{financing}/pagamentos', [FinanciamentoImovelController::class, 'pagamento'])->name('imovel.pagamento');
    Route::delete('/financiamento-imovel/{financing}/{tipo}/{id}', [FinanciamentoImovelController::class, 'excluirItem'])
        ->whereIn('tipo', ['liberacao', 'gasto', 'pagamento'])->whereNumber('id')->name('imovel.excluir');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/convites', [InviteController::class, 'index'])->name('convites.index');
        Route::post('/convites', [InviteController::class, 'store'])->name('convites.store');
        Route::delete('/convites/{invite}', [InviteController::class, 'destroy'])->name('convites.destroy');
    });

    Route::get('/carteira', [AssetController::class, 'index'])->name('carteira.index');
    Route::post('/carteira', [AssetController::class, 'store'])->name('carteira.store');
    Route::put('/carteira/{asset}', [AssetController::class, 'update'])->name('carteira.update');
    Route::delete('/carteira/{asset}', [AssetController::class, 'destroy'])->name('carteira.destroy');
    Route::put('/carteira/{asset}/nota', [AssetController::class, 'nota'])->name('carteira.nota');
    Route::put('/metas', [AllocationTargetController::class, 'update'])->name('metas.update');
    Route::post('/cotacoes/atualizar', [CotacaoController::class, 'atualizar'])
        ->middleware('throttle:6,1')
        ->name('cotacoes.atualizar');
    Route::put('/reserva', [ReservaController::class, 'update'])->name('reserva.update');

    Route::get('/aporte', [AporteController::class, 'index'])->name('aporte.index');
    Route::post('/aporte', [AporteController::class, 'store'])->name('aporte.store');

    Route::get('/orcamento', [TransactionController::class, 'index'])->name('orcamento.index');
    Route::post('/orcamento', [TransactionController::class, 'store'])->name('orcamento.store');
    Route::get('/orcamento/importar', [ImportacaoController::class, 'create'])->name('orcamento.importar');
    Route::post('/orcamento/importar', [ImportacaoController::class, 'preview'])->name('orcamento.importar.preview');
    Route::post('/orcamento/importar/confirmar', [ImportacaoController::class, 'store'])->name('orcamento.importar.store');
    Route::put('/orcamento/{transaction}', [TransactionController::class, 'update'])->name('orcamento.update');
    Route::delete('/orcamento/{transaction}', [TransactionController::class, 'destroy'])->name('orcamento.destroy');

    Route::get('/objetivos', [GoalController::class, 'index'])->name('objetivos.index');
    Route::post('/objetivos', [GoalController::class, 'store'])->name('objetivos.store');
    Route::put('/objetivos/{goal}', [GoalController::class, 'update'])->name('objetivos.update');
    Route::delete('/objetivos/{goal}', [GoalController::class, 'destroy'])->name('objetivos.destroy');
    Route::post('/objetivos/{goal}/depositar', [GoalController::class, 'depositar'])->name('objetivos.depositar');

    Route::view('/simuladores/juros-compostos', 'simuladores.juros')->name('simuladores.juros');
    Route::view('/simuladores/financiamento', 'simuladores.financiamento')->name('simuladores.financiamento');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
