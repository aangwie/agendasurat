# Agenda Surat

Aplikasi tata usaha digital untuk mencatat surat masuk, surat keluar, dan agenda kegiatan kantor. Tahap ini merupakan **setup awal** yang berfokus pada autentikasi, layout aplikasi, dan dashboard.

## Stack

- Laravel 12 (PHP 8.2+)
- Tailwind CSS v4 melalui plugin `@tailwindcss/vite`
- Vite 6, JavaScript vanilla (tanpa framework tambahan)
- MySQL (default) / SQLite untuk pengujian

## Fitur saat ini

- Halaman login responsif (split-screen) dengan validasi, toggle kata sandi, dan pembatasan percobaan masuk (rate limit 5x).
- Dashboard dengan ringkasan surat, grafik volume surat (3 bar), agenda hari ini, tabel surat terbaru, dan distribusi status.
- Sidebar navigasi responsif (drawer di ponsel, tetap tampil di desktop) yang mendukung **minimize / maximize** dengan tooltip ikon dan status tersimpan di `localStorage`.
- Menu sidebar: Dashboard, Surat Masuk, Surat Keluar, Surat Tugas, Agenda, Klasifikasi, Laporan, Profil Instansi, Pengguna, dan Pengaturan.
- **Profil Instansi**: formulir pembaruan data instansi (nama pemerintahan, nama instansi, alamat instansi, alamat website, alamat email) sekaligus upload/ganti logo pemerintah dengan pratinjau langsung.
- **Klasifikasi Surat**: hierarki kode klasifikasi arsip (4 level) sesuai lampiran Permendagri Nomor 83 Tahun 2022, lengkap dengan datatable (pencarian, slice data **10**/25/50/100 dengan default 10, dan pagination).
- **Dashboard**: 4 kartu ringkasan (Surat Masuk, Surat Keluar, Surat Tugas, Total Surat) dan grafik 3 bar —
  Surat Masuk (merah `#e11d48`), Surat Keluar (biru laut `#1e3a8a`), Surat Tugas (kuning tua `#ca8a04`).
  Nilai Surat Masuk memakai data nyata; Surat Keluar & Surat Tugas masih 0 sampai modulnya tersedia.
- **Surat Masuk**: input tanggal surat, nomor surat, asal surat, perihal, sifat surat (checkbox Segera/Penting, Terbuka, Rahasia), tanggal diterima, dan nomor agenda (digit angka dengan saran otomatis nomor terbesar + 1); datatable dengan pencarian, slice data 10/25/50/100, dan pagination; aksi ikon **cetak**, **edit**, dan **hapus** (konfirmasi memakai SweetAlert2).
- **Surat Keluar**: penomoran berjenjang **Tahun → Bulan → Kode → Subkode** (kombinasi unik per tahun) dengan input **Nomor Surat Keluar**, **Perihal**, dan **Tanggal Surat**; datatable dengan pencarian, slice data 10/25/50/100, dan pagination; aksi ikon edit & hapus (konfirmasi SweetAlert2) hanya untuk pembuat surat dan administrator.
- **Agenda**: input **Tanggal, Tempat, Penanggung Jawab, Peserta**; datatable dengan pencarian, slice data 10/25/50/100, dan pagination; aksi ikon **edit** & **hapus** (konfirmasi SweetAlert2).
- **Pengguna (CRUD)**: kelola akun per bidang — nama bidang, kode bidang (format register, mis. `408.37.06`), email, no HP (opsional), password + konfirmasi password; datatable dengan pencarian (termasuk kode bidang), slice data 10/25/50/100, pagination, serta aksi ikon edit & hapus (konfirmasi SweetAlert2). Akun yang sedang dipakai tidak dapat dihapus.
- **Pengaturan (admin)**: pull dari GitHub (token terenkripsi), tombol `migrate --force` / clear cache / symlink, backup & import database, serta terminal log aktivitas.
- **Hak akses (admin)**: menu **Profil Instansi**, **Pengguna**, dan **Pengaturan** hanya terlihat dan
  dapat diakses oleh pengguna bertanda **Administrator** (selain itu menu disembunyikan dan URL
  mengembalikan 403). Administrator dapat menunjuk admin lain lewat checkbox di form Pengguna.
