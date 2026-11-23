<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageIncomingLetters
{
    /**
     * Batasi pencatatan surat masuk pada administrator dan Subbagian Umum
     * dan Kepegawaian (kode bidang 408.37.01). Akun lain hanya boleh membaca.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canManageIncomingLetters()) {
            abort(403, 'Surat masuk hanya dapat diisi oleh Subbagian Umum dan Kepegawaian atau administrator.');
        }

        return $next($request);
    }
}
