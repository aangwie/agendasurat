<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lembar Disposisi
    |--------------------------------------------------------------------------
    |
    | Daftar tujuan dan tindakan yang dicetak pada lembar disposisi surat
    | masuk. Silakan sesuaikan dengan struktur organisasi instansi Anda.
    |
    */

    'disposisi' => [

        'tujuan' => [
            'Kepala Dinas Pendidikan',
            'Sekretaris Dinas Pendidikan',
            'Kepala Sub Bagian Umum dan Kepegawaian',
            'Analis Keuangan Pusat dan Daerah',
            'Kepala Bidang Pendidik dan Tenaga Kependidikan',
            'Kepala Pembinaan Bidang PAUD dan PNF',
            'Kepala Bidang Pembinaan SD',
            'Kepala Bidang Pembinaan SMP',
            'Koordinator Pengawas',
            'Penyusun Program Anggaran dan Pelaporan',
        ],

        'tindakan' => [
            'Tanggapan dan saran',
            'Proses lebih lanjut',
            'Koordinasi/konfirmasi',
            '.........................',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment (halaman Pengaturan)
    |--------------------------------------------------------------------------
    |
    | commands_enabled : izinkan eksekusi perintah git/artisan dari halaman
    |                    Pengaturan (dimatikan saat pengujian agar tidak
    |                    mengubah repository/database sungguhan).
    | origin_fallback  : bila push memakai token gagal, coba push melalui
    |                    remote "origin" yang kredensialnya tersimpan di server.
    |
    */

    'deployment' => [
        'commands_enabled' => env('DEPLOY_COMMANDS_ENABLED', true),
        'origin_fallback' => env('DEPLOY_ORIGIN_FALLBACK', true),
    ],

];