- Menu profil di pojok kanan navbar (Profil Saya, Pengaturan, Keluar).
- Halaman profil pengguna dan halaman penanda untuk modul yang belum tersedia.
- Tema aksen **zinc & rose** dengan font Plus Jakarta Sans.

## Akun demo

| Email | Kata sandi | Peran |
| --- | --- | --- |
| `admin@agendasurat.test` | `password` | Administrator |

Akun dibuat melalui `DatabaseSeeder`. Ganti kata sandi sebelum dipakai di lingkungan nyata.

### Akun bidang (BidangUserSeeder)

Dibuat otomatis oleh `php artisan db:seed` (atau `--class=BidangUserSeeder`).
Semua akun memakai kata sandi **`@Dewisartika17`** dan **bukan** administrator.

| Kode Register | Bidang / Subbagian | Username |
| --- | --- | --- |
| 408.37.01 | Subbagian Umum dan Kepegawaian / Tata Usaha | `umum.01@dindikpct.go.id` |
| 408.37.02 | Sekretariat (Keuangan & Aset / Program & Perencanaan) | `sekretariat.02@dindikpct.go.id` |
| 408.37.03 | Bidang Pembinaan Sekolah Dasar (SD) | `sd.03@dindikpct.go.id` |
| 408.37.04 | Bidang Pembinaan Sekolah Menengah Pertama (SMP) | `smp.04@dindikpct.go.id` |
| 408.37.05 | Bidang Pembinaan PAUD dan PNF | `paud.05@dindikpct.go.id` |
| 408.37.06 | Bidang Pendidik dan Tenaga Kependidikan (PTK) | `ptk.06@dindikpct.go.id` |

Pola username: `{slug bidang}.{2 digit terakhir kode register}@dindikpct.go.id`.
Seeder bersifat **non-destruktif** (`updateOrCreate`), jadi aman dijalankan ulang.

## Menjalankan aplikasi

```bash
composer install
cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate
npm install
php artisan migrate --seed
php artisan storage:link    # agar logo instansi dapat diakses publik
npm run build               # atau: npm run dev
php artisan serve
```

Buka `http://localhost:8000`, lalu masuk menggunakan akun demo di atas.

> Proyek ini memakai MySQL dengan database `agendasurat` (lihat `.env`). Buat database tersebut terlebih dahulu, atau ubah ke SQLite dengan `DB_CONNECTION=sqlite` pada `.env`.

Pengaturan tambahan pada `.env`:

```env
APP_NAME="Agenda Surat"
APP_TIMEZONE=Asia/Jakarta
```

## Struktur penting

