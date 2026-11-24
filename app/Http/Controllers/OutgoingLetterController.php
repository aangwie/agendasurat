<?php

namespace App\Http\Controllers;

use App\Http\Requests\OutgoingLetterRequest;
use App\Models\OutgoingLetter;
use App\Models\User;
use App\Services\WhatsappNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OutgoingLetterController extends Controller
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
     * Tampilkan datatable surat keluar.
     */
    public function index(Request $request): View|Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $perPage = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $user = $request->user();

        $letters = OutgoingLetter::query()
            ->with('user')
            ->search($search)
            ->orderByDesc('year')
            ->orderByDesc('code')
            ->orderByDesc('sub_code')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $openForm = session('open_form');
        $openFormId = session('open_form_id');
        $openLetter = null;

        if ($openForm === 'edit' && $openFormId) {
            $openLetter = OutgoingLetter::query()
                ->whereKey($openFormId)
                ->where(fn ($query) => $user->isAdmin()
                    ? $query
                    : $query->where('user_id', $user->id))
                ->first();
        }

        $usedByYear = OutgoingLetter::query()
            ->select(['year', 'code', 'sub_code'])
            ->get()
            ->groupBy('year')
            ->map(fn ($rows) => $rows->map(fn (OutgoingLetter $row) => $row->code.'-'.$row->sub_code)->values());

        // Administrator memilih bidang pemilik surat dari akun bidang yang sudah
        // memiliki kode bidang. Pengguna biasa memakai bidang akunnya sendiri.
        $bidangOptions = $user->isAdmin()
            ? User::query()
                ->whereNotNull('kode_bidang')
                ->where('kode_bidang', '<>', '')
                ->orderBy('name')
                ->get(['id', 'name', 'kode_bidang'])
            : collect();

        $data = [
            'letters' => $letters,
            'search' => $search,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'totalLetters' => OutgoingLetter::query()->count(),
            'yearLetters' => OutgoingLetter::query()->where('year', now()->year)->count(),
            'myBidangLetters' => OutgoingLetter::query()->where('bidang', $user->name)->count(),
            'usedByYear' => $usedByYear,
            'bidangOptions' => $bidangOptions,
            'months' => OutgoingLetter::MONTHS,
            'allCodes' => OutgoingLetter::allCodes(),
            'allSubCodes' => OutgoingLetter::allSubCodes(),
            'openForm' => $openForm,
            'openFormId' => $openFormId,
            'openLetter' => $openLetter,
        ];

        // Permintaan AJAX (datatable) hanya mengembalikan partial tabel.
        if ($request->ajax()) {
            return response()->view('surat-keluar._results', $data);
        }

        return view('surat-keluar.index', $data);
    }

    /**
     * Simpan surat keluar baru.
     */
    public function store(OutgoingLetterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $letter = OutgoingLetter::create([
            'user_id' => $request->user()->id,
            'year' => $validated['year'],
            'month' => $validated['month'],
            'code' => $validated['code'],
            'sub_code' => $validated['sub_code'],
            'letter_prefix' => $validated['letter_prefix'],
            'kode_bidang' => $validated['kode_bidang'],
            'letter_number' => $validated['letter_number'],
            'subject' => $validated['subject'],
            'letter_date' => $validated['letter_date'],
            'bidang' => $validated['bidang'],
        ]);

        return redirect()
            ->route('surat-keluar.index')
            ->with('status', $this->statusMessage($letter, $this->notifier->notifyOutgoingLetter($letter)));
    }

    /**
     * Gabungkan pesan sukses dengan ringkasan notifikasi WhatsApp.
     *
     * @param  array{message: string}  $notice
     */
    private function statusMessage(OutgoingLetter $letter, array $notice): string
    {
        $message = "Surat keluar {$letter->fullCode()} berhasil ditambahkan.";

        return trim($message.' '.($notice['message'] ?? ''));
    }

    /**
     * Perbarui surat keluar.
     */
    public function update(OutgoingLetterRequest $request, OutgoingLetter $outgoingLetter): RedirectResponse
    {
        $outgoingLetter->update($request->validated());

        return redirect()
            ->route('surat-keluar.index')
            ->with('status', "Surat keluar {$outgoingLetter->fullCode()} berhasil diperbarui.");
    }

    /**
     * Hapus surat keluar.
     */
    public function destroy(Request $request, OutgoingLetter $outgoingLetter): RedirectResponse
    {
        abort_unless($outgoingLetter->canBeManagedBy($request->user()), 403);

        $code = $outgoingLetter->fullCode();

        $outgoingLetter->delete();

        return redirect()
            ->route('surat-keluar.index')
            ->with('status', "Surat keluar {$code} berhasil dihapus.");
    }
}
