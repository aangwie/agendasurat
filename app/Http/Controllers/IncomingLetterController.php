<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomingLetterRequest;
use App\Models\IncomingLetter;
use App\Models\InstitutionProfile;
use App\Models\LetterDisposition;
use App\Services\WhatsappNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomingLetterController extends Controller
{
    /**
     * Pilihan jumlah baris per halaman (slice data), terkecil 10.
     *
     * @var list<int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    private const DEFAULT_PER_PAGE = 10;

    public function __construct(private readonly WhatsappNotifier $notifier) {}

    /**
     * Display the incoming letters datatable.
     */
    public function index(Request $request): View|Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $perPage = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $letters = IncomingLetter::query()
            ->with('dispositions.user')
            ->search($search)
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $data = [
            'letters' => $letters,
            'search' => $search,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'totalLetters' => IncomingLetter::query()->count(),
            'monthLetters' => IncomingLetter::query()
                ->whereBetween('received_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'suggestedAgendaNumber' => IncomingLetter::suggestAgendaNumber(),
            // Akun bidang lain hanya boleh membaca (tombol tambah/edit/hapus disembunyikan).
            'canManage' => $request->user()->canManageIncomingLetters(),
            'openForm' => session('open_form'),
            'openFormId' => session('open_form_id'),
        ];

        // Permintaan AJAX (datatable) hanya mengembalikan partial tabel.
        if ($request->ajax()) {
            return response()->view('surat-masuk._results', $data);
        }

        return view('surat-masuk.index', $data);
    }

    /**
     * Store a newly created incoming letter.
     */
    public function store(IncomingLetterRequest $request): RedirectResponse
    {
        $letter = IncomingLetter::create($request->validated());

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', "Surat masuk {$letter->agenda_number} berhasil ditambahkan.");
    }

    /**
     * Update the given incoming letter.
     */
    public function update(IncomingLetterRequest $request, IncomingLetter $incomingLetter): RedirectResponse
    {
        $incomingLetter->update($request->validated());

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', "Surat masuk {$incomingLetter->agenda_number} berhasil diperbarui.");
    }

    /**
     * Remove the given incoming letter.
     */
    public function destroy(IncomingLetter $incomingLetter): RedirectResponse
    {
        $agendaNumber = $incomingLetter->agenda_number;

        $incomingLetter->deleteAttachment();
        $incomingLetter->delete();

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', "Surat masuk {$agendaNumber} berhasil dihapus.");
    }

    /**
     * Unduh lampiran surat masuk (disk privat, wajib login).
     */
    public function attachment(IncomingLetter $incomingLetter): StreamedResponse
    {
        abort_unless($incomingLetter->hasAttachment(), 404);

        $disk = Storage::disk(IncomingLetter::ATTACHMENT_DISK);
        $path = (string) $incomingLetter->attachment_path;

        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $incomingLetter->attachmentLabel());
    }

    /**
     * Unggah/ganti lampiran surat dari popup disposisi.
     *
     * Lampiran diunggah saat mendisposisikan surat (bukan saat mencatat surat),
     * agar tautan unduhan pada notifikasi WhatsApp selalu tersedia.
     */
    public function uploadAttachment(Request $request, IncomingLetter $incomingLetter): JsonResponse
    {
        $request->validate([
            'attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'attachment.required' => 'Pilih berkas lampiran terlebih dahulu.',
            'attachment.file' => 'Lampiran gagal diunggah. Silakan pilih ulang berkas.',
            'attachment.mimes' => 'Lampiran harus berupa PDF atau gambar (JPG, PNG, WEBP).',
            'attachment.max' => 'Ukuran lampiran maksimal 10 MB.',
        ], [
            'attachment' => 'lampiran',
        ]);

        $file = $request->file('attachment');
        abort_if($file === null, 422);

        // Lampiran lama diganti: hapus berkas sebelumnya agar tidak menumpuk.
        $incomingLetter->deleteAttachment();

        $incomingLetter->update([
            'attachment_path' => $file->store('incoming-letters', IncomingLetter::ATTACHMENT_DISK),
            'attachment_name' => $file->getClientOriginalName(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Lampiran surat berhasil diunggah.',
            'attachment' => [
                'available' => true,
                'name' => $incomingLetter->attachmentLabel(),
                'url' => route('surat-masuk.lampiran', $incomingLetter),
            ],
        ]);
    }

    /**
     * Hapus lampiran surat dari popup disposisi.
     */
    public function removeAttachment(IncomingLetter $incomingLetter): JsonResponse
    {
        $incomingLetter->deleteAttachment();
        $incomingLetter->update(['attachment_path' => null, 'attachment_name' => null]);

        return response()->json([
            'ok' => true,
            'message' => 'Lampiran surat dihapus.',
            'attachment' => [
                'available' => false,
                'name' => null,
                'url' => null,
            ],
        ]);
    }

    /**
     * Data popup disposisi: daftar bidang, lampiran, dan riwayat terakhir.
     */
    public function disposition(IncomingLetter $incomingLetter): JsonResponse
    {
        $history = $incomingLetter->dispositions()
            ->orderByDesc('id')
            ->get()
            ->unique('kode_bidang')
            ->reject(fn (LetterDisposition $disposition): bool => ! $disposition->isActive())
            ->keyBy('kode_bidang');

        $bidangs = $this->notifier->bidangRecipients()
            ->map(function (array $recipient) use ($history): array {
                /** @var LetterDisposition|null $last */
                $last = $history->get($recipient['kode_bidang']);

                return [
                    'kode_bidang' => $recipient['kode_bidang'],
                    'name' => $recipient['name'],
                    'alias' => $recipient['alias'] ?? null,
                    'label' => $recipient['label'] ?? $recipient['name'],
                    'phone' => $recipient['phone'],
                    'can_receive' => $recipient['phone'] !== null,
                    'disposition_id' => $last?->id,
                    'last_status' => $last?->status,
                    'last_status_label' => $last?->statusLabel(),
                    'last_sent_at' => $last?->sent_at?->locale('id')->translatedFormat('j M Y, H:i'),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'letter' => [
                'id' => $incomingLetter->id,
                'agenda_number' => $incomingLetter->agenda_number,
                'letter_number' => $incomingLetter->letter_number,
                'sender' => $incomingLetter->sender,
                'subject' => $incomingLetter->subject,
                'letter_date' => $incomingLetter->letter_date?->locale('id')->translatedFormat('j F Y'),
                'received_date' => $incomingLetter->received_date?->locale('id')->translatedFormat('j F Y'),
                'sifat' => $incomingLetter->sifatLabels(),
            ],
            'attachment' => [
                'available' => $incomingLetter->hasAttachment(),
                'name' => $incomingLetter->attachmentLabel(),
                'url' => route('surat-masuk.lampiran', $incomingLetter),
                'upload_url' => route('surat-masuk.lampiran.unggah', $incomingLetter),
                'delete_url' => route('surat-masuk.lampiran.hapus', $incomingLetter),
            ],
            'bidangs' => $bidangs,
            'send_url' => route('surat-masuk.disposisi.kirim', $incomingLetter),
            'cancel_url' => route('surat-masuk.disposisi.batal', $incomingLetter),
        ]);
    }

    /**
     * Kirim disposisi surat masuk ke bidang yang dicentang via WhatsApp.
     */
    public function sendDisposition(Request $request, IncomingLetter $incomingLetter): JsonResponse
    {
        $data = $request->validate([
            'bidangs' => ['required', 'array', 'min:1'],
            'bidangs.*' => ['string', 'max:30'],
        ], [
            'bidangs.required' => 'Pilih minimal satu bidang tujuan disposisi.',
            'bidangs.min' => 'Pilih minimal satu bidang tujuan disposisi.',
        ], [
            'bidangs' => 'bidang tujuan',
        ]);

        $selected = array_values(array_unique($data['bidangs']));

        $targets = $this->notifier->bidangRecipients()
            ->filter(fn (array $recipient): bool => in_array($recipient['kode_bidang'], $selected, true))
            ->values();

        if ($targets->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'Bidang yang dipilih tidak ditemukan.',
            ], 422);
        }

        $user = $request->user();
        $result = $this->notifier->notifyDisposition($incomingLetter, $targets, (string) $user?->name);

        $stored = [];

        foreach ($result['results'] as $row) {
            $record = LetterDisposition::create([
                'incoming_letter_id' => $incomingLetter->id,
                'kode_bidang' => $row['kode_bidang'],
                'bidang_name' => $row['name'],
                'user_id' => $row['user_id'],
                'phone' => $row['phone'],
                'status' => $row['status'],
                'message' => $row['message'],
                'disposed_by' => $user?->id,
                'sent_at' => $row['status'] === LetterDisposition::STATUS_SENT ? now() : null,
            ]);

            $row['disposition_id'] = $record->id;
            $row['sent_at'] = $record->sent_at?->locale('id')->translatedFormat('j M Y, H:i');
            $stored[] = $row;
        }

        $sent = collect($result['results'])->where('status', LetterDisposition::STATUS_SENT)->count();
        $failed = collect($result['results'])->where('status', LetterDisposition::STATUS_FAILED)->count();
        $skipped = collect($result['results'])->where('status', LetterDisposition::STATUS_SKIPPED)->count();

        return response()->json([
            'ok' => $sent > 0,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => $this->dispositionSummary($sent, $failed, $skipped),
            'results' => $stored,
        ]);
    }

    /**
     * Batalkan disposisi surat masuk pada satu bidang (mis. salah tujuan).
     */
    public function cancelDisposition(Request $request, IncomingLetter $incomingLetter): JsonResponse
    {
        $data = $request->validate([
            'kode_bidang' => ['required', 'string', 'max:30'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notify' => ['nullable', 'boolean'],
        ], [
            'kode_bidang.required' => 'Pilih bidang yang disposisinya akan dibatalkan.',
            'reason.max' => 'Alasan pembatalan maksimal 255 karakter.',
        ], [
            'kode_bidang' => 'bidang',
            'reason' => 'alasan pembatalan',
        ]);

        $disposition = $incomingLetter->dispositions()
            ->where('kode_bidang', $data['kode_bidang'])
            ->whereIn('status', LetterDisposition::ACTIVE_STATUSES)
            ->orderByDesc('id')
            ->first();

        if ($disposition === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Tidak ada disposisi aktif untuk bidang tersebut.',
            ], 422);
        }

        $user = $request->user();
        $reason = trim((string) ($data['reason'] ?? ''));

        // Pembatalan dicatat sebagai baris riwayat baru agar jejak disposisi
        // sebelumnya tetap utuh (tidak menghapus data).
        $cancelled = LetterDisposition::create([
            'incoming_letter_id' => $incomingLetter->id,
            'kode_bidang' => $disposition->kode_bidang,
            'bidang_name' => $disposition->bidang_name,
            'user_id' => $disposition->user_id,
            'phone' => $disposition->phone,
            'status' => LetterDisposition::STATUS_CANCELLED,
            'message' => $reason !== '' ? $reason : 'Dibatalkan oleh '.$user?->name.'.',
            'disposed_by' => $user?->id,
            'cancelled_at' => now(),
            'cancel_reason' => $reason !== '' ? $reason : null,
        ]);

        // Pemberitahuan WhatsApp bersifat opsional dan tidak menghalangi
        // pembatalan bila gateway bermasalah.
        $notification = null;

        if ($request->boolean('notify')) {
            $target = $this->notifier->bidangRecipients()
                ->firstWhere('kode_bidang', $disposition->kode_bidang);

            if (is_array($target)) {
                $notification = $this->notifier->notifyDispositionCancelled(
                    $incomingLetter,
                    $target,
                    $reason,
                    (string) $user?->name,
                );
            }
        }

        $label = $disposition->bidang_name;

        return response()->json([
            'ok' => true,
            'message' => 'Disposisi ke '.$label.' dibatalkan.'
                .($notification !== null ? ' '.$notification['message'] : ''),
            'notification' => $notification,
            'cancelled' => [
                'id' => $cancelled->id,
                'kode_bidang' => $cancelled->kode_bidang,
                'bidang_name' => $cancelled->bidang_name,
                'reason' => $cancelled->cancel_reason,
                'cancelled_at' => $cancelled->cancelled_at?->locale('id')->translatedFormat('j M Y, H:i'),
            ],
        ]);
    }

    /**
     * Ringkasan hasil pengiriman disposisi.
     */
    private function dispositionSummary(int $sent, int $failed, int $skipped): string
    {
        $parts = [];

        if ($sent > 0) {
            $parts[] = "Disposisi terkirim ke {$sent} bidang.";
        }

        if ($failed > 0) {
            $parts[] = "{$failed} bidang gagal dikirimi notifikasi.";
        }

        if ($skipped > 0) {
            $parts[] = "{$skipped} bidang dilewati (tanpa nomor WhatsApp atau memakai nomor gateway).";
        }

        if ($parts === []) {
            return 'Disposisi tidak terkirim.';
        }

        return implode(' ', $parts);
    }

    /**
     * Show the printable agenda sheet for the given letter.
     */
    public function print(IncomingLetter $incomingLetter): View
    {
        return view('surat-masuk.cetak', [
            'letter' => $incomingLetter,
            'profile' => InstitutionProfile::current(),
        ]);
    }
}
