<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BidangUserSeeder extends Seeder
{
    /**
     * Password yang sama untuk seluruh akun bidang.
     */
    public const PASSWORD = '@Dewisartika17';

    /**
     * Daftar bidang/subbagian beserta kode register-nya.
     *
     * Suffix email diambil dari 2 digit terakhir kode register,
     * contoh: 408.37.06 -> ptk.06@dindikpct.go.id
     *
     * @var list<array{code: string, slug: string, name: string}>
     */
    public const BIDANG = [
        ['code' => '408.37.01', 'slug' => 'umum', 'name' => 'Subbagian Umum dan Kepegawaian / Tata Usaha'],
        ['code' => '408.37.02', 'slug' => 'sekretariat', 'name' => 'Sekretariat (Subbag Keuangan & Aset / Subbag Program & Perencanaan)'],
        ['code' => '408.37.03', 'slug' => 'sd', 'name' => 'Bidang Pembinaan Sekolah Dasar (SD)'],
        ['code' => '408.37.04', 'slug' => 'smp', 'name' => 'Bidang Pembinaan Sekolah Menengah Pertama (SMP)'],
        ['code' => '408.37.05', 'slug' => 'paud', 'name' => 'Bidang Pembinaan PAUD dan PNF'],
        ['code' => '408.37.06', 'slug' => 'ptk', 'name' => 'Bidang Pendidik dan Tenaga Kependidikan (PTK)'],
    ];

    /**
     * Domain email instansi.
     */
    public const DOMAIN = 'dindikpct.go.id';

    /**
     * Seed akun untuk setiap bidang/subbagian.
     *
     * Non-destruktif: memakai updateOrCreate sehingga data pengguna lain
     * tidak dihapus dan akun bidang hanya diperbarui bila sudah ada.
     */
    public function run(): void
    {
        $created = 0;
        $updated = 0;

        foreach (self::BIDANG as $bidang) {
            $suffix = Str::afterLast($bidang['code'], '.');
            $email = $bidang['slug'].'.'.$suffix.'@'.self::DOMAIN;

            $user = User::query()->where('email', $email)->first();

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $bidang['name'],
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                    'is_admin' => false,
                ],
            );

            $user ? $updated++ : $created++;
        }

        $this->command?->info(sprintf(
            'Akun bidang: %d dibuat, %d diperbarui (password: %s).',
            $created,
            $updated,
            self::PASSWORD,
        ));

        foreach (self::BIDANG as $bidang) {
            $suffix = Str::afterLast($bidang['code'], '.');
            $this->command?->line('  - '.$bidang['slug'].'.'.$suffix.'@'.self::DOMAIN.'  ('.$bidang['code'].')');
        }
    }
}
