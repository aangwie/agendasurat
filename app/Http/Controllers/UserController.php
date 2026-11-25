<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Pilihan jumlah baris per halaman (slice data), terkecil 10.
     *
     * @var list<int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    private const DEFAULT_PER_PAGE = 10;

    /**
     * Display the user management datatable.
     */
    public function index(Request $request): View|Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $perPage = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhere('kode_bidang', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $data = [
            'users' => $users,
            'search' => $search,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'totalUsers' => User::query()->count(),
            'openForm' => session('open_form'),
            'openFormId' => session('open_form_id'),
        ];

        // Permintaan AJAX (datatable) hanya mengembalikan partial tabel.
        if ($request->ajax()) {
            return response()->view('pengguna._results', $data);
        }

        return view('pengguna.index', $data);
    }

    /**
     * Store a newly created user.
     */
    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'alias' => $request->validated('alias'),
            'kode_bidang' => $request->validated('kode_bidang'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => Hash::make($request->validated('password')),
            'email_verified_at' => now(),
            'is_admin' => $request->boolean('is_admin'),
        ]);

        return redirect()
            ->route('pengguna.index')
            ->with('status', "Pengguna {$user->name} berhasil ditambahkan.");
    }

    /**
     * Update the given user.
     */
    public function update(UserRequest $request, User $pengguna): RedirectResponse
    {
        $wantsAdmin = $request->boolean('is_admin');

        // Jangan sampai tidak ada administrator sama sekali.
        if ($pengguna->isAdmin() && ! $wantsAdmin) {
            if ($request->user()->is($pengguna)) {
                return redirect()
                    ->route('pengguna.index')
                    ->withErrors(['is_admin' => 'Anda tidak dapat mencabut hak administrator akun sendiri.']);
            }

            if (User::query()->where('is_admin', true)->count() <= 1) {
                return redirect()
                    ->route('pengguna.index')
                    ->withErrors(['is_admin' => 'Minimal harus ada satu administrator.']);
            }
        }

        $data = [
            'name' => $request->validated('name'),
            'alias' => $request->validated('alias'),
            'kode_bidang' => $request->validated('kode_bidang'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'is_admin' => $wantsAdmin,
        ];

        // Password hanya diubah bila diisi.
        if (filled($request->validated('password'))) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $pengguna->update($data);

        return redirect()
            ->route('pengguna.index')
            ->with('status', "Pengguna {$pengguna->name} berhasil diperbarui.");
    }

    /**
     * Remove the given user.
     */
    public function destroy(Request $request, User $pengguna): RedirectResponse
    {
        if ($request->user()->is($pengguna)) {
            return redirect()
                ->route('pengguna.index')
                ->withErrors(['pengguna' => 'Anda tidak dapat menghapus akun yang sedang dipakai.']);
        }

        if (User::query()->count() <= 1) {
            return redirect()
                ->route('pengguna.index')
                ->withErrors(['pengguna' => 'Pengguna terakhir tidak dapat dihapus.']);
        }

        if ($pengguna->isAdmin() && User::query()->where('is_admin', true)->count() <= 1) {
            return redirect()
                ->route('pengguna.index')
                ->withErrors(['pengguna' => 'Administrator terakhir tidak dapat dihapus.']);
        }

        $name = $pengguna->name;
        $pengguna->delete();

        return redirect()
            ->route('pengguna.index')
            ->with('status', "Pengguna {$name} berhasil dihapus.");
    }
}
