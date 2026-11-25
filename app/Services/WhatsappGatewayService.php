<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
     * Kirim lampiran (gambar/dokumen) ke gateway.
     *
     * Kontrak gateway Billnesia (POST /send-media, body JSON):
     *   device_id, recipient, type (image|video|audio), caption,
     *   serta salah satu dari url atau file_path.
     *
     * Berkas disimpan dulu pada disk publik agar gateway dapat mengunduhnya
     * lewat URL (atau membacanya lewat file_path bila gateway berada pada
     * server yang sama). Gateway hanya mendukung type image/video/audio,
     * sehingga dokumen seperti PDF otomatis dikirim sebagai tautan unduhan
     * melalui /send.
     *
     * @return array{ok: bool, message: string, message_id: ?string, recipient: string, attachment: array{name: string, mime: ?string, size: int}, attachment_url?: string, via?: string}
     */
    public function sendMedia(string $recipient, string $message, UploadedFile $attachment, ?int $timeout = null): array
    {
        $recipient = $this->normalizePhone($recipient);

        $mime = (string) ($attachment->getMimeType() ?: $attachment->getClientMimeType());

        $file = [
            'name' => $attachment->getClientOriginalName(),
            'mime' => $mime,
            'size' => (int) $attachment->getSize(),
        ];

        $type = $this->mediaType($mime);
        $stored = $this->storeAttachment($attachment);

        if ($stored === null) {
            return [
                'ok' => false,
                'message' => 'Lampiran gagal disimpan di server. Periksa izin folder storage/app/public.',
                'message_id' => null,
                'recipient' => $recipient,
                'attachment' => $file,
            ];
        }

        // Gateway mengunduh lampiran lewat URL; hentikan lebih awal bila
        // alamatnya tidak mungkin dijangkau dari internet.
        $unreachable = $this->unreachableReason($stored['url']) ?? $this->probeAttachmentUrl($stored['url']);

        if ($unreachable !== null) {
            $this->deleteStored($stored);

            return [
                'ok' => false,
                'message' => $unreachable,
                'message_id' => null,
                'recipient' => $recipient,
                'attachment' => $file,
                'attachment_url' => $stored['url'],
            ];
        }

        $path = '/'.ltrim((string) config('whatsapp-gateway.media_path', '/send-media'), '/');

        $result = $this->request('post', $path, [
            'device_id' => $this->deviceId(),
            'recipient' => $recipient,
            'type' => $type,
            'url' => $stored['url'],
            'file_path' => $stored['absolute'],
            'caption' => $message,
            'filename' => $file['name'],
            'mimetype' => $file['mime'],
        ], $timeout);

        $messageId = $result['data']['messageId'] ?? $result['data']['message_id'] ?? null;

        if ($result['ok'] && ($result['data']['success'] ?? true)) {
            return [
                'ok' => true,
                'message' => 'Lampiran berhasil dikirim ke '.$recipient.'.',
                'message_id' => is_string($messageId) ? $messageId : null,
                'recipient' => $recipient,
                'attachment' => $file,
                'attachment_url' => $stored['url'],
                'via' => 'media',
            ];
        }

        // Gateway menolak type "document": kirim dokumen sebagai tautan.
        if ($type === 'document' && (bool) config('whatsapp-gateway.document_link_fallback', true)) {
            $link = $this->sendDocumentLink($recipient, $message, $stored, $file, $timeout);

            if ($link !== null) {
                return $link;
            }
        }

        return [
            'ok' => false,
            'message' => ($result['message'] ?? 'Lampiran gagal dikirim.').$this->attachmentHint((string) ($result['message'] ?? ''), $stored['url']),
            'message_id' => null,
            'recipient' => $recipient,
            'attachment' => $file,
            'attachment_url' => $stored['url'],
        ];
    }

    /**
     * Tentukan jenis media sesuai kontrak gateway (image|video|audio|document).
     */
    private function mediaType(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };
    }

    /**
     * Simpan lampiran pada disk publik agar bisa diakses gateway lewat URL.
     *
     * @return array{disk: string, path: string, url: string, absolute: string}|null
     */
    private function storeAttachment(UploadedFile $attachment): ?array
    {
        $diskName = (string) config('whatsapp-gateway.media_disk', 'public');
        $directory = trim((string) config('whatsapp-gateway.attachment_directory', 'whatsapp/attachments'), '/');

        try {
            $this->pruneAttachments($diskName, $directory);

            $path = (string) $attachment->store($directory, $diskName);
            $disk = Storage::disk($diskName);

            $absolute = '';

            try {
                // Hanya tersedia untuk driver lokal; dipakai bila gateway satu server.
                $absolute = (string) $disk->path($path);
            } catch (Throwable) {
                $absolute = '';
            }

            return [
                'disk' => $diskName,
                'path' => $path,
                'url' => $this->attachmentUrl($diskName, $path),
                'absolute' => $absolute,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * URL publik lampiran.
     *
     * Nilai WHATSAPP_GATEWAY_PUBLIC_URL dipakai lebih dulu agar lampiran tetap
     * dapat diunduh gateway ketika APP_URL masih menunjuk localhost.
     */
    private function attachmentUrl(string $diskName, string $path): string
    {
        $override = trim((string) config('whatsapp-gateway.attachment_public_url', ''));

        if ($override !== '') {
            return rtrim($override, '/').'/storage/'.ltrim($path, '/');
        }

        $url = (string) Storage::disk($diskName)->url($path);

        // Sebagian driver mengembalikan URL relatif; lengkapi dengan APP_URL.
        if (preg_match('~^https?://~i', $url) !== 1) {
            $url = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        return $url;
    }

    /**
     * Hapus berkas lampiran yang gagal dikirim.
     *
     * @param  array{disk: string, path: string, url: string, absolute: string}  $stored
     */
    private function deleteStored(array $stored): void
    {
        try {
            Storage::disk($stored['disk'])->delete($stored['path']);
        } catch (Throwable) {
            // Abaikan: berkas akan dibersihkan oleh housekeeping.
        }
    }

    /**
     * Alasan URL lampiran tidak dapat dijangkau gateway (null bila wajar).
     */
    private function unreachableReason(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return 'URL lampiran tidak valid ('.$url.'). Periksa konfigurasi APP_URL atau WHATSAPP_GATEWAY_PUBLIC_URL.';
        }

        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.localhost');

        $isPrivateIp = filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false && filter_var($host, FILTER_VALIDATE_IP) !== false;

        if (! $isLocalHost && ! $isPrivateIp) {
            return null;
        }

        return 'Lampiran tidak dapat dikirim karena URL-nya ('.$url.') hanya bisa diakses dari server ini. '
            .'Set APP_URL ke domain publik (mis. https://agenda.domain.go.id) atau isi '
            .'WHATSAPP_GATEWAY_PUBLIC_URL, lalu jalankan "php artisan storage:link".';
    }

    /**
     * Pastikan berkas benar-benar dapat diakses publik (deteksi storage:link
     * belum dijalankan). Kegagalan koneksi diabaikan agar tidak memblokir
     * pengiriman ketika server tidak bisa mengakses URL-nya sendiri.
     */
    private function probeAttachmentUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(5)->connectTimeout(3)->head($url);
        } catch (Throwable) {
            return null;
        }

        if (in_array($response->status(), [403, 404, 410], true)) {
            return 'Lampiran tidak dapat diakses publik (HTTP '.$response->status().'): '.$url.'. '
                .'Jalankan "php artisan storage:link" di server agar folder public/storage tersedia.';
        }

        return null;
    }

    /**
     * Hapus lampiran uji yang lebih lama dari satu hari (housekeeping).
     */
    private function pruneAttachments(string $diskName, string $directory): void
    {
        try {
            $disk = Storage::disk($diskName);
            $expired = now()->subDay()->getTimestamp();

            foreach ($disk->files($directory) as $file) {
                if ($disk->lastModified($file) < $expired) {
                    $disk->delete($file);
                }
            }
        } catch (Throwable) {
            // Housekeeping bersifat opsional; abaikan bila disk tidak mendukung.
        }
    }

    /**
     * Kirim dokumen sebagai pesan teks berisi tautan unduhan.
     *
     * @param  array{disk: string, path: string, url: string, absolute: string}  $stored
     * @param  array{name: string, mime: ?string, size: int}  $file
     * @return array{ok: bool, message: string, message_id: ?string, recipient: string, attachment: array{name: string, mime: ?string, size: int}, attachment_url: string, via: string}|null
     */
    private function sendDocumentLink(string $recipient, string $message, array $stored, array $file, ?int $timeout): ?array
    {
        if ($stored['url'] === '') {
            return null;
        }

        $lines = array_values(array_filter([
            trim($message),
            'Lampiran: '.$file['name'],
            $stored['url'],
        ], static fn (string $line): bool => $line !== ''));

        $result = $this->send($recipient, implode("\n", $lines), $timeout);

        if (! $result['ok']) {
            return null;
        }

        return [
            'ok' => true,
            'message' => 'Gateway hanya mendukung gambar/video/audio, jadi dokumen dikirim sebagai tautan unduhan.',
            'message_id' => $result['message_id'],
            'recipient' => $recipient,
            'attachment' => $file,
            'attachment_url' => $stored['url'],
            'via' => 'link',
        ];
    }

    /**
     * Petunjuk tambahan saat lampiran gagal diunduh gateway.
     *
     * Pesan seperti "Request failed with status code 404" berasal dari axios
     * milik gateway ketika URL lampiran tidak dapat diakses, sehingga perlu
     * diterjemahkan menjadi langkah perbaikan yang jelas.
     */
    private function attachmentHint(string $error, string $url): string
    {
        $downloadIssue = preg_match(
            '/\b(403|404|410)\b|request failed|failed to fetch|enotfound|econnrefused|etimedout|socket hang up/i',
            $error,
        ) === 1;

        if (! $downloadIssue) {
            return '';
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $base = ' Gateway gagal mengunduh lampiran dari '.$url.'.';

        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return $base.' URL masih memakai "'.$host.'" sehingga tidak dapat diakses gateway. '
                .'Set APP_URL ke domain publik atau isi WHATSAPP_GATEWAY_PUBLIC_URL, lalu jalankan "php artisan storage:link".';
        }

        return $base.' Pastikan "php artisan storage:link" sudah dijalankan dan berkas dapat diakses publik.';
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
