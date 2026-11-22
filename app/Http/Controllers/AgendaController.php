<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgendaRequest;
use App\Models\Agenda;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AgendaController extends Controller
{
    /**
     * Pilihan jumlah baris per halaman (slice data), terkecil 10.
     *
     * @var list<int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    private const DEFAULT_PER_PAGE = 10;

    /**
     * Tampilkan datatable agenda kegiatan.
     */
    public function index(Request $request): View|Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $perPage = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $agendas = Agenda::query()
            ->search($search)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $data = [
            'agendas' => $agendas,
            'search' => $search,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'totalAgendas' => Agenda::query()->count(),
            'upcomingAgendas' => Agenda::query()->whereDate('date', '>=', today())->count(),
            'openForm' => session('open_form'),
            'openFormId' => session('open_form_id'),
        ];

        if ($request->ajax()) {
            return response()->view('agenda._results', $data);
        }

        return view('agenda.index', $data);
    }

    /**
     * Simpan agenda baru.
     */
    public function store(AgendaRequest $request): RedirectResponse
    {
        $agenda = Agenda::create($request->validated());

        return redirect()
            ->route('agenda.index')
            ->with('status', 'Agenda tanggal '.$agenda->date?->locale('id')->translatedFormat('j F Y').' berhasil ditambahkan.');
    }

    /**
     * Perbarui agenda.
     */
    public function update(AgendaRequest $request, Agenda $agenda): RedirectResponse
    {
        $agenda->update($request->validated());

        return redirect()
            ->route('agenda.index')
            ->with('status', 'Agenda tanggal '.$agenda->date?->locale('id')->translatedFormat('j F Y').' berhasil diperbarui.');
    }

    /**
     * Hapus agenda.
     */
    public function destroy(Agenda $agenda): RedirectResponse
    {
        $date = $agenda->date?->locale('id')->translatedFormat('j F Y');

        $agenda->delete();

        return redirect()
            ->route('agenda.index')
            ->with('status', 'Agenda tanggal '.$date.' berhasil dihapus.');
    }
}
