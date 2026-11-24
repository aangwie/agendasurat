<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URL Gateway WhatsApp
    |--------------------------------------------------------------------------
    |
    | Nilai bawaan menunjuk ke skrip gateway (server.js) milik Billnesia.
    | Service akan memotong akhiran "/server.js" sehingga API dipanggil pada
    | host yang sama, mis. http://gateway.billnesia.com/status/{deviceId}.
    |
    */

    'url' => env('WHATSAPP_GATEWAY_URL', 'http://gateway.billnesia.com/server.js'),

    /*
    |--------------------------------------------------------------------------
    | Device ID
    |--------------------------------------------------------------------------
    |
    | Nama sesi pada gateway. Gateway menyimpan kredensial Baileys pada folder
    | .baileys_auth/device_{deviceId}, sehingga sesi tetap tersimpan setelah
    | server gateway dijalankan ulang.
    |
    */

    'device_id' => env('WHATSAPP_GATEWAY_DEVICE', 'agenda-surat'),

    /*
    |--------------------------------------------------------------------------
    | Batas Waktu & Interval Polling
    |--------------------------------------------------------------------------
    |
    | Timeout (detik) untuk setiap permintaan ke gateway dan interval (ms)
    | untuk memuat ulang QR/status secara otomatis pada halaman.
    |
    */

    'timeout' => (int) env('WHATSAPP_GATEWAY_TIMEOUT', 15),

    'poll_interval' => (int) env('WHATSAPP_GATEWAY_POLL_INTERVAL', 5000),

    /*
    |--------------------------------------------------------------------------
    | Notifikasi Surat
    |--------------------------------------------------------------------------
    |
    | Bila aktif, administrator menerima pesan WhatsApp setiap ada surat keluar
    | atau surat tugas baru. Nomor admin yang sama dengan nomor WhatsApp gateway
    | otomatis dilewati agar tidak mengirim pesan ke perangkat sendiri.
    |
    */

    'notify_letters' => (bool) env('WHATSAPP_GATEWAY_NOTIFY_LETTERS', true),

    /*
    |--------------------------------------------------------------------------
    | Batas Waktu Notifikasi
    |--------------------------------------------------------------------------
    |
    | Timeout (detik) khusus pengiriman notifikasi surat, lebih pendek dari
    | timeout umum agar pencatatan surat tidak tertahan bila gateway mati.
    |
    */

    'notify_timeout' => (int) env('WHATSAPP_GATEWAY_NOTIFY_TIMEOUT', 5),

];
