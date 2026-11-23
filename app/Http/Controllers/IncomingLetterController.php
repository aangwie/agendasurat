<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomingLetterRequest;
use App\Models\IncomingLetter;
use App\Models\InstitutionProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IncomingLetterController extends Controller
{
    /**
     * Pilihan jumlah baris per halaman (slice data), terkecil 10.
     *
     * @var list<int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    private const DEFAULT_PER_PAGE = 10;

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

        $incomingLetter->delete();

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', "Surat masuk {$agendaNumber} berhasil dihapus.");
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