| Path | Keterangan |
| --- | --- |
| `routes/web.php` | Rute login, dashboard, profil, profil instansi, dan modul penanda |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Proses masuk dan keluar |
| `app/Http/Requests/Auth/LoginRequest.php` | Validasi + rate limit login |
| `app/Http/Controllers/DashboardController.php` | Data dashboard (masih data contoh) |
| `app/Http/Controllers/InstitutionProfileController.php` | Form & proses update profil instansi |
| `app/Http/Requests/UpdateInstitutionProfileRequest.php` | Validasi profil instansi + normalisasi website |
| `app/Models/InstitutionProfile.php` | Model profil instansi (single row) + URL logo |
| `app/Http/Controllers/LetterClassificationController.php` | Datatable klasifikasi (cari, slice, pagination) |
| `app/Models/LetterClassification.php` | Model hierarki kode klasifikasi arsip |
| `database/seeders/LetterClassificationSeeder.php` | Import 3.972 kode klasifikasi dari lampiran Permendagri 83/2022 |
| `database/seeders/BidangUserSeeder.php` | 6 akun bidang (username dari kode register, password seragam) |
| `database/seeders/data/klasifikasi-arsip.json` | Dataset lampiran (Kemendagri & Pemerintah Daerah) |
| `resources/views/klasifikasi/index.blade.php` | Halaman klasifikasi + toolbar datatable |
| `resources/views/klasifikasi/_results.blade.php` | Partial tabel (dipakai halaman penuh & AJAX) |
| `resources/js/klasifikasi-table.js` | Pencarian debounce, slice data, dan pagination via AJAX |
| `app/Http/Controllers/IncomingLetterController.php` | Datatable + CRUD surat masuk & halaman cetak |
| `app/Http/Requests/IncomingLetterRequest.php` | Validasi surat masuk (termasuk nomor agenda unik) |
| `app/Models/IncomingLetter.php` | Model surat masuk + saran nomor agenda |
| `resources/views/surat-masuk/index.blade.php` | Halaman surat masuk (toolbar, tabel, modal) |
| `resources/views/surat-masuk/_results.blade.php` | Partial tabel (halaman penuh & AJAX) |
| `resources/views/surat-masuk/_form.blade.php` | Modal tambah/edit surat masuk |
| `resources/views/surat-masuk/cetak.blade.php` | Lembar disposisi A5 landscape (½ A4) dengan kop instansi |
| `config/surat.php` | Daftar tujuan & tindakan disposisi pada lembar cetak |
| `resources/js/datatable.js` | Datatable generik (pencarian, slice, pagination) |
| `resources/js/date-display.js` | Format tanggal Indonesia (`12 Agustus 2026`) untuk input tanggal |
| `resources/js/surat-masuk.js` | Modal tambah/edit + buka ulang saat validasi gagal |
| `app/Http/Controllers/OutgoingLetterController.php` | Datatable + CRUD surat keluar (penomoran tahun/kode/subkode) |
| `app/Http/Requests/OutgoingLetterRequest.php` | Validasi surat keluar (nomor, perihal, tanggal, keunikan kode) |
| `app/Models/OutgoingLetter.php` | Model surat keluar + kode bulanan & subkode |
| `resources/views/surat-keluar/` | Halaman surat keluar (index, tabel, form modal, aksi) |
| `resources/js/surat-keluar.js` | Modal surat keluar + hierarki tahun > bulan > kode > subkode |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | Middleware pembatas akses admin (menu sistem) |
| `resources/js/csrf.js` | Penyegaran token CSRF + penanganan sesi berakhir (419) |
| `resources/views/errors/419.blade.php` | Halaman ramah untuk error 419 Page Expired |
| `app/Services/DeploymentService.php` | Eksekusi git pull, artisan, backup/import database + log |
| `app/Http/Controllers/SettingsController.php` | Halaman Pengaturan (admin) |
| `app/Models/AppSetting.php` | Penyimpanan pengaturan (token terenkripsi) |
| `resources/views/pengaturan/index.blade.php` | UI pengaturan + terminal log |
| `resources/js/pengaturan.js` | Aksi pengaturan, konfirmasi SweetAlert2, terminal |
| `app/Http/Controllers/AgendaController.php` | CRUD agenda + datatable |
| `app/Http/Requests/AgendaRequest.php` | Validasi agenda (tanggal, tempat, PJ, peserta) |
| `app/Models/Agenda.php` | Model agenda + pencarian |
| `resources/views/agenda/` | Halaman agenda (index, tabel, form modal, aksi) |
| `resources/js/agenda.js` | Modal tambah/edit agenda |
| `app/Http/Controllers/UserController.php` | CRUD pengguna + datatable |
| `app/Http/Requests/UserRequest.php` | Validasi pengguna (email unik, password + konfirmasi, no HP) |
| `resources/views/pengguna/index.blade.php` | Halaman manajemen pengguna |
| `resources/views/pengguna/_results.blade.php` | Partial tabel pengguna |
| `resources/views/pengguna/_form.blade.php` | Modal tambah/edit pengguna |
| `resources/js/pengguna.js` | Modal pengguna (password opsional saat edit) |
| `resources/views/institution/edit.blade.php` | Halaman update profil instansi |
| `resources/views/components/layouts/app.blade.php` | Layout sidebar + navbar |
| `resources/views/components/layouts/guest.blade.php` | Layout halaman tamu (login) |
| `resources/views/components/input-field.blade.php` | Komponen input form (label, ikon, error) |
| `resources/views/auth/login.blade.php` | Halaman login |
| `resources/views/dashboard.blade.php` | Halaman dashboard |
| `resources/css/app.css` | Tema Tailwind v4, komponen dasar, dan aturan sidebar minimize |
| `resources/js/app.js` | Sidebar drawer, minimize/maximize, dropdown profil, toggle sandi, pratinjau logo, toast |

## Penyimpanan logo instansi

