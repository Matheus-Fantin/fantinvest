<?php

use App\Http\Controllers\AllocationTargetController;
use App\Http\Controllers\AporteController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\DashboardController;
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
    Route::put('/reserva', [ReservaController::class, 'update'])->name('reserva.update');

    Route::get('/aporte', [AporteController::class, 'index'])->name('aporte.index');
    Route::post('/aporte', [AporteController::class, 'store'])->name('aporte.store');

    Route::get('/orcamento', [TransactionController::class, 'index'])->name('orcamento.index');
    Route::post('/orcamento', [TransactionController::class, 'store'])->name('orcamento.store');
    Route::delete('/orcamento/{transaction}', [TransactionController::class, 'destroy'])->name('orcamento.destroy');

    Route::view('/simuladores/juros-compostos', 'simuladores.juros')->name('simuladores.juros');
    Route::view('/simuladores/financiamento', 'simuladores.financiamento')->name('simuladores.financiamento');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
