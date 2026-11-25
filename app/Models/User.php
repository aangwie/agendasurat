<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'alias',
        'kode_bidang',
        'email',
        'password',
        'phone',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Apakah pengguna ini administrator (boleh mengakses menu sistem)?
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Label ringkas bidang: alias bila diisi, jika tidak nama lengkap.
     */
    public function bidangLabel(): string
    {
        $alias = trim((string) ($this->alias ?? ''));

        return $alias !== '' ? $alias : (string) $this->name;
    }

    /**
     * Apakah pengguna berhak mencatat/mengubah surat masuk?
     *
     * Hanya administrator dan akun Subbagian Umum dan Kepegawaian (408.37.01)
     * yang diizinkan; akun bidang lain bersifat hanya-baca.
     */
    public function canManageIncomingLetters(): bool
    {
        return $this->isAdmin()
            || $this->kode_bidang === IncomingLetter::MANAGER_KODE_BIDANG;
    }

    /**
     * Get up to two initials from the user's name, e.g. "Budi Santoso" => "BS".
     */
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Str::of($this->name)
                ->explode(' ')
                ->filter()
                ->take(2)
                ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                ->implode('') ?: '?',
        );
    }
}
