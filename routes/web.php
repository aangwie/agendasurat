<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncomingLetterController;
use App\Http\Controllers\InstitutionProfileController;
use App\Http\Controllers\LetterClassificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('profil', [ProfileController::class, 'show'])->name('profil.show');

    Route::get('profil-instansi', [InstitutionProfileController::class, 'edit'])->name('profil-instansi.edit');
    Route::put('profil-instansi', [InstitutionProfileController::class, 'update'])->name('profil-instansi.update');

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

    Route::view('agenda', 'modules.placeholder', [
        'title' => 'Agenda',
        'description' => 'Atur jadwal kegiatan kantor dan pantau agenda harian seluruh bagian.',
        'icon' => 'calendar',
        'features' => [
            'Kalender kegiatan terpusat',
            'Pengingat agenda mendatang',
            'Rekap kegiatan per periode',
        ],
    ])->name('agenda.index');

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

    Route::get('pengguna', [UserController::class, 'index'])->name('pengguna.index');
    Route::post('pengguna', [UserController::class, 'store'])->name('pengguna.store');
    Route::put('pengguna/{pengguna}', [UserController::class, 'update'])->name('pengguna.update');
    Route::delete('pengguna/{pengguna}', [UserController::class, 'destroy'])->name('pengguna.destroy');
    Route::view('pengaturan', 'modules.placeholder', [
        'title' => 'Pengaturan',
        'description' => 'Sesuaikan identitas instansi, format penomoran, dan preferensi aplikasi.',
        'icon' => 'cog',
        'features' => [
            'Profil dan logo instansi',
            'Format penomoran surat',
            'Preferensi tampilan aplikasi',
        ],
    ])->name('pengaturan.index');
});
