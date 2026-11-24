<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskLetterRequest;
use App\Models\TaskLetter;
use App\Models\User;
use App\Services\WhatsappNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskLetterController extends Controller
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
     * Tampilkan datatable surat tugas.
     */
    public function index(Request $request): View|Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $perPage = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $user = $request->user();

        $letters = TaskLetter::query()
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
            $openLetter = TaskLetter::query()
                ->whereKey($openFormId)
                ->where(fn ($query) => $user->isAdmin()
                    ? $query
                    : $query->where('user_id', $user->id))
                ->first();
        }

        $usedByYear = TaskLetter::query()
            ->select(['year', 'code', 'sub_code'])
            ->get()
            ->groupBy('year')
            ->map(fn ($rows) => $rows->map(fn (TaskLetter $row) => $row->code.'-'.$row->sub_code)->values());

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
            'totalLetters' => TaskLetter::query()->count(),
            'yearLetters' => TaskLetter::query()->where('year', now()->year)->count(),
            'myBidangLetters' => TaskLetter::query()->where('bidang', $user->name)->count(),
            'usedByYear' => $usedByYear,
            'bidangOptions' => $bidangOptions,
            'months' => TaskLetter::MONTHS,
            'allCodes' => TaskLetter::allCodes(),
            'allSubCodes' => TaskLetter::allSubCodes(),
            'openForm' => $openForm,
            'openFormId' => $openFormId,
            'openLetter' => $openLetter,
        ];

        // Permintaan AJAX (datatable) hanya mengembalikan partial tabel.
        if ($request->ajax()) {
            return response()->view('surat-tugas._results', $data);
        }

        return view('surat-tugas.index', $data);
    }

    /**
     * Simpan surat tugas baru.
     */
    public function store(TaskLetterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $letter = TaskLetter::create([
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
            ->route('surat-tugas.index')
            ->with('status', $this->statusMessage($letter, $this->notifier->notifyTaskLetter($letter)));
    }

    /**
     * Gabungkan pesan sukses dengan ringkasan notifikasi WhatsApp.
     *
     * @param  array{message: string}  $notice
     */
    private function statusMessage(TaskLetter $letter, array $notice): string
    {
        $message = "Surat tugas {$letter->fullCode()} berhasil ditambahkan.";

        return trim($message.' '.($notice['message'] ?? ''));
    }

    /**
     * Perbarui surat tugas.
     */
    public function update(TaskLetterRequest $request, TaskLetter $taskLetter): RedirectResponse
    {
        $taskLetter->update($request->validated());

        return redirect()
            ->route('surat-tugas.index')
            ->with('status', "Surat tugas {$taskLetter->fullCode()} berhasil diperbarui.");
    }

    /**
     * Hapus surat tugas.
     */
    public function destroy(Request $request, TaskLetter $taskLetter): RedirectResponse
    {
        abort_unless($taskLetter->canBeManagedBy($request->user()), 403);

        $code = $taskLetter->fullCode();

        $taskLetter->delete();

        return redirect()
            ->route('surat-tugas.index')
            ->with('status', "Surat tugas {$code} berhasil dihapus.");
    }
}