Logo pemerintah disimpan pada disk `public` di `storage/app/public/logos`. Jalankan
`php artisan storage:link` sekali agar berkas dapat diakses melalui `/storage/...`.
Format yang diterima: PNG, JPG, JPEG, WEBP (maks. 2 MB). Saat logo diganti, berkas lama otomatis dihapus.

Logo dan identitas instansi yang diunggah melalui menu **Profil Instansi** dipakai pada:

- kop **lembar disposisi** (cetak) — logo, nama pemerintahan, nama instansi, alamat,
  website, dan email;
- **sidebar** aplikasi (logo + nama instansi + nama pemerintahan);
- halaman **login** (panel brand & logo di layar kecil).

URL logo memakai path relatif (`/storage/logos/...`) dengan penanda versi, sehingga tetap
tampil baik diakses lewat `localhost`, `127.0.0.1`, maupun domain lain.

## Pengujian

```bash
php artisan test
```

Pengujian menggunakan SQLite in-memory (lihat `phpunit.xml`) sehingga tidak menyentuh database pengembangan.

## Klasifikasi surat (Permendagri 83/2022)

Halaman **Klasifikasi** memuat hierarki Kode Klasifikasi Arsip dari lampiran
Permendagri Nomor 83 Tahun 2022, mencakup dua bagian:

| Lingkup | Jumlah kode |
| --- | --- |
| Pemerintah Daerah | 2.979 |
| Kementerian Dalam Negeri | 993 |

Setiap kode memiliki level 1–4 (`000` → `000.1` → `000.1.1` → `000.1.1.1`) dan
disimpan pada tabel `letter_classifications` (relasi `parent_id`). Data dimuat
oleh `LetterClassificationSeeder` dari `database/seeders/data/klasifikasi-arsip.json`,
sehingga `php artisan db:seed` langsung mengisi seluruh hierarki.

Tabel klasifikasi mendukung:

- pencarian kode/uraian (induk dari baris yang cocok tetap ditampilkan sebagai konteks),
- slice data 10/25/50/100 baris per halaman,
- pagination, dan
- pemfilteran lingkup (Pemerintah Daerah / Kemendagri).

Semua interaksi tersebut berjalan lewat AJAX dengan fallback formulir GET biasa
bila JavaScript dinonaktifkan.

## Surat masuk

Halaman **Surat Masuk** (`/surat-masuk`) mencatat surat yang diterima instansi dengan
urutan field: **Tanggal Surat**, **Nomor Surat**, **Asal Surat**, **Perihal**,
**Sifat Surat** (checkbox), **Tanggal Diterima**, dan **Nomor Agenda**.

- Tanggal surat ditampilkan dengan format Indonesia, contoh **12 Agustus 2026**
  (berlaku pada tabel, lembar cetak, dan pratinjau di formulir).
- **Tanggal surat boleh melebihi hari ini** karena surat dapat diterbitkan di kemudian
  hari, sedangkan **tanggal diterima** tidak boleh melebihi hari ini.
- **Sifat Surat** berupa checkbox: *Segera/Penting*, *Terbuka*, dan *Rahasia*
  (boleh lebih dari satu, boleh dikosongkan). Nilai disimpan sebagai JSON dan
  ditampilkan sebagai badge pada tabel serta label pada lembar cetak.
- Nomor agenda berupa **digit angka** (mis. `1600`) dan divalidasi unik. Kolom saran
  pada kartu ringkasan dan formulir otomatis berisi **nomor agenda terbesar saat ini + 1**
  (mis. data terbesar `1699` → saran `1700`). Data lama berformat lain (mis. `SM/2026/001`)
  diabaikan saat menghitung nomor terbesar.
- **Hak akses**: hanya **Subbagian Umum dan Kepegawaian** (kode bidang `408.37.01`)
  dan **administrator** yang dapat menambah, mengubah, dan menghapus surat masuk.
  Akun bidang lain bersifat **hanya-baca**: tetap bisa melihat tabel, mencari,
  dan mencetak lembar disposisi, tetapi tombol tambah/edit/hapus disembunyikan
  dan permintaan tulis ditolak dengan `403`.
- Datatable: pencarian (nomor agenda, nomor surat, asal surat, perihal), slice data
  10/25/50/100 baris, dan pagination — semuanya berjalan lewat AJAX.
