<?php

namespace App\Http\Controllers;

use App\Models\LetterClassification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

class LetterClassificationController extends Controller
{
    /**
     * Pilihan jumlah baris per halaman (slice data).
     *
     * @var list<int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    private const DEFAULT_PER_PAGE = 25;

    /**
     * Display the hierarchical letter classification (Permendagri 83/2022).
     */
    public function index(Request $request): View|Response
    {
        $scope = in_array($request->query('scope'), LetterClassification::SCOPES, true)
            ? (string) $request->query('scope')
            : LetterClassification::SCOPE_PEMDA;

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $perPage = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $rows = $this->rows($scope, $search);
        $total = count($rows);

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) $request->query('page', 1)), $lastPage);

        $paginator = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            $total,
            $perPage,
            $page,
            [
                'path' => route('klasifikasi.index'),
                'query' => array_filter([
                    'scope' => $scope,
                    'q' => $search !== '' ? $search : null,
                    'per_page' => $perPage,
                ], fn ($value) => $value !== null && $value !== ''),
            ],
        );

        $counts = $this->counts();

        $data = [
            'scope' => $scope,
            'search' => $search,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'paginator' => $paginator,
            'counts' => $counts,
            'totalAll' => array_sum($counts),
        ];

        // Permintaan AJAX (datatable) hanya mengembalikan bagian tabel.
        if ($request->ajax()) {
            return response()->view('klasifikasi._results', $data);
        }

        return view('klasifikasi.index', $data);
    }

    /**
     * Build the ordered rows for a scope. When searching, ancestors of the
     * matching rows are kept so the hierarchy stays understandable.
     *
     * @return list<array{code: string, name: string, level: int, parent: ?string, matched: bool}>
     */
    private function rows(string $scope, string $search): array
    {
        $nodes = LetterClassification::query()
            ->forScope($scope)
            ->inDocumentOrder()
            ->get(['id', 'code', 'name', 'parent_id', 'level']);

        $codeById = $nodes->pluck('code', 'id');
        $nodeByCode = $nodes->keyBy('code');

        if ($search === '') {
            return $nodes->map(fn (LetterClassification $node): array => [
                'code' => $node->code,
                'name' => $node->name,
                'level' => $node->level,
                'parent' => $node->parent_id !== null ? ($codeById[$node->parent_id] ?? null) : null,
                'matched' => true,
            ])->values()->all();
        }

        $needle = mb_strtolower($search);
        $matched = [];

        foreach ($nodes as $node) {
            if (str_contains(mb_strtolower($node->code), $needle)
                || str_contains(mb_strtolower($node->name), $needle)) {
                $matched[$node->code] = true;
            }
        }

        // Sertakan induk dari setiap baris yang cocok agar konteks hierarki tetap terlihat.
        $included = [];

        foreach (array_keys($matched) as $code) {
            $cursor = $nodeByCode[$code] ?? null;
            $guard = 0;

            while ($cursor !== null && $guard < 10) {
                $included[$cursor->code] = true;
                $cursor = $cursor->parent_id !== null
                    ? ($nodeByCode[$codeById[$cursor->parent_id] ?? ''] ?? null)
                    : null;
                $guard++;
            }
        }

        $rows = [];

        foreach ($nodes as $node) {
            if (! isset($included[$node->code])) {
                continue;
            }

            $rows[] = [
                'code' => $node->code,
                'name' => $node->name,
                'level' => $node->level,
                'parent' => $node->parent_id !== null ? ($codeById[$node->parent_id] ?? null) : null,
                'matched' => isset($matched[$node->code]),
            ];
        }

        return $rows;
    }

    /**
     * Count rows per scope for the tab badges.
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = LetterClassification::query()
            ->selectRaw('scope, count(*) as total')
            ->groupBy('scope')
            ->pluck('total', 'scope')
            ->all();

        $result = [];

        foreach (LetterClassification::SCOPES as $scope) {
            $result[$scope] = (int) ($counts[$scope] ?? 0);
        }

        return $result;
    }
}
