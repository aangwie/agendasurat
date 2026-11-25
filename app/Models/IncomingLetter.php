<?php

namespace App\Models;

use Database\Factories\IncomingLetterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IncomingLetter extends Model
{
    /** @use HasFactory<IncomingLetterFactory> */
    use HasFactory;

    /**
     * Pilihan sifat surat (checkbox).
     *
     * @var array<string, string>
     */
    public const SIFAT_OPTIONS = [
        'segera' => 'Segera/Penting',
        'terbuka' => 'Terbuka',
        'rahasia' => 'Rahasia',
    ];

    /**
     * Kode bidang yang berhak mencatat surat masuk (Subbagian Umum dan Kepegawaian).
     *
     * Pengguna lain hanya dapat membaca dan mencetak.
     */
    public const MANAGER_KODE_BIDANG = '408.37.01';

    /**
     * Disk penyimpanan lampiran surat (privat, diunduh lewat controller).
     */
    public const ATTACHMENT_DISK = 'local';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'agenda_number',
        'received_date',
        'letter_date',
        'letter_number',
        'sender',
        'subject',
        'sifat',
        'attachment_path',
        'attachment_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'letter_date' => 'date',
            'sifat' => 'array',
        ];
    }

    /**
     * Get the human readable labels for the selected sifat.
     *
     * @return list<string>
     */
    public function sifatLabels(): array
    {
        return collect($this->sifat ?? [])
            ->map(fn (string $value): ?string => self::SIFAT_OPTIONS[$value] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Riwayat disposisi surat ini.
     *
     * @return HasMany<LetterDisposition, $this>
     */
    public function dispositions(): HasMany
    {
        return $this->hasMany(LetterDisposition::class);
    }

    /**
     * Disposisi terakhir yang masih aktif untuk setiap bidang.
     *
     * Baris berstatus "cancelled" diabaikan sehingga badge pada tabel surat
     * masuk ikut hilang setelah disposisi dibatalkan.
     *
     * @return Collection<int, LetterDisposition>
     */
    public function latestDispositions(): Collection
    {
        return $this->dispositions
            ->sortByDesc('id')
            ->unique('kode_bidang')
            ->reject(fn (LetterDisposition $disposition): bool => $disposition->status === LetterDisposition::STATUS_CANCELLED)
            ->sortBy('kode_bidang')
            ->values();
    }

    /**
     * Apakah surat memiliki lampiran berkas.
     */
    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    /**
     * Nama berkas lampiran yang ditampilkan ke pengguna.
     */
    public function attachmentLabel(): string
    {
        return (string) ($this->attachment_name ?: basename((string) $this->attachment_path));
    }

    /**
     * Hapus berkas lampiran dari disk (dipanggil sebelum menimpa/menghapus surat).
     */
    public function deleteAttachment(): void
    {
        if (! $this->hasAttachment()) {
            return;
        }

        Storage::disk(self::ATTACHMENT_DISK)->delete((string) $this->attachment_path);
    }

    /**
     * Search the agenda number, letter number, sender, and subject.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where('agenda_number', 'like', $like)
                ->orWhere('letter_number', 'like', $like)
                ->orWhere('sender', 'like', $like)
                ->orWhere('subject', 'like', $like);
        });
    }

    /**
     * Nomor agenda terbesar yang saat ini tercatat (0 bila belum ada).
     *
     * Nomor agenda disimpan sebagai string digit. Penambahan `+ 0` membuat
     * perbandingan berjalan numerik pada MySQL maupun SQLite, dan data lama
     * berformat lain (mis. "SM/2026/001") otomatis dihitung 0 sehingga diabaikan.
     */
    public static function maxAgendaNumber(): int
    {
        return (int) static::query()->max(DB::raw('agenda_number + 0'));
    }

    /**
     * Nomor agenda berikutnya: nomor terbesar saat ini + 1, mis. 1699 => 1700.
     */
    public static function suggestAgendaNumber(): string
    {
        return (string) (static::maxAgendaNumber() + 1);
    }
}