- Aksi per baris memakai ikon: **cetak** (`printer`), **disposisi** (`share`),
  **edit** (`pencil`), dan **hapus** (`trash`). Penghapusan dikonfirmasi dengan
  **SweetAlert2**.
- **Lampiran surat** (opsional) dapat diunggah pada formulir: PDF atau gambar
  (JPG/PNG/WEBP), maksimal 10 MB. Berkas disimpan pada disk **privat**
  (`storage/app/private/incoming-letters`) dan hanya dapat diunduh pengguna yang
  sudah login lewat `/surat-masuk/{id}/lampiran`.

### Disposisi surat masuk

Tombol **disposisi** pada kolom aksi membuka popup berisi:

- ringkasan surat (nomor agenda, nomor surat, perihal, asal, tanggal) dan
  **lampiran surat** dengan tombol **Unduh** (bila lampiran tersedia);
- **ceklist nama bidang** — diambil dari akun pengguna yang memiliki
  `kode_bidang` (satu bidang = satu akun, akun bernomor WhatsApp dipilih lebih
  dulu). Bidang tanpa nomor WhatsApp ditandai dan tidak dapat dicentang;
- status disposisi terakhir per bidang (Terkirim / Gagal / Dilewati).

Saat **Kirim Disposisi** ditekan, setiap bidang yang dicentang menerima pesan
WhatsApp berisi ringkasan surat, sifat, nama pendisposisi, dan **tautan unduhan
surat** (lampiran bila ada, jika tidak halaman cetak). Hasil tiap bidang
disimpan pada tabel `letter_dispositions` sehingga popup dapat menampilkan
riwayat terakhirnya. Bidang tanpa nomor atau yang nomornya sama dengan nomor
gateway otomatis **dilewati**.

Tautan pada pesan memakai `APP_URL`; bila `APP_URL` masih `localhost`, isi
`WHATSAPP_GATEWAY_PUBLIC_URL` (lihat bagian *Uji Pesan WhatsApp & Lampiran*).

### Lembar disposisi (cetak)

Halaman `/surat-masuk/{id}/cetak` mencetak **Lembar Disposisi** pada kertas putih
berukuran **A4 dengan area isi setinggi ½ A4** (210 × 148 mm, kertas putih), berisi:

- kop instansi dari menu **Profil Instansi** (logo, nama pemerintahan 14 pt,
  nama instansi 16 pt, alamat/website/email 12 pt) dengan garis ganda;
- tabel **LEMBAR DISPOSISI**: Surat Dari, Diterima Tanggal, Nomor Surat, Nomor Agenda,
  Tanggal Surat, Sifat (checkbox otomatis tercentang sesuai data), dan Hal — label dan isi
  data dipisahkan tanda `:` tanpa garis tegak (agar titik dua tidak terhimpit), dengan
  garis pemisah blok kiri/kanan di ±55% lebar lembar dan titik dua kolom kanan
  (Diterima Tanggal & Nomor Agenda) diberi padding kiri 1,5 mm;
- kolom **Diteruskan Kepada** (10 tujuan bernomor dengan kotak centang) dan
  **Dengan hormat harap** (Tanggapan dan saran, Proses lebih lanjut,
  Koordinasi/konfirmasi, serta satu baris titik-titik);
- kolom **Catatan** yang dikosongkan untuk diisi tangan, dengan tinggi 170 mm
  agar lega menampung tulisan tangan (lembar tetap muat satu halaman A4).

Kotak centang pada bagian disposisi sengaja dibiarkan kosong agar diisi manual saat
disposisi. Daftar tujuan/tindakan dapat diubah pada `config/surat.php`. Dialog cetak
terbuka otomatis; deklarasi halaman memakai A4 portrait sehingga hasil cetak menempati
separuh atas kertas A4 dan siap dipotong (2 lembar per kertas A4).

Data contoh opsional (28 baris) untuk mencoba datatable:

```bash
php artisan db:seed --class=IncomingLetterSeeder
```

## Surat keluar

Halaman **Surat Keluar** (`/surat-keluar`) mencatat surat yang diterbitkan instansi.
Nomor urut dipilih berjenjang: **Tahun → Bulan → Kode (00–59) → Subkode (01–99)**,
lalu diisi **Nomor Surat Keluar**, **Perihal**, dan **Tanggal Surat**.

