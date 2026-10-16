<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cotações da B3 durante o pregão (seg. a sex., 10h às 18h30 de Brasília).
Schedule::command('cotacoes:atualizar --classe=acoes --classe=fii --classe=exterior')
    ->everyFifteenMinutes()
    ->weekdays()
    ->between('10:00', '18:30')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

// Cripto negocia 24 horas por dia, todos os dias.
Schedule::command('cotacoes:atualizar --classe=cripto')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
