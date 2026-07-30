<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render 등 TLS 종료 프록시 뒤에서 X-Forwarded-Proto(https)를 신뢰.
        // 없으면 앱이 http로 인식해 @vite 에셋이 http로 생성되어 혼합콘텐츠 차단됨.
        $middleware->trustProxies(at: '*');

        $middleware->alias(['admin' => \App\Http\Middleware\EnsureAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
