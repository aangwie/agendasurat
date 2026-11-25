<?php

namespace App\Services;

use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\TaskLetter;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi WhatsApp untuk administrator.
 *
 * Dipakai saat surat keluar atau surat tugas baru dicatat. Nomor administrator
 * yang sama dengan nomor WhatsApp gateway (perangkat pengirim) dilewati agar
 * notifikasi tidak dikirim ke perangkat sendiri.
 */
class WhatsappNotifier
{
    public function __construct(private readonly WhatsappGatewayService $gateway) {}

    /**
     * Apakah notifikasi surat diaktifkan pada konfigurasi?
     */
    public function enabled(): bool
    {
        return (bool) config('whatsapp-gateway.notify_letters', true);
    }

    /**
     * Nomor WhatsApp gateway yang tersimpan (tanpa memanggil jaringan).
     */
    public function gatewayPhone(): ?string
    {
        $phone = $this->gateway->savedSession()['phone_number'];

        return filled($phone) ? $this->gateway->normalizePhone((string) $phone) : null;
    }

    /**
     * Normalisasi nomor HP ke format gateway (62...).
     */
    public function normalizePhone(string $phone): string
    {
        return $this->gateway->normalizePhone($phone);
    }

    /**
     * Daftar administrator beserta status penerima notifikasi.
     *
     * @return array{sender_phone: ?string, admins: Collection<int, array{user: User, phone: ?string, is_gateway: bool, can_receive: bool}>}
     */
    public function recipients(?string $senderPhone = null): array
    {
        $sender = $senderPhone ?? $this->gatewayPhone();

        $admins = User::query()
            ->where('is_admin', true)
            ->orderBy('name')
            ->get()
            ->map(function (User $admin) use ($sender): array {
                $phone = filled($admin->phone)
                    ? $this->gateway->normalizePhone((string) $admin->phone)
                    : null;

                $isGateway = $phone !== null && $sender !== null && $phone === $sender;

                return [
                    'user' => $admin,
                    'phone' => $phone,
                    'is_gateway' => $isGateway,
                    'can_receive' => $phone !== null && ! $isGateway,
                ];
            });

        return ['sender_phone' => $sender, 'admins' => $admins];
    }

    /**
     * Kirim notifikasi surat keluar baru ke seluruh administrator.
     *
     * @return array{ok: bool, sent: int, skipped: int, failed: int, message: string}
     */
    public function notifyOutgoingLetter(OutgoingLetter $letter): array
    {
        return $this->notifyAdmins($this->outgoingLetterMessage($letter));
    }

    /**
     * Kirim notifikasi surat tugas baru ke seluruh administrator.
     *
     * @return array{ok: bool, sent: int, skipped: int, failed: int, message: string}
     */
    public function notifyTaskLetter(TaskLetter $letter): array
    {
        return $this->notifyAdmins($this->taskLetterMessage($letter));
    }

    /**
     * Isi pesan notifikasi surat keluar.
     */
    public function outgoingLetterMessage(OutgoingLetter $letter): string
    {
        return $this->letterMessage('Surat Keluar Baru', $letter);
    }

    /**
     * Isi pesan notifikasi surat tugas.
     */
    public function taskLetterMessage(TaskLetter $letter): string
    {
        return $this->letterMessage('Surat Tugas Baru', $letter);
    }

    /**
     * Daftar bidang (dari akun pengguna) yang dapat menerima disposisi.
     *
     * Satu bidang diwakili satu akun; bila ada beberapa akun pada bidang yang
     * sama, akun yang memiliki nomor WhatsApp dipilih lebih dulu.
     *
     * @return Collection<int, array{kode_bidang: string, name: string, user: User, phone: ?string}>
     */
    public function bidangRecipients(): Collection
    {
        return User::query()
            ->whereNotNull('kode_bidang')
            ->where('kode_bidang', '<>', '')
            ->orderBy('kode_bidang')
            ->orderBy('name')
            ->get()
            ->groupBy('kode_bidang')
            ->map(function (Collection $users, string $kodeBidang): array {
                /** @var User $user */
                $user = $users->first(fn (User $candidate): bool => filled($candidate->phone)) ?? $users->first();

                return [
                    'kode_bidang' => (string) $kodeBidang,
                    'name' => (string) $user->name,
                    'alias' => $user->alias,
                    'label' => $user->bidangLabel(),
                    'user' => $user,
                    'phone' => filled($user->phone) ? $this->gateway->normalizePhone((string) $user->phone) : null,
                ];
            })
            ->values();
    }

