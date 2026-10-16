<?php

use App\Http\Middleware\SomenteAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['admin' => SomenteAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 (sessão expirada / formulário antigo): volta para a página com um aviso
        // e o que foi digitado, em vez da tela "Page Expired".
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', 'current_password', '_token']))
                ->with('aviso', 'A página tinha expirado (ficou aberta muito tempo ou o servidor foi reiniciado). Confira os dados e envie de novo.');
        });
    })->create();
