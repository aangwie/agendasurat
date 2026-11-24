<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Klien untuk WhatsApp Gateway berbasis Baileys (server.js).
 *
 * Endpoint yang dipakai:
 * - GET  /health              : cek kesehatan gateway
 * - GET  /qr/{deviceId}       : minta QR (tambahkan ?force=true untuk reset sesi)
 * - GET  /status/{deviceId}   : status koneksi (connected/pending/connecting/...)
 * - POST /send                : kirim pesan teks
 * - POST /disconnect/{id}     : putuskan koneksi & hapus sesi di gateway
 * - GET  /logs/{deviceId}     : log perangkat
 *
 * Sesi perangkat (device_id + nomor WhatsApp) ikut disimpan pada tabel
 * app_settings agar aplikasi tetap tahu perangkat mana yang dipakai.
 */
class WhatsappGatewayService
{
    private const SETTING_URL = 'whatsapp_gateway_url';

    private const SETTING_DEVICE = 'whatsapp_gateway_device';

    private const SETTING_SESSION_DEVICE = 'whatsapp_session_device';

    private const SETTING_SESSION_PHONE = 'whatsapp_session_phone';

    private const SETTING_SESSION_SAVED_AT = 'whatsapp_session_saved_at';

    /**
     * URL bawaan (skrip gateway), dipakai saat pengaturan belum diisi.
     */
    public function defaultUrl(): string
    {
        return (string) config('whatsapp-gateway.url', 'http://gateway.billnesia.com/server.js');
    }

    /**
     * Device ID bawaan.
     */
    public function defaultDeviceId(): string
    {
        return (string) config('whatsapp-gateway.device_id', 'agenda-surat');
    }

    /**
     * URL gateway sebagaimana tersimpan (boleh berakhiran /server.js).
     */
    public function configuredUrl(): string
    {
        $url = trim((string) AppSetting::get(self::SETTING_URL, $this->defaultUrl()));

        return $url !== '' ? $url : $this->defaultUrl();
    }

    /**
     * Base URL API: tanpa akhiran /server.js dan tanpa garis miring di akhir.
     */
    public function baseUrl(): string
    {
        $url = preg_replace('~/server\.js$~i', '', $this->configuredUrl()) ?? $this->configuredUrl();

        return rtrim(trim($url), '/');
    }

    /**
     * Device ID aktif (hanya huruf, angka, titik, garis bawah, dan strip).
     */
    public function deviceId(): string
    {
        $device = preg_replace('/[^A-Za-z0-9._-]/', '-', trim((string) AppSetting::get(self::SETTING_DEVICE, $this->defaultDeviceId()))) ?? '';

        return $device !== '' ? $device : $this->defaultDeviceId();
    }

    /**
     * Simpan pengaturan koneksi gateway.
     */
    public function saveSettings(string $url, ?string $deviceId = null): void
    {
        AppSetting::put(self::SETTING_URL, trim($url));

        if (filled($deviceId)) {
            AppSetting::put(self::SETTING_DEVICE, trim($deviceId));
        }
    }

    /**
     * Sesi yang tersimpan di aplikasi (bukan kredensial Baileys).
     *
     * @return array{device_id: ?string, phone_number: ?string, saved_at: ?string}
     */
    public function savedSession(): array
    {
        return [
            'device_id' => AppSetting::get(self::SETTING_SESSION_DEVICE),
            'phone_number' => AppSetting::get(self::SETTING_SESSION_PHONE),
            'saved_at' => AppSetting::get(self::SETTING_SESSION_SAVED_AT),
        ];
    }

    /**
     * Simpan sesi perangkat aktif.
     *
     * @return array{device_id: ?string, phone_number: ?string, saved_at: ?string}
     */
    public function saveSession(?string $phoneNumber = null): array
    {
        $phoneNumber = filled($phoneNumber) ? $this->normalizePhone((string) $phoneNumber) : null;

        AppSetting::put(self::SETTING_SESSION_DEVICE, $this->deviceId());
        AppSetting::put(self::SETTING_SESSION_PHONE, $phoneNumber);
        AppSetting::put(self::SETTING_SESSION_SAVED_AT, now()->format('Y-m-d H:i:s'));

        return $this->savedSession();
    }

    /**
     * Hapus catatan sesi pada aplikasi.
     */
    public function forgetSession(): void
    {
        AppSetting::put(self::SETTING_SESSION_DEVICE, null);
        AppSetting::put(self::SETTING_SESSION_PHONE, null);
        AppSetting::put(self::SETTING_SESSION_SAVED_AT, null);
    }

    /**
     * Cek kesehatan gateway.
     *
     * @return array{ok: bool, status: string, message: string, data: array<string, mixed>}
     */
    public function health(?int $timeout = null): array
    {
        $result = $this->request('get', '/health', [], $timeout);

        if (! $result['ok']) {
            return [
                'ok' => false,
                'status' => 'offline',
                'message' => $result['message'] ?? 'Gateway tidak dapat dihubungi.',
                'data' => $result['data'],
            ];
        }

        return [
            'ok' => true,
            'status' => 'online',
            'message' => 'Gateway aktif.',
            'data' => $result['data'],
        ];
    }

    /**
     * Minta QR terbaru. Gunakan $force = true untuk menghapus sesi lama
     * di gateway lalu membuat QR baru.
     *
     * @return array{ok: bool, status: string, qr: ?string, phone_number: ?string, message: string}
     */
    public function qr(bool $force = false): array
    {
        $result = $this->request(
            'get',
            '/qr/'.rawurlencode($this->deviceId()),
            $force ? ['force' => 'true'] : [],
        );

        return $this->normalizeConnection($result);
    }

