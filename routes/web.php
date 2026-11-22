<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncomingLetterController;
use App\Http\Controllers\InstitutionProfileController;
use App\Http\Controllers\LetterClassificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// Token CSRF segar + memperpanjang masa aktif sesi selama tab masih terbuka.
// Dipakai oleh resources/js/csrf.js agar halaman tidak "419 Page Expired".
Route::get('csrf-token', function (Request $request) {
    return response()
        ->json(['token' => csrf_token()])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
})->name('csrf-token');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('profil', [ProfileController::class, 'show'])->name('profil.show');

    Route::middleware('admin')->group(function () {
        Route::get('profil-instansi', [InstitutionProfileController::class, 'edit'])->name('profil-instansi.edit');
        Route::put('profil-instansi', [InstitutionProfileController::class, 'update'])->name('profil-instansi.update');

        Route::get('pengguna', [UserController::class, 'index'])->name('pengguna.index');
        Route::post('pengguna', [UserController::class, 'store'])->name('pengguna.store');
        Route::put('pengguna/{pengguna}', [UserController::class, 'update'])->name('pengguna.update');
        Route::delete('pengguna/{pengguna}', [UserController::class, 'destroy'])->name('pengguna.destroy');

        Route::get('pengaturan', [SettingsController::class, 'index'])->name('pengaturan.index');
        Route::put('pengaturan', [SettingsController::class, 'update'])->name('pengaturan.update');
        Route::post('pengaturan/aksi', [SettingsController::class, 'action'])->name('pengaturan.action');
        Route::get('pengaturan/log', [SettingsController::class, 'log'])->name('pengaturan.log');
        Route::delete('pengaturan/log', [SettingsController::class, 'clearLog'])->name('pengaturan.log.clear');
        Route::post('pengaturan/backup', [SettingsController::class, 'backup'])->name('pengaturan.backup');
        Route::get('pengaturan/backup/{filename}/unduh', [SettingsController::class, 'downloadBackup'])
            ->name('pengaturan.backup.download');
        Route::post('pengaturan/import', [SettingsController::class, 'import'])->name('pengaturan.import');
    });

    Route::get('surat-masuk', [IncomingLetterController::class, 'index'])->name('surat-masuk.index');
    Route::post('surat-masuk', [IncomingLetterController::class, 'store'])->name('surat-masuk.store');
    Route::put('surat-masuk/{incomingLetter}', [IncomingLetterController::class, 'update'])->name('surat-masuk.update');
    Route::delete('surat-masuk/{incomingLetter}', [IncomingLetterController::class, 'destroy'])->name('surat-masuk.destroy');
    Route::get('surat-masuk/{incomingLetter}/cetak', [IncomingLetterController::class, 'print'])->name('surat-masuk.cetak');

    Route::view('surat-keluar', 'modules.placeholder', [
        'title' => 'Surat Keluar',
        'description' => 'Kelola penomoran, pembuatan, dan pengiriman surat keluar instansi secara tertib.',
        'icon' => 'paper-airplane',
        'features' => [
            'Penomoran surat keluar otomatis',
            'Template surat dinas siap pakai',
            'Riwayat pengiriman dan arsip digital',
        ],
    ])->name('surat-keluar.index');

    Route::view('surat-tugas', 'modules.placeholder', [
        'title' => 'Surat Tugas',
        'description' => 'Terbitkan dan arsipkan surat tugas pegawai lengkap dengan dasar penugasan dan periode tugas.',
        'icon' => 'clipboard-document-list',
        'features' => [
            'Penerbitan surat tugas pegawai',
            'Dasar dan uraian penugasan',
            'Rekap penugasan per pegawai',
        ],
    ])->name('surat-tugas.index');

    Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::post('agenda', [AgendaController::class, 'store'])->name('agenda.store');
    Route::put('agenda/{agenda}', [AgendaController::class, 'update'])->name('agenda.update');
    Route::delete('agenda/{agenda}', [AgendaController::class, 'destroy'])->name('agenda.destroy');
    Route::get('klasifikasi', [LetterClassificationController::class, 'index'])->name('klasifikasi.index');

    Route::view('laporan', 'modules.placeholder', [
        'title' => 'Laporan',
        'description' => 'Pantau rekapitulasi surat dan agenda dalam bentuk laporan yang siap dicetak.',
        'icon' => 'chart-bar',
        'features' => [
            'Rekap surat bulanan',
            'Ekspor data ke Excel dan PDF',
            'Statistik surat per bagian',
        ],
    ])->name('laporan.index');
});
