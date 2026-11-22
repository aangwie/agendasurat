<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AppSetting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['key', 'value'];

    /**
     * Ambil nilai pengaturan (mengembalikan $default bila belum ada).
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    /**
     * Simpan nilai pengaturan.
     */
    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Ambil nilai rahasia (terenkripsi). Nilai lama yang belum terenkripsi
     * tetap dapat dibaca agar migrasi data tidak merusak pengaturan.
     */
    public static function getSecret(string $key, ?string $default = null): ?string
    {
        $value = static::get($key);

        if (blank($value)) {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    /**
     * Simpan nilai rahasia dalam bentuk terenkripsi.
     */
    public static function putSecret(string $key, ?string $value): void
    {
        static::put($key, blank($value) ? null : Crypt::encryptString($value));
    }
}
