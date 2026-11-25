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
     * @var list<array{code: string, slug: string, name: string, alias: string}>
     */
    public const BIDANG = [
        ['code' => '408.37.01', 'slug' => 'umum', 'name' => 'Subbagian Umum dan Kepegawaian / Tata Usaha', 'alias' => 'UMUM'],
        ['code' => '408.37.02', 'slug' => 'sekretariat', 'name' => 'Sekretariat (Subbag Keuangan & Aset / Subbag Program & Perencanaan)', 'alias' => 'SEKRETARIAT'],
        ['code' => '408.37.03', 'slug' => 'sd', 'name' => 'Bidang Pembinaan Sekolah Dasar (SD)', 'alias' => 'SD'],
        ['code' => '408.37.04', 'slug' => 'smp', 'name' => 'Bidang Pembinaan Sekolah Menengah Pertama (SMP)', 'alias' => 'SMP'],
        ['code' => '408.37.05', 'slug' => 'paud', 'name' => 'Bidang Pembinaan PAUD dan PNF', 'alias' => 'PAUD'],
        ['code' => '408.37.06', 'slug' => 'ptk', 'name' => 'Bidang Pendidik dan Tenaga Kependidikan (PTK)', 'alias' => 'PTK'],
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

            $user = User::query()->firstOrNew(['email' => $email]);
            $isNew = ! $user->exists;

            $user->fill([
                'name' => $bidang['name'],
                'kode_bidang' => $bidang['code'],
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'is_admin' => false,
            ]);

            // Alias hanya diisi saat akun dibuat atau bila masih kosong, agar
            // perubahan manual dari menu Pengguna tidak tertimpa.
            if (blank($user->alias)) {
                $user->alias = $bidang['alias'];
            }

            $user->save();

            $isNew ? $created++ : $updated++;
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
