<?php

use App\Http\Controllers\AllocationTargetController;
use App\Http\Controllers\AporteController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CotacaoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\ImportacaoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