    /**
     * Status koneksi perangkat.
     *
     * @return array{ok: bool, status: string, qr: ?string, phone_number: ?string, message: string}
     */
    public function status(): array
    {
        $result = $this->request('get', '/status/'.rawurlencode($this->deviceId()));

        return $this->normalizeConnection($result);
    }

    /**
     * Kirim pesan teks.
     *
     * @return array{ok: bool, message: string, message_id: ?string, recipient: string}
     */
    public function send(string $recipient, string $message, ?int $timeout = null): array
    {
        $recipient = $this->normalizePhone($recipient);

        $result = $this->request('post', '/send', [
            'device_id' => $this->deviceId(),
            'nomor_penerima' => $recipient,
            'pesan' => $message,
        ], $timeout);

        $messageId = $result['data']['messageId'] ?? $result['data']['message_id'] ?? null;

        if (! $result['ok'] || ! ($result['data']['success'] ?? true)) {
            return [
                'ok' => false,
                'message' => $result['message'] ?? 'Pesan gagal dikirim.',
                'message_id' => null,
                'recipient' => $recipient,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Pesan berhasil dikirim ke '.$recipient.'.',
            'message_id' => is_string($messageId) ? $messageId : null,
            'recipient' => $recipient,
        ];
    }

    /**
     * Putuskan koneksi & hapus sesi pada gateway.
     *
     * @return array{ok: bool, message: string}
     */
    public function disconnect(): array
    {
        $result = $this->request('post', '/disconnect/'.rawurlencode($this->deviceId()));

        return [
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? 'Koneksi WhatsApp diputus dan sesi gateway dihapus.'
                : ($result['message'] ?? 'Gagal memutus koneksi gateway.'),
        ];
    }

    /**
     * Log perangkat dari gateway.
     *
     * @return array<int, string>
     */
    public function logs(): array
    {
        $result = $this->request('get', '/logs/'.rawurlencode($this->deviceId()));
        $logs = $result['data']['logs'] ?? [];

        return is_array($logs) ? array_values(array_map('strval', $logs)) : [];
    }

    /**
     * Normalisasi nomor Indonesia: 0812... / +62 812... / 812... => 62812...
     */
    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    /**
     * Ubah respons /qr atau /status menjadi struktur yang konsisten.
     *
     * @param  array{ok: bool, data: array<string, mixed>, message: ?string}  $result
     * @return array{ok: bool, status: string, qr: ?string, phone_number: ?string, message: string}
     */
    private function normalizeConnection(array $result): array
    {
        $data = $result['data'];
        $status = is_string($data['status'] ?? null) ? strtolower($data['status']) : ($result['ok'] ? 'unknown' : 'offline');
        $qr = is_string($data['qr'] ?? null) && $data['qr'] !== '' ? $data['qr'] : null;
        $phone = isset($data['phone_number']) && is_scalar($data['phone_number'])
            ? (string) $data['phone_number']
            : null;

        return [
            'ok' => $result['ok'],
            'status' => $status,
            'qr' => $qr,
            'phone_number' => $phone,
            'message' => $result['ok']
                ? $this->statusMessage($status, $phone)
                : ($result['message'] ?? 'Gateway tidak dapat dihubungi.'),
        ];
    }

    /**
     * Pesan status berbahasa Indonesia untuk ditampilkan pada halaman.
     */
    private function statusMessage(string $status, ?string $phone): string
    {
        return match ($status) {
            'connected' => $phone
                ? "Perangkat terhubung sebagai +{$phone}."
                : 'Perangkat WhatsApp terhubung.',
            'pending' => 'QR siap dipindai. Buka WhatsApp > Perangkat Tertaut > Tautkan perangkat.',
            'initializing' => 'Gateway sedang menyiapkan sesi dan QR. Mohon tunggu sebentar...',
            'connecting' => 'Gateway sedang memuat sesi tersimpan...',
            'disconnected' => 'Belum ada sesi aktif. Muat QR lalu pindai dengan WhatsApp.',
            default => 'Status gateway tidak dikenali.',
        };
    }

    /**
     * Kirim permintaan HTTP ke gateway.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, http_status: int|null, data: array<string, mixed>, message: ?string}
     */
    private function request(string $method, string $path, array $payload = [], ?int $timeout = null): array
    {
        $url = $this->baseUrl().'/'.ltrim($path, '/');
        $timeout = max(3, $timeout ?? (int) config('whatsapp-gateway.timeout', 15));

        try {
            $client = Http::acceptJson()->timeout($timeout)->connectTimeout(min(10, $timeout));

            $response = strtolower($method) === 'post'
                ? $client->post($url, $payload)
                : $client->get($url, $payload);
        } catch (ConnectionException) {
            return [
                'ok' => false,
                'http_status' => null,
                'data' => [],
                'message' => 'Tidak dapat terhubung ke gateway '.$this->baseUrl().'. Periksa URL gateway dan pastikan server gateway berjalan.',
            ];
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if ($response->failed()) {
            $error = $data['error'] ?? $data['message'] ?? null;

            return [
                'ok' => false,
                'http_status' => $response->status(),
                'data' => $data,
                'message' => is_string($error) && $error !== ''
                    ? $error
                    : 'Gateway mengembalikan status HTTP '.$response->status().'.',
            ];
        }

        return [
            'ok' => true,
            'http_status' => $response->status(),
            'data' => $data,
            'message' => null,
        ];
    }
}
