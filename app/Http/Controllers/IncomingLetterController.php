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
        $letter = IncomingLetter::create($this->letterData($request));

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', "Surat masuk {$letter->agenda_number} berhasil ditambahkan.");
    }

    /**
     * Update the given incoming letter.
     */
    public function update(IncomingLetterRequest $request, IncomingLetter $incomingLetter): RedirectResponse
    {
        $incomingLetter->update($this->letterData($request, $incomingLetter));

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', "Surat masuk {$incomingLetter->agenda_number} berhasil diperbarui.");
    }

    /**
     * Data surat yang siap disimpan, termasuk lampiran bila diunggah.
     *
     * @return array<string, mixed>
     */
    private function letterData(IncomingLetterRequest $request, ?IncomingLetter $letter = null): array
    {
        $data = $request->safe()->except('attachment');

        $file = $request->file('attachment');

        if ($file === null) {
            return $data;
        }

        // Lampiran lama diganti: hapus berkas sebelumnya agar tidak menumpuk.
        $letter?->deleteAttachment();

        $data['attachment_path'] = $file->store('incoming-letters', IncomingLetter::ATTACHMENT_DISK);
        $data['attachment_name'] = $file->getClientOriginalName();

        return $data;
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
     * Data popup disposisi: daftar bidang, lampiran, dan riwayat terakhir.
     */
    public function disposition(IncomingLetter $incomingLetter): JsonResponse
    {
        $history = $incomingLetter->dispositions()
            ->orderByDesc('id')
            ->get()
            ->unique('kode_bidang')
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
            ],
            'bidangs' => $bidangs,
            'send_url' => route('surat-masuk.disposisi.kirim', $incomingLetter),
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

        foreach ($result['results'] as $row) {
            LetterDisposition::create([
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
            'results' => $result['results'],
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
