<?php

use App\Http\Middleware\EnsureSessionIsNotIdle;
use App\Http\Middleware\EnsureUserCanManageIncomingLetters;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'surat-masuk.manage' => EnsureUserCanManageIncomingLetters::class,
        ]);

        // Logout otomatis bila tidak ada aktivitas (lihat config/session.php).
        // Ditempatkan pada grup "web" agar berlaku untuk semua halaman yang
        // membutuhkan login tanpa perlu menambahkannya ke setiap rute.
        $middleware->web(append: [
            EnsureSessionIsNotIdle::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Tangani "419 Page Expired" (CSRF token / sesi kedaluwarsa).
        $render419 = function (Request $request) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Sesi Anda telah berakhir. Halaman akan dimuat ulang.',
                    'csrf_expired' => true,
                ], 419);
            }

            return response()->view('errors.419', [], 419);
        };

        // Laravel mengubah TokenMismatchException menjadi HttpException(419)
        // sebelum render callback dijalankan, jadi keduanya perlu ditangani.
        $exceptions->render(function (TokenMismatchException $exception, Request $request) use ($render419) {
            return $render419($request);
        });

        $exceptions->render(function (HttpException $exception, Request $request) use ($render419) {
            if ($exception->getStatusCode() === 419) {
                return $render419($request);
            }

            return null;
        });
    })->create();
