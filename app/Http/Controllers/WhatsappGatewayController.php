<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WhatsappGatewayService;
use App\Services\WhatsappNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    /**
     * Kirim pesan uji melalui gateway.
     */
    public function sendTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{6,}$/'],
            'message' => ['required', 'string', 'max:4096'],
        ], [
            'recipient.required' => 'Nomor tujuan wajib diisi.',
            'recipient.regex' => 'Nomor tujuan tidak valid. Gunakan format 08xx atau 62xx.',
            'message.required' => 'Isi pesan wajib diisi.',
            'message.max' => 'Pesan maksimal 4096 karakter.',
        ], [
            'recipient' => 'nomor tujuan',
            'message' => 'pesan',
        ]);

        return response()->json(
            $this->gateway->send($data['recipient'], $data['message']),
        );
    }
}
