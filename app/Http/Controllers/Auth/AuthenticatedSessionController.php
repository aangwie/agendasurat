<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSessionIsNotIdle;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Mulai hitungan logout otomatis sejak pengguna berhasil masuk.
        $request->session()->put(EnsureSessionIsNotIdle::SESSION_KEY, now()->getTimestamp());

        return redirect()
            ->intended(route('dashboard', absolute: false))
            ->with('status', 'Selamat datang kembali, '.Auth::user()->name.'.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Anda telah keluar dari akun.');
    }

    /**
     * Segarkan waktu aktivitas terakhir (dipakai hitungan mundur di browser).
     *
     * Middleware `EnsureSessionIsNotIdle` sudah memperbarui waktu aktivitas
     * untuk setiap permintaan; endpoint ini hanya memberi konfirmasi dan sisa
     * waktu agar JavaScript dapat menyelaraskan hitungannya dengan server.
     */
    public function keepAlive(Request $request): JsonResponse
    {
        $timeout = max(0, (int) config('session.idle_timeout', 10));

        $request->session()->put(EnsureSessionIsNotIdle::SESSION_KEY, now()->getTimestamp());

        return response()
            ->json([
                'ok' => true,
                'timeout' => $timeout * 60,
                'expires_in' => $timeout * 60,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Akhiri sesi karena melewati batas tidak ada aktivitas.
     *
     * Dipakai oleh hitungan mundur di browser. Rute ini sengaja berada di luar
     * middleware `auth` agar tetap berfungsi bila sesi sudah tidak valid,
     * sehingga penerima selalu berakhir di halaman masuk dengan pesan jelas.
     */
    public function idleLogout(Request $request): RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $minutes = max(1, (int) config('session.idle_timeout', 10));

        return redirect()
            ->route('login')
            ->with('status', "Sesi Anda berakhir karena tidak ada aktivitas selama {$minutes} menit. Silakan masuk kembali.");
    }
}
