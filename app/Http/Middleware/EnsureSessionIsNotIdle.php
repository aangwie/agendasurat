<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logout otomatis setelah tidak ada aktivitas pengguna.
 *
 * Batas waktu diambil dari `session.idle_timeout` (menit). Waktu aktivitas
 * terakhir disimpan pada session sehingga berlaku di semua perangkat, dan
 * permintaan tanpa aktivitas pengguna (detak CSRF) sengaja tidak memperpanjang
 * sesi agar tab yang dibiarkan terbuka tetap berakhir sesuai batas.
 */
class EnsureSessionIsNotIdle
{
    /**
     * Kunci session berisi waktu (timestamp) aktivitas terakhir pengguna.
     */
    public const SESSION_KEY = 'last_activity_at';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timeout = $this->timeoutInMinutes();

        // Fitur dimatikan, atau pengunjung belum login.
        if ($timeout <= 0 || ! Auth::guard('web')->check()) {
            return $next($request);
        }

        // Detak CSRF berkala bukan aksi pengguna: jangan dihitung sebagai
        // aktivitas, agar logout otomatis tetap berjalan.
        if ($request->routeIs('csrf-token')) {
            return $next($request);
        }

        $now = now()->getTimestamp();
        $lastActivity = (int) $request->session()->get(self::SESSION_KEY, 0);

        if ($lastActivity > 0 && ($now - $lastActivity) > $timeout * 60) {
            return $this->expireSession($request, $timeout);
        }

        $request->session()->put(self::SESSION_KEY, $now);

        return $next($request);
    }

    /**
     * Akhiri sesi karena melewati batas idle.
     */
    private function expireSession(Request $request, int $timeout): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = "Sesi Anda berakhir karena tidak ada aktivitas selama {$timeout} menit. Silakan masuk kembali.";

        // Permintaan AJAX (datatable, popup disposisi) menerima 401 agar
        // JavaScript dapat langsung mengalihkan ke halaman masuk.
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'session_idle' => true,
                'login_url' => route('login'),
            ], 401);
        }

        return redirect()
            ->route('login')
            ->with('status', $message);
    }

    /**
     * Batas idle (menit) dari konfigurasi session.
     */
    private function timeoutInMinutes(): int
    {
        return max(0, (int) config('session.idle_timeout', 10));
    }
}