Nomor surat **tidak diketik penuh**. Cukup isi **kode depan** (mis. `400.3.5`),
lalu sistem menyusun nomor lengkap dengan pola:

```
kode depan / kode+subkode / kode bidang / tahun
```

Contoh: kode depan `400.3.5`, kode `03`, subkode `56`, kode bidang akun `408.37.06`,
dan tahun `2026` menghasilkan **`400.3.5/0356/408.37.06/2026`**. Nilai inilah yang
disimpan pada kolom `letter_number` dan ditampilkan di tabel. Formulir menampilkan
pratinjau nomor lengkap secara langsung saat kode depan/tahun/bulan/kode/subkode diubah.

- Setiap tahun memiliki 5 kode per bulan (Januari `00–04` … Desember `55–59`).
  Kombinasi tahun + kode + subkode tidak boleh dipakai dua kali; kode/subkode yang
  sudah terpakai otomatis dinonaktifkan pada formulir.
- **Kode depan** wajib diisi (maks. 50 karakter) dan hanya boleh berisi huruf, angka,
  titik, serta tanda hubung — garis miring tidak dipakai agar nomor tetap 4 bagian.
- **Kode bidang** otomatis diambil dari kode bidang akun pembuat (`users.kode_bidang`).
  Akun tanpa kode bidang akan ditolak dengan pesan agar administrator melengkapinya.
  Khusus **administrator**, bidang pemilik surat dipilih dari daftar akun bidang
  (akun yang sudah memiliki kode bidang), lalu kode bidang diambil dari akun tersebut.
- **Perihal** wajib diisi (maks. 255 karakter) dan **Tanggal Surat** wajib diisi.
  Tanggal surat **boleh melebihi hari ini** karena surat dapat diterbitkan di kemudian
  hari. Tanggal ditampilkan dalam format Indonesia, contoh **12 Agustus 2026**
  (tabel dan pratinjau formulir).
- **Bidang** diambil dari akun pembuat surat, bukan input bebas. Administrator memilih
  bidang pemilik surat dari daftar akun bidang pada formulir tambah surat.
- Saat mengedit, nomor urut (tahun/kode/subkode) dan kode bidang tetap; hanya kode
  depan, perihal, dan tanggal surat yang dapat diubah. Nomor lengkap disusun ulang
  otomatis dari komponen tersebut.
- Datatable: pencarian (nomor surat, kode depan, kode bidang, perihal, bidang, kode,
  pembuat), slice data 10/25/50/100 baris, dan pagination via AJAX. Aksi ikon **edit**
  dan **hapus** (konfirmasi SweetAlert2) hanya tampil untuk pembuat surat dan administrator.

## Penanganan 419 Page Expired

Aplikasi memakai beberapa lapis perlindungan agar halaman tidak sering menampilkan
**419 Page Expired**:

1. **Masa sesi diperpanjang** — `SESSION_LIFETIME=240` menit (4 jam) dan
   `SESSION_EXPIRE_ON_CLOSE=false`, jadi sesi tidak habis saat tab ditutup.
2. **Token disegarkan otomatis** — `resources/js/csrf.js` memanggil `/csrf-token`
   setiap 5 menit dan setiap kali tab kembali aktif. Endpoint ini sekaligus
   memperpanjang masa aktif sesi, sehingga selama tab dibuka sesi tidak kedaluwarsa.
3. **Token disisipkan otomatis** pada setiap request `fetch` non-GET dan pada axios
   (`X-CSRF-TOKEN` dari `<meta name="csrf-token">`).
4. **Halaman 419 yang ramah** — `resources/views/errors/419.blade.php` menampilkan
   penjelasan, tombol *Muat Ulang Sekarang*, dan hitung mundur muat ulang otomatis.
5. **Respons JSON untuk AJAX** — request AJAX menerima
   `{"message": "...", "csrf_expired": true}` dan memunculkan notifikasi SweetAlert
   untuk memuat ulang halaman.

Penyebab umum yang sebaiknya dihindari: membuka aplikasi dari dua alamat berbeda
(`localhost` vs `127.0.0.1`), menghapus cookie saat halaman masih terbuka, atau
mengganti `APP_KEY` saat pengguna masih login.

## Build assets ikut di-repository

