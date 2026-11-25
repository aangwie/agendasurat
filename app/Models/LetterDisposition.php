<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat disposisi surat masuk ke satu bidang.
 *
 * Setiap pengiriman notifikasi WhatsApp dicatat agar halaman surat masuk dapat
 * menampilkan bidang mana yang sudah menerima disposisi.
 */
class LetterDisposition extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'incoming_letter_id',
        'kode_bidang',
        'bidang_name',
        'user_id',
        'phone',
        'status',
        'message',
        'disposed_by',
        'sent_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Surat masuk yang didisposisikan.
     *
     * @return BelongsTo<IncomingLetter, $this>
     */
    public function incomingLetter(): BelongsTo
    {
        return $this->belongsTo(IncomingLetter::class);
    }

    /**
     * Akun penerima disposisi.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Label status untuk ditampilkan pada halaman.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'Terkirim',
            self::STATUS_FAILED => 'Gagal',
            self::STATUS_SKIPPED => 'Dilewati',
            default => 'Tidak diketahui',
        };
    }
}
