<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncomingLetterController;
use App\Http\Controllers\InstitutionProfileController;
use App\Http\Controllers\LetterClassificationController;
use App\Http\Controllers\OutgoingLetterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskLetterController;
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

    // Semua pengguna boleh membaca & mencetak surat masuk.
    Route::get('surat-masuk', [IncomingLetterController::class, 'index'])->name('surat-masuk.index');
    Route::get('surat-masuk/{incomingLetter}/cetak', [IncomingLetterController::class, 'print'])->name('surat-masuk.cetak');

    // Pencatatan surat masuk hanya untuk Subbag Umum & Kepegawaian dan admin.
    Route::middleware('surat-masuk.manage')->group(function () {
        Route::post('surat-masuk', [IncomingLetterController::class, 'store'])->name('surat-masuk.store');
        Route::put('surat-masuk/{incomingLetter}', [IncomingLetterController::class, 'update'])->name('surat-masuk.update');
        Route::delete('surat-masuk/{incomingLetter}', [IncomingLetterController::class, 'destroy'])->name('surat-masuk.destroy');
    });

    Route::get('surat-keluar', [OutgoingLetterController::class, 'index'])->name('surat-keluar.index');
    Route::post('surat-keluar', [OutgoingLetterController::class, 'store'])->name('surat-keluar.store');
    Route::put('surat-keluar/{outgoingLetter}', [OutgoingLetterController::class, 'update'])->name('surat-keluar.update');
    Route::delete('surat-keluar/{outgoingLetter}', [OutgoingLetterController::class, 'destroy'])->name('surat-keluar.destroy');

    Route::get('surat-tugas', [TaskLetterController::class, 'index'])->name('surat-tugas.index');
    Route::post('surat-tugas', [TaskLetterController::class, 'store'])->name('surat-tugas.store');
    Route::put('surat-tugas/{taskLetter}', [TaskLetterController::class, 'update'])->name('surat-tugas.update');
    Route::delete('surat-tugas/{taskLetter}', [TaskLetterController::class, 'destroy'])->name('surat-tugas.destroy');

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
