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

    /*
    |--------------------------------------------------------------------------
    | Lampiran (Media)
    |--------------------------------------------------------------------------
    |
    | Gateway menyediakan POST /send-media dengan body JSON berisi device_id,
    | recipient, type (image|video|audio), caption, serta salah satu dari url
    | atau file_path. Lampiran disimpan pada disk publik agar gateway dapat
    | mengunduhnya lewat URL; "file_path" hanya terpakai bila gateway berada
    | pada server yang sama dengan aplikasi.
    |
    | Gateway belum mendukung type "document", sehingga PDF dikirim sebagai
    | pesan teks berisi tautan unduhan (document_link_fallback).
    |
    */

    'media_path' => env('WHATSAPP_GATEWAY_MEDIA_PATH', '/send-media'),

    'media_disk' => env('WHATSAPP_GATEWAY_MEDIA_DISK', 'public'),

    'attachment_directory' => env('WHATSAPP_GATEWAY_ATTACHMENT_DIR', 'whatsapp/attachments'),

    /*
    |--------------------------------------------------------------------------
    | URL Publik Lampiran (opsional)
    |--------------------------------------------------------------------------
    |
    | Gateway mengunduh lampiran lewat URL, sehingga alamatnya harus dapat
    | diakses dari internet. Bila APP_URL masih menunjuk localhost (umum pada
    | pengembangan lokal), isi nilai ini dengan domain publik aplikasi, mis.
    | https://agenda.domain.go.id — URL lampiran menjadi:
    | {nilai ini}/storage/whatsapp/attachments/xxxx.png
    |
    */

    'attachment_public_url' => env('WHATSAPP_GATEWAY_PUBLIC_URL'),

    'document_link_fallback' => (bool) env('WHATSAPP_GATEWAY_DOCUMENT_LINK_FALLBACK', true),

    'attachment_max_kb' => (int) env('WHATSAPP_GATEWAY_ATTACHMENT_MAX_KB', 8192),

];