Folder `public/build` **tidak** di-ignore sehingga hasil `npm run build` ikut
ter-upload ke GitHub. Server/hosting dapat langsung menjalankan aplikasi tanpa
harus menjalankan Vite terlebih dahulu. Setelah mengubah CSS/JS, jalankan
`npm run build` lalu commit folder `public/build`.

## Halaman Pengaturan (admin)

Menu **Pengaturan** (khusus administrator) menyediakan:

- **Sinkronisasi GitHub** — cukup isi **Token GitHub (PAT)**; repository & branch memakai nilai
  tersimpan (ditampilkan sebagai info tujuan, bukan input). Token disimpan **terenkripsi**
  di tabel `app_settings` dan hanya ditampilkan tersamar.

  Aplikasi hanya melakukan **Pull**: tombol **Update Sistem** menjalankan `git pull`
  untuk mengambil perubahan terbaru dari repository ke server, dengan konfirmasi
  SweetAlert2. Token disamarkan (`***`) pada log. Tombol **Tes Token** memeriksa izin
  baca sebelum update. Push dilakukan dari komputer pengembang (`git push`), bukan dari aplikasi.
- **Perintah Artisan** — tombol untuk `php artisan migrate --force`,
  `php artisan optimize:clear`, dan `php artisan storage:link`.
- **Backup Database** — membuat berkas `.sql` di `storage/app/backups` (di-ignore git) dan
  mengunduhnya lewat tautan.
- **Import Database** — unggah `.sql` (maks 50 MB). Sistem **otomatis membuat backup
  pengaman** sebelum menimpa data, dan konfirmasi SweetAlert2 wajib disetujui.
- **Terminal log** — panel gelap di bawah halaman yang menampilkan output perintah
  (tersimpan di `storage/logs/deploy.log`), lengkap dengan status, muat ulang, dan bersihkan log.

> **Penting:** isi token dengan PAT GitHub asli (format `github_pat_...`) yang memiliki izin
> `Contents: Read` pada repository tujuan. Nilai `github_patxxxxxxxxxxxxxxxxxxx`
> hanyalah contoh — ganti dengan token Anda sendiri. Perintah dijalankan tanpa shell
> (argumen terpisah) sehingga aman dari injeksi, dan hanya aksi yang ada di whitelist
> yang diterima server.

### Jika pull gagal (error 401 / 403 / 404)

Tombol **Tes Token** memeriksa token lewat GitHub API sebelum pull dan menampilkan
penyebabnya secara spesifik:

| Pesan | Arti & solusi |
| --- | --- |
| `401 Bad credentials` | Token salah/kedaluwarsa → buat token baru. |
| `Token dikenali sebagai X, tetapi TIDAK punya izin baca` | Fine-grained token belum diberi **Contents: Read**, atau repository belum dipilih pada *Repository access*. |
| `404` | Token tidak diberi akses ke repository tersebut. |
| `perubahan lokal belum di-commit` | Server memiliki perubahan lokal; commit/simpan dulu sebelum pull. |
| `konflik merge` | Selesaikan konflik di server, lalu commit. |

Untuk lingkungan pengujian, set `DEPLOY_COMMANDS_ENABLED=false` agar perintah git/artisan
tidak dieksekusi (sudah diatur pada `phpunit.xml`).

## Uji Pesan WhatsApp & Lampiran

Menu **Whatsapp Gateway** (khusus administrator) menyediakan halaman **Uji Pesan**
di `/whatsapp-gateway/uji-pesan` untuk memastikan perangkat sudah tertaut dan pesan
terkirim dengan benar.

### Sesi per lingkungan (lokal vs hosting)

Gateway menyimpan **satu sesi Baileys per device ID**
(`.baileys_auth/device_{deviceId}`). Bila aplikasi lokal dan hosting memakai device
ID yang sama, keduanya berbagi satu sesi: **memutus koneksi di lokal ikut memutus
hosting** (dan sebaliknya), dan pesan uji dari lokal terkirim lewat perangkat hosting.

Karena itu device ID diberi akhiran lingkungan secara otomatis:

| Lingkungan (`APP_ENV`) | Device ID | Catatan |
| --- | --- | --- |
| `production` | `agenda-surat` | hosting tetap memakai sesi yang sudah ada |
| `local` | `agenda-surat-local` | perlu pindai QR sendiri |
| `staging` | `agenda-surat-staging` | perlu pindai QR sendiri |

