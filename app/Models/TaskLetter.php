<?php

namespace App\Models;

use Database\Factories\TaskLetterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskLetter extends Model
{
    /** @use HasFactory<TaskLetterFactory> */
    use HasFactory;

    /**
     * Tahun surat tugas yang dapat dipilih.
     */
    public const MIN_YEAR = 2000;

    public const MAX_YEAR = 2099;

    /**
     * Jumlah subkode untuk setiap kode.
     */
    public const SUB_CODES_PER_CODE = 99;

    /**
     * Jumlah kode arsip untuk setiap bulan.
     *
     * Januari memiliki empat kode (00-03), Februari tiga kode (04-06), dan
     * bulan-bulan berikutnya masing-masing tiga kode sampai Desember (34-36).
     * Total tersedia 37 kode per tahun.
     *
     * @var array<int, int>
     */
    public const CODES_PER_MONTH = [
        1 => 4,
        2 => 3,
        3 => 3,
        4 => 3,
        5 => 3,
        6 => 3,
        7 => 3,
        8 => 3,
        9 => 3,
        10 => 3,
        11 => 3,
        12 => 3,
    ];

    /**
     * Nama bulan dalam bahasa Indonesia, indeks 1-12.
     *
     * @var array<int, string>
     */
    public const MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'year',
        'month',
        'code',
        'sub_code',
        'letter_prefix',
        'kode_bidang',
        'letter_number',
        'subject',
        'letter_date',
        'bidang',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'letter_date' => 'date',
        ];
    }

    /**
     * Pengguna yang membuat surat tugas.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Apakah pengguna berhak mengubah/menghapus surat ini?
     * Hanya administrator dan pembuat surat yang diizinkan.
     */
    public function canBeManagedBy(User $user): bool
    {
        return $user->isAdmin() || $this->user_id === $user->id;
    }

    /**
     * Kode-kode arsip yang tersedia untuk bulan tertentu.
     *
     * @return list<string>
     */
    public static function codesForMonth(int $month): array
    {
        if (! isset(self::CODES_PER_MONTH[$month])) {
            return [];
        }

        $start = 0;

        for ($current = 1; $current < $month; $current++) {
            $start += self::CODES_PER_MONTH[$current];
        }

        return array_map(
            fn (int $offset): string => str_pad((string) ($start + $offset), 2, '0', STR_PAD_LEFT),
            range(0, self::CODES_PER_MONTH[$month] - 1),
        );
    }

    /**
     * Seluruh kode arsip untuk satu tahun (00-36).
     *
     * @return list<string>
     */
    public static function allCodes(): array
    {
        $codes = [];

        foreach (array_keys(self::CODES_PER_MONTH) as $month) {
            $codes = array_merge($codes, self::codesForMonth($month));
        }

        return $codes;
    }

    /**
     * Bulan pemilik sebuah kode arsip. Mengembalikan 0 bila kode tidak dikenal.
     */
    public static function monthForCode(string $code): int
    {
        foreach (array_keys(self::CODES_PER_MONTH) as $month) {
            if (in_array($code, self::codesForMonth($month), true)) {
                return $month;
            }
        }

        return 0;
    }

    /**
     * Seluruh subkode 01-99.
     *
     * @return list<string>
     */
    public static function allSubCodes(): array
    {
        return array_map(
            fn (int $number): string => str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            range(1, self::SUB_CODES_PER_CODE),
        );
    }

    /**
     * Label bulan, mis. 1 => "Januari".
     */
    public function monthLabel(): string
    {
        return self::MONTHS[$this->month] ?? (string) $this->month;
    }

    /**
     * Susun nomor surat lengkap dari kode depan, kode+subkode, kode bidang, dan tahun.
     *
     * Contoh: "400.3.5", "03", "56", "408.37.06", 2026
     * menjadi "400.3.5/0356/408.37.06/2026".
     */
    public static function composeNumber(
        string $prefix,
        string $code,
        string $subCode,
        string $kodeBidang,
        int $year,
    ): string {
        return trim($prefix).'/'.$code.$subCode.'/'.trim($kodeBidang).'/'.$year;
    }

    /**
     * Nomor surat lengkap, mis. "400.3.5/0356/408.37.06/2026".
     *
     * Data lama yang belum memiliki nomor tersusun akan memakai format
     * "tahun/kode/subkode" sebagai cadangan.
     */
    public function fullCode(): string
    {
        if (filled($this->letter_number)) {
            return (string) $this->letter_number;
        }

        return $this->year.'/'.$this->code.'/'.$this->sub_code;
    }

    /**
     * Tanggal surat dalam format Indonesia, mis. "12 Agustus 2026".
     */
    public function letterDateLabel(): string
    {
        return $this->letter_date?->locale('id')->translatedFormat('j F Y') ?? '—';
    }

    /**
     * Cari berdasarkan nomor surat, perihal, bidang, kode, subkode, tahun, atau pembuat.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where('letter_number', 'like', $like)
                ->orWhere('letter_prefix', 'like', $like)
                ->orWhere('kode_bidang', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('bidang', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('sub_code', 'like', $like)
                ->orWhere('year', 'like', $like)
                ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $like));
        });
    }
}

