<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WhatsappGatewayService;
use App\Services\WhatsappNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class WhatsappGatewayController extends Controller
{
    public function __construct(
        private readonly WhatsappGatewayService $gateway,
        private readonly WhatsappNotifier $notifier,
    ) {}

    /**
     * Halaman koneksi: QR scan, simpan sesi, dan pengaturan gateway.
     */
    public function index(): View
    {
        $recipients = $this->notifier->recipients();

        return view('whatsapp.index', [
            'gatewayUrl' => $this->gateway->configuredUrl(),
            'defaultUrl' => $this->gateway->defaultUrl(),
            'baseUrl' => $this->gateway->baseUrl(),
            'deviceId' => $this->gateway->deviceId(),
            'session' => $this->gateway->savedSession(),
            'pollInterval' => max(2000, (int) config('whatsapp-gateway.poll_interval', 5000)),
            'notificationRecipients' => $recipients['admins'],
            'gatewayPhone' => $recipients['sender_phone'],
            'notificationsEnabled' => $this->notifier->enabled(),
        ]);
    }

    /**
     * Simpan URL gateway & device ID.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateway_url' => ['required', 'string', 'max:255', 'regex:~^https?://[^\s]+$~i'],
            'device_id' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
        ], [
            'gateway_url.required' => 'URL gateway wajib diisi.',
            'gateway_url.regex' => 'URL gateway harus diawali http:// atau https://.',
            'device_id.required' => 'Device ID wajib diisi.',
            'device_id.regex' => 'Device ID hanya boleh berisi huruf, angka, titik, garis bawah, dan strip.',
        ], [
            'gateway_url' => 'URL gateway',
            'device_id' => 'device ID',
        ]);

        $previousDevice = $this->gateway->deviceId();

        $this->gateway->saveSettings($data['gateway_url'], $data['device_id']);

        // Sesi lama tidak relevan lagi bila device ID diganti.
        if ($previousDevice !== $this->gateway->deviceId()) {
            $this->gateway->forgetSession();
        }

        return response()->json([
            'ok' => true,
            'message' => 'Pengaturan gateway berhasil disimpan.',
            'gateway_url' => $this->gateway->configuredUrl(),
            'base_url' => $this->gateway->baseUrl(),
            'device_id' => $this->gateway->deviceId(),
            'session' => $this->gateway->savedSession(),
        ]);
    }

    /**
     * Proxy QR dari gateway (dipanggil berkala oleh halaman).
     */
    public function qr(Request $request): JsonResponse
    {
        $result = $this->gateway->qr($request->boolean('force'));

        // Sesi otomatis dicatat begitu perangkat terhubung.
        if ($result['status'] === 'connected') {
            $result['session'] = $this->gateway->saveSession($result['phone_number']);
        }

        return response()->json($result);
    }

    /**
     * Proxy status koneksi dari gateway.
     */
    public function status(): JsonResponse
    {
        $result = $this->gateway->status();

        if ($result['status'] === 'connected') {
            $result['session'] = $this->gateway->saveSession($result['phone_number']);
        }

        return response()->json($result);
    }

    /**
     * Simpan sesi perangkat aktif ke pengaturan aplikasi.
     */
    public function saveSession(): JsonResponse
    {
        $status = $this->gateway->status();
        $phone = $status['status'] === 'connected' ? $status['phone_number'] : null;

        $session = $this->gateway->saveSession($phone);

        return response()->json([
            'ok' => true,
            'message' => filled($phone)
                ? "Sesi perangkat +{$phone} berhasil disimpan."
                : 'Sesi disimpan. Hubungkan perangkat agar nomor WhatsApp ikut tersimpan.',
            'session' => $session,
            'connection' => $status,
        ]);
    }

    /**
     * Putuskan koneksi & hapus sesi gateway.
     */
    public function disconnect(): JsonResponse
    {
        $result = $this->gateway->disconnect();

        if ($result['ok']) {
            $this->gateway->forgetSession();
        }

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'session' => $this->gateway->savedSession(),
        ]);
    }

    /**
     * Halaman uji kirim pesan WhatsApp.
     */
    public function test(): View
    {
        $session = $this->gateway->savedSession();

        $users = User::query()
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'kode_bidang']);

        return view('whatsapp.test', [
            'baseUrl' => $this->gateway->baseUrl(),
            'deviceId' => $this->gateway->deviceId(),
            'session' => $session,
            'users' => $users,
            'prefillRecipient' => old('recipient', $session['phone_number']),
            'maxAttachmentKb' => $this->attachmentMaxKb(),
            'attachmentWarning' => $this->attachmentWarning(),
        ]);
    }

    /**
     * Peringatan bila URL lampiran tidak dapat diunduh gateway.
     */
    private function attachmentWarning(): ?string
    {
        $override = trim((string) config('whatsapp-gateway.attachment_public_url', ''));
        $base = $override !== '' ? $override : (string) config('app.url');
        $host = strtolower((string) parse_url($base, PHP_URL_HOST));

        if (! in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return null;
        }

        return 'URL lampiran masih memakai "'.$host.'" sehingga gateway tidak dapat mengunduhnya '
            .'(gejala: "Request failed with status code 404"). Set APP_URL ke domain publik atau isi '
            .'WHATSAPP_GATEWAY_PUBLIC_URL, lalu jalankan "php artisan storage:link".';
    }

    /**
     * Batas ukuran lampiran (KB) dari konfigurasi.
     */
    private function attachmentMaxKb(): int
    {
        return max(1024, (int) config('whatsapp-gateway.attachment_max_kb', 8192));
    }

    /**
     * Kirim pesan uji melalui gateway.
     *
     * Lampiran bersifat opsional; bila dikirim, pesan diteruskan sebagai
     * multipart/form-data ke endpoint media gateway.
     */
    public function sendTest(Request $request): JsonResponse
    {
        $maxKb = $this->attachmentMaxKb();

        $data = $request->validate([
            'recipient' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{6,}$/'],
            'message' => ['nullable', 'string', 'max:4096', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:'.$maxKb, 'mimes:pdf,jpg,jpeg,png,webp'],
        ], [
            'recipient.required' => 'Nomor tujuan wajib diisi.',
            'recipient.regex' => 'Nomor tujuan tidak valid. Gunakan format 08xx atau 62xx.',
            'message.required_without' => 'Isi pesan atau lampiran wajib diisi.',
            'message.max' => 'Pesan maksimal 4096 karakter.',
            'attachment.file' => 'Lampiran gagal diunggah. Silakan pilih ulang berkas.',
            'attachment.max' => 'Ukuran lampiran maksimal '.intdiv($maxKb, 1024).' MB.',
            'attachment.mimes' => 'Lampiran harus berupa PDF atau gambar (JPG, PNG, WEBP).',
        ], [
            'recipient' => 'nomor tujuan',
            'message' => 'pesan',
            'attachment' => 'lampiran',
        ]);

        $attachment = $request->file('attachment');
        $message = (string) ($data['message'] ?? '');

        $result = $attachment instanceof UploadedFile
            ? $this->gateway->sendMedia($data['recipient'], $message, $attachment)
            : $this->gateway->send($data['recipient'], $message);

        return response()->json($result);
    }
}