    /**
     * Kirim notifikasi disposisi surat masuk ke bidang yang dipilih.
     *
     * @param  Collection<int, array{kode_bidang: string, name: string, user: User, phone: ?string}>  $targets
     * @return array{ok: bool, results: array<int, array{kode_bidang: string, name: string, user_id: int, phone: ?string, status: string, message: string}>}
     */
    public function notifyDisposition(IncomingLetter $letter, Collection $targets, string $senderName): array
    {
        $message = $this->dispositionMessage($letter, $senderName);
        $timeout = max(3, (int) config('whatsapp-gateway.notify_timeout', 5));

        // Nomor gateway tidak perlu dikirimi disposisi (perangkat sendiri).
        $gatewayPhone = $this->gatewayPhone();
        $health = null;
        $results = [];

        foreach ($targets as $target) {
            $phone = $target['phone'];

            if ($phone === null) {
                $results[] = $this->dispositionResult($target, 'Bidang belum memiliki nomor WhatsApp.', 'skipped');

                continue;
            }

            if ($phone === $gatewayPhone) {
                $results[] = $this->dispositionResult($target, 'Nomor bidang sama dengan nomor gateway.', 'skipped');

                continue;
            }

            // Cek kesehatan gateway sekali saja sebelum pengiriman pertama.
            $health ??= $this->gateway->health($timeout);

            if (! $health['ok']) {
                $results[] = $this->dispositionResult($target, 'Gateway WhatsApp tidak dapat dihubungi.', 'failed');

                continue;
            }

            try {
                $result = $this->gateway->send($phone, $message, $timeout);
            } catch (Throwable $exception) {
                $result = ['ok' => false, 'message' => $exception->getMessage()];
            }

            $results[] = $result['ok']
                ? $this->dispositionResult($target, 'Notifikasi disposisi terkirim.', 'sent')
                : $this->dispositionResult($target, (string) ($result['message'] ?? 'Gagal dikirim.'), 'failed');
        }

        if (collect($results)->contains(fn (array $row): bool => $row['status'] === 'failed')) {
            Log::warning('Sebagian notifikasi disposisi surat masuk gagal dikirim.', [
                'letter_id' => $letter->id,
                'results' => $results,
            ]);
        }

        return [
            'ok' => collect($results)->contains(fn (array $row): bool => $row['status'] === 'sent'),
            'results' => $results,
        ];
    }

    /**
     * Susun isi pesan disposisi surat masuk beserta tautan unduhan.
     */
    public function dispositionMessage(IncomingLetter $letter, string $senderName): string
    {
        $lines = [
            '*Disposisi Surat Masuk*',
            '',
            '*Nomor Agenda:* '.$letter->agenda_number,
            '*Nomor Surat:* '.$letter->letter_number,
            '*Asal Surat:* '.$letter->sender,
            '*Perihal:* '.$letter->subject,
            '*Tanggal Surat:* '.($letter->letter_date?->locale('id')->translatedFormat('j F Y') ?? '—'),
            '*Diterima:* '.($letter->received_date?->locale('id')->translatedFormat('j F Y') ?? '—'),
        ];

        $sifat = $letter->sifatLabels();

        if ($sifat !== []) {
            $lines[] = '*Sifat:* '.implode(', ', $sifat);
        }

        $lines[] = '';
        $lines[] = $letter->hasAttachment()
            ? 'Silakan unduh lampiran surat melalui tautan berikut:'
            : 'Silakan buka detail surat melalui tautan berikut:';
        $lines[] = $this->publicUrl($letter);
        $lines[] = '';
        $lines[] = 'Didisposisikan oleh '.$senderName.'.';
        $lines[] = (string) config('app.name', 'Agenda Surat');

        return implode("\n", $lines);
    }

