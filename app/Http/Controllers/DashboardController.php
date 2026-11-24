<?php

namespace App\Http\Controllers;

use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\TaskLetter;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * Catatan: kartu ringkasan memakai data nyata dari tabel surat, sedangkan
     * daftar surat terbaru, agenda, dan rincian status masih berupa data contoh
     * (placeholder) sampai modul terkait tersedia.
     */
    public function __invoke(): View
    {
        $now = Carbon::now();

        $greeting = match (true) {
            $now->hour < 11 => 'Selamat pagi',
            $now->hour < 15 => 'Selamat siang',
            $now->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };

        $incomingTotal = IncomingLetter::query()->count();
        $incomingThisMonth = IncomingLetter::query()
            ->whereBetween('received_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->count();

        $outgoingTotal = OutgoingLetter::query()->count();
        $outgoingThisMonth = OutgoingLetter::query()
            ->whereBetween('letter_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->count();

        $taskTotal = TaskLetter::query()->count();
        $taskThisMonth = TaskLetter::query()
            ->whereBetween('letter_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->count();

        $totalLetters = $incomingTotal + $outgoingTotal + $taskTotal;

        $stats = [
            [
                'label' => 'Surat Masuk',
                'value' => $incomingTotal,
                'delta' => '+'.$incomingThisMonth,
                'trend' => 'up',
                'hint' => 'surat bulan ini',
                'icon' => 'inbox',
                'accent' => 'rose',
            ],
            [
                'label' => 'Surat Keluar',
                'value' => $outgoingTotal,
                'delta' => '+'.$outgoingThisMonth,
                'trend' => 'up',
                'hint' => 'surat bulan ini',
                'icon' => 'paper-airplane',
                'accent' => 'blue',
            ],
            [
                'label' => 'Surat Tugas',
                'value' => $taskTotal,
                'delta' => '+'.$taskThisMonth,
                'trend' => 'up',
                'hint' => 'surat bulan ini',
                'icon' => 'clipboard-document-list',
                'accent' => 'amber',
            ],
            [
                'label' => 'Total Surat',
                'value' => $totalLetters,
                'delta' => 'Semua',
                'trend' => 'neutral',
                'hint' => 'surat tercatat',
                'icon' => 'document-text',
                'accent' => 'zinc',
            ],
        ];

        // Grafik 3 bar: Surat Masuk (merah), Surat Keluar (biru laut),
        // Surat Tugas (kuning tua).
        $letterVolume = [
            [
                'label' => 'Surat Masuk',
                'value' => $incomingTotal,
                'color' => '#e11d48',
                'route' => route('surat-masuk.index'),
            ],
            [
                'label' => 'Surat Keluar',
                'value' => $outgoingTotal,
                'color' => '#1e3a8a',
                'route' => route('surat-keluar.index'),
            ],
            [
                'label' => 'Surat Tugas',
                'value' => $taskTotal,
                'color' => '#ca8a04',
                'route' => route('surat-tugas.index'),
            ],
        ];

        $maxVolume = max(1, collect($letterVolume)->max('value'));

        $recentLetters = [
            [
                'number' => 'SM/2026/0148',
                'subject' => 'Undangan Rapat Koordinasi Tata Usaha',
                'party' => 'Dinas Pendidikan Provinsi',
                'date' => '20 Nov 2026',
                'status' => 'Baru',
                'direction' => 'Masuk',
            ],
            [
                'number' => 'SK/2026/0093',
                'subject' => 'Permohonan Data Rekapitulasi Surat',
                'party' => 'Bagian Umum Sekretariat',
                'date' => '20 Nov 2026',
                'status' => 'Diproses',
                'direction' => 'Keluar',
            ],
            [
                'number' => 'SM/2026/0147',
                'subject' => 'Pemberitahuan Perubahan Jadwal Kegiatan',
                'party' => 'Kecamatan Sukamaju',
                'date' => '19 Nov 2026',
                'status' => 'Selesai',
                'direction' => 'Masuk',
            ],
            [
                'number' => 'SK/2026/0092',
                'subject' => 'Balasan Undangan Forum Koordinasi',
                'party' => 'Inspektorat Daerah',
                'date' => '18 Nov 2026',
                'status' => 'Diarsipkan',
                'direction' => 'Keluar',
            ],
            [
                'number' => 'SM/2026/0146',
                'subject' => 'Laporan Bulanan Arsip Surat Dinas',
                'party' => 'Arsip Daerah',
                'date' => '18 Nov 2026',
                'status' => 'Baru',
                'direction' => 'Masuk',
            ],
        ];

        $agendas = [
            ['time' => '08.30', 'title' => 'Rapat koordinasi surat masuk', 'place' => 'Ruang Rapat Utama', 'tag' => 'Internal'],
            ['time' => '10.00', 'title' => 'Verifikasi dokumen anggaran', 'place' => 'Bagian Keuangan', 'tag' => 'Keuangan'],
            ['time' => '13.30', 'title' => 'Disposisi surat dinas luar', 'place' => 'Meja Kepala TU', 'tag' => 'Disposisi'],
        ];

        $statusBreakdown = [
            ['label' => 'Selesai', 'value' => 62, 'color' => '#10b981'],
            ['label' => 'Diproses', 'value' => 24, 'color' => '#f59e0b'],
            ['label' => 'Baru', 'value' => 14, 'color' => '#f43f5e'],
        ];

        $donutGradient = 'conic-gradient(';
        $offset = 0;

        foreach ($statusBreakdown as $segment) {
            $donutGradient .= "{$segment['color']} {$offset}% ".($offset + $segment['value']).'%';
            $offset += $segment['value'];
            $donutGradient .= $offset < 100 ? ', ' : ')';
        }

        return view('dashboard', [
            'greeting' => $greeting,
            'today' => $now->locale('id')->translatedFormat('l, j F Y'),
            'stats' => $stats,
            'letterVolume' => $letterVolume,
            'maxVolume' => $maxVolume,
            'totalLetters' => $totalLetters,
            'recentLetters' => $recentLetters,
            'agendas' => $agendas,
            'statusBreakdown' => $statusBreakdown,
            'donutGradient' => $donutGradient,
        ]);
    }
}
