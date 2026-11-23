<?php

namespace App\Models;

use Database\Factories\IncomingLetterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * Build the next agenda number suggestion, e.g. "SM/2026/007".
     */
    public static function suggestAgendaNumber(): string
    {
        $year = now()->year;
        $prefix = 'SM/'.$year.'/';

        $last = static::query()
            ->where('agenda_number', 'like', $prefix.'%')
            ->orderByDesc('agenda_number')
            ->value('agenda_number');

        $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