    /**
     * Tautan publik surat (lampiran bila ada, jika tidak halaman cetak).
     *
     * Nilai WHATSAPP_GATEWAY_PUBLIC_URL dipakai lebih dulu agar tautan tetap
     * dapat dibuka penerima ketika APP_URL masih menunjuk localhost.
     */
    public function publicUrl(IncomingLetter $letter): string
    {
        $route = $letter->hasAttachment() ? 'surat-masuk.lampiran' : 'surat-masuk.cetak';
        $url = route($route, ['incomingLetter' => $letter]);

        $override = trim((string) config('whatsapp-gateway.attachment_public_url', ''));

        if ($override === '') {
            return $url;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $query = parse_url($url, PHP_URL_QUERY);

        return rtrim($override, '/').$path.($query ? '?'.$query : '');
    }

    /**
     * @param  array{kode_bidang: string, name: string, user: User, phone: ?string}  $target
     * @return array{kode_bidang: string, name: string, user_id: int, phone: ?string, status: string, message: string}
     */
    private function dispositionResult(array $target, string $message, string $status): array
    {
        return [
            'kode_bidang' => $target['kode_bidang'],
            'name' => $target['name'],
            'user_id' => (int) $target['user']->id,
            'phone' => $target['phone'],
            'status' => $status,
            'message' => $message,
        ];
    }

    /**
     * Kirim pesan ke seluruh administrator yang berhak menerima.
     *
     * @return array{ok: bool, sent: int, skipped: int, failed: int, message: string}
     */
    public function notifyAdmins(string $message): array
    {
        try {
            return $this->dispatchToAdmins($message);
        } catch (Throwable $exception) {
            Log::error('Notifikasi WhatsApp gagal diproses.', [
                'error' => $exception->getMessage(),
            ]);

            return $this->result(false, 0, 0, 0, 'Notifikasi WhatsApp gagal diproses.');
        }
    }

    /**
     * @return array{ok: bool, sent: int, skipped: int, failed: int, message: string}
     */
    private function dispatchToAdmins(string $message): array
    {
        if (! $this->enabled()) {
            return $this->result(false, 0, 0, 0, 'Notifikasi WhatsApp dinonaktifkan.');
        }

        // Nomor gateway hanya dibaca dari sesi tersimpan (tanpa permintaan HTTP
        // tambahan) agar pencatatan surat tetap cepat.
        $sender = $this->gatewayPhone();
        $timeout = max(3, (int) config('whatsapp-gateway.notify_timeout', 5));

        $admins = $this->recipients($sender)['admins'];
        $targets = $admins->where('can_receive', true)->values();
        $skipped = $admins->count() - $targets->count();

        if ($targets->isEmpty()) {
            return $this->result(
                false,
                0,
                $skipped,
                0,
                'Notifikasi WhatsApp tidak dikirim karena belum ada administrator dengan nomor yang bisa dihubungi.',
            );
        }

        $health = $this->gateway->health($timeout);

        if (! $health['ok']) {
            return $this->result(
                false,
                0,
                $skipped,
                0,
                'Notifikasi WhatsApp tidak dikirim karena gateway tidak dapat dihubungi.',
            );
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($targets as $target) {
            try {
                $result = $this->gateway->send((string) $target['phone'], $message, $timeout);
            } catch (Throwable $exception) {
                $result = ['ok' => false, 'message' => $exception->getMessage()];
            }

            if ($result['ok']) {
                $sent++;

                continue;
            }

            $failed++;
            $errors[] = $target['user']->name.': '.($result['message'] ?? 'gagal dikirim');
        }

        if ($errors !== []) {
            Log::warning('Sebagian notifikasi WhatsApp surat gagal dikirim.', [
                'errors' => $errors,
                'skipped' => $skipped,
            ]);
        }

        return $this->result($sent > 0, $sent, $skipped, $failed, $this->summary($sent, $skipped, $failed));
    }

    /**
     * Ringkasan hasil pengiriman untuk pesan flash.
     */
    private function summary(int $sent, int $skipped, int $failed): string
    {
        $parts = [];

        if ($sent > 0) {
            $parts[] = "Notifikasi WhatsApp terkirim ke {$sent} admin.";
        }

        if ($failed > 0) {
            $parts[] = "{$failed} notifikasi gagal dikirim.";
        }

        if ($sent === 0 && $failed > 0) {
            array_unshift($parts, 'Notifikasi WhatsApp tidak terkirim.');
        }

        if ($skipped > 0) {
            $parts[] = "{$skipped} admin dilewati (tanpa nomor atau memakai nomor gateway).";
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{ok: bool, sent: int, skipped: int, failed: int, message: string}
     */
    private function result(bool $ok, int $sent, int $skipped, int $failed, string $message): array
    {
        return [
            'ok' => $ok,
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => $message,
        ];
    }

    /**
     * Susun isi pesan notifikasi surat.
     */
    private function letterMessage(string $heading, OutgoingLetter|TaskLetter $letter): string
    {
        $lines = [
            '*'.$heading.'*',
            '',
            '*Nomor:* '.$letter->fullCode(),
            '*Perihal:* '.$letter->subject,
            '*Tanggal:* '.$letter->letterDateLabel(),
            '*Bidang:* '.($letter->bidang ?: '—'),
            '*Dicatat oleh:* '.($letter->user?->name ?? '—'),
            '',
            'Dikirim '.now()->locale('id')->translatedFormat('j F Y, H:i').' WIB.',
            (string) config('app.name', 'Agenda Surat'),
        ];

        return implode("\n", $lines);
    }
}