- Akhiran diatur lewat `WHATSAPP_GATEWAY_DEVICE_SUFFIX`: **baris dikomentari**
  (tidak diisi) = otomatis seperti tabel di atas; diisi nilai (mis. `local`) =
  akhiran tersebut; diisi kosong (`SUFFIX=`) = akhiran dimatikan sehingga semua
  lingkungan kembali berbagi satu sesi.
- Device ID lama yang tersimpan sebagai nama dasar (mis. `agenda-surat`) pada
  database lokal otomatis dibaca sebagai `agenda-surat-local`, sehingga sesi
  hosting tidak tersentuh. Device ID khusus yang pernah diisi manual
  (mis. `agenda-bidang-tik`) tetap dipakai apa adanya.
- Halaman gateway menampilkan peringatan bila device ID ternyata dipakai bersama.

### Lampiran

- **Lampiran opsional** — PDF, JPG, JPEG, PNG, atau WEBP (maksimal 8 MB, dapat
  diubah lewat `WHATSAPP_GATEWAY_ATTACHMENT_MAX_KB`). Isi pesan boleh dikosongkan
  bila hanya mengirim lampiran; bila pesan diisi, teks tersebut menjadi *caption*.
- Lampiran disimpan pada disk publik (`storage/app/public/whatsapp/attachments`)
  lalu diteruskan ke gateway sebagai **JSON** ke `WHATSAPP_GATEWAY_MEDIA_PATH`
  (default `POST /send-media`) dengan field `device_id`, `recipient`,
  `type` (`image`), `url`, `file_path`, dan `caption`.
- **URL lampiran harus dapat diakses dari internet.** Gateway mengunduh berkas
  dari `url`, jadi bila `APP_URL` masih `http://localhost`, gateway akan gagal
  dengan pesan *"Request failed with status code 404"*. Solusi: set `APP_URL`
  ke domain publik **atau** isi `WHATSAPP_GATEWAY_PUBLIC_URL`
  (mis. `https://agenda.domain.go.id`), lalu jalankan `php artisan storage:link`.
  Aplikasi menolak pengiriman lebih awal (dengan pesan jelas) bila URL masih
  mengarah ke localhost/IP privat, dan menampilkan peringatan pada halaman uji.
- `file_path` hanya terpakai bila gateway berada pada server yang sama dengan
  aplikasi (mis. gateway lokal saat pengembangan).
- Gateway Billnesia hanya mendukung `type` **image/video/audio**. Untuk PDF,
  aplikasi otomatis mengirim pesan teks berisi tautan unduhan melalui
  `POST /send` (dapat dimatikan lewat
  `WHATSAPP_GATEWAY_DOCUMENT_LINK_FALLBACK=false`).
- Pratinjau berkas (thumbnail untuk gambar, nama & ukuran untuk PDF) tampil sebelum
  pengiriman, dan riwayat pengujian mencatat nama lampiran.
- Lampiran uji yang lebih lama dari 24 jam dibersihkan otomatis pada pengiriman
  berikutnya.

## Aturan pengembangan

- **Dilarang menghapus isi database** (users, profil instansi, surat masuk/keluar, klasifikasi)
  saat melakukan perbaikan atau perubahan kode. Perbaikan hanya boleh berupa perubahan kode,
  migrasi yang bersifat menambah/mengubah kolom, atau update data — bukan `delete()`/`truncate()`.
- Seeder harus **non-destruktif** (upsert / lewati data yang sudah ada), sehingga aman
  dijalankan ulang tanpa menghilangkan data pengguna.
- Pengujian memakai **SQLite in-memory** (`phpunit.xml`), sehingga tidak menyentuh database
  pengembangan/produksi.
- Untuk verifikasi tampilan, gunakan instance model di memori atau request HTTP biasa
  (tanpa menulis ke database).

## Rencana modul berikutnya

1. Surat masuk (registrasi, disposisi, arsip)
2. Surat keluar (penomoran, template, pengiriman)
3. Surat tugas (penerbitan, dasar penugasan, rekap per pegawai)
4. Agenda kegiatan dan klasifikasi arsip
5. Laporan, manajemen pengguna, dan pengaturan instansi

