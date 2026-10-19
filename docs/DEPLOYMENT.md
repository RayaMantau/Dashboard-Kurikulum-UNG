# Deployment — Dashboard Kurikulum UNG

Panduan ini untuk operator dengan akses terminal terkontrol. Tidak ada deployment
remote yang dilakukan dalam Phase 10. Sesuaikan hosting; jangan menjalankan test,
composer setup, composer update, demo seeder atau migrate:fresh di production.

## Environment dan provisioning

Gunakan PHP 8.3+ dengan ekstensi sesuai composer.lock, Composer 2 dan database
terpisah. Jalankan `composer check-platform-reqs --no-dev` pada target. Buat database
dan akun aplikasi dengan hak minimum yang diperlukan; migrasi memerlukan hak DDL.
MySQL harus memakai konfigurasi strict/utf8mb4 yang tersedia di config/database.php.
Uji release di staging dengan engine/version yang sama seperti production.

Web root **harus project/public**, bukan root repository. Blok akses .env, source,
vendor, storage, dotfiles dan backup. Konfigurasikan routing Laravel ke index.php.
HTTPS wajib. Reverse proxy/SSL termination memerlukan trusted proxy dan forwarded
header yang tepat berdasarkan jaringan hosting; tidak ada wildcard proxy baru
pada aplikasi ini. Jangan mengubah trust policy tanpa informasi infrastructure.

Buat .env dari .env.example pada server (atau secret manager). Template adalah
konfigurasi lokal; set minimal:

```dotenv
APP_NAME="Dashboard Kurikulum UNG"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://kurikulum.example.invalid
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dashboard_kurikulum_ung
DB_USERNAME=kurikulum_app
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

Ganti host/URL/user dengan nilai nyata; isi DB_PASSWORD melalui kanal rahasia.
APP_KEY tidak disediakan di repository. Untuk instalasi baru saja jalankan
`php artisan key:generate --force --no-interaction`. Jangan regenerate key pada
upgrade: pertahankan serta backup key secara terenkripsi di luar source control.
DB_URL, bila diatur, dapat mengoverride konfigurasi DB individual; periksa tanpa
mencetak credential ke log. .env tidak boleh dapat dibaca publik.

Session, cache/cache_locks dan jobs/failed_jobs memiliki migrasi. Redis tidak
wajib. Gunakan cache/prefix khusus aplikasi; limiter login memerlukan cache yang
persisten dan shared bila multi-instance. Session domain default null cocok untuk
host tunggal. Sesuaikan cookie/domain/proxy sebelum pengujian HTTPS.

Notifikasi workflow saat ini ditulis sinkron ke database; tidak memerlukan mail
atau queue worker. MAIL_MAILER=log bukan pengiriman email production; jangan
menganggap reset email tersedia. Jika pekerjaan queue ditambahkan kelak, siapkan
worker terkelola dan restart worker saat release. Tidak ada worker baru di fase ini.

Timezone aplikasi aktual UTC (config/app.php). APP_LOCALE default en, tetapi teks
aplikasi/Form Request berbahasa Indonesia. Timestamp tidak diubah ke zona lokal
pada Phase 10. Jangan mengganti timezone diam-diam.

## Storage, permissions dan upload

Disk `documents`: local, private, serve=false, throw=true;
root **storage/app/private-documents**. Path PDF dibuat server dengan UUID.
Nama display/download disanitasi; path internal bukan public URL.

Persist storage antar release. Buat folder private-documents dan beri service user
PHP hak baca/tulis/traverse yang diperlukan. storage/ dan bootstrap/cache/ juga
harus writable. Gunakan ownership/group/ACL sesuai hosting, **bukan chmod 777**.
CLI deployment dan PHP worker harus bisa mengakses file satu sama lain.

Tidak perlu `storage:link` untuk PDF kurikulum. Jangan expose private-documents
melalui alias/symlink web server. Download aplikasi memeriksa scope/status dan
nested ownership, termasuk pada akses publik published/archived.

Batas aplikasi 10 MiB PDF (10240 KiB). Atur PHP upload_max_filesize=10M dan
post_max_size setidaknya 12M atau 16M; request body web server/reverse proxy juga
harus mengizinkan multipart tersebut. Periksa timeout/disk quota. Tidak ada
konfigurasi mesin yang otomatis diubah oleh aplikasi.

Database/filesystem bukan satu transaksi atomic. Upload memiliki cleanup bila
DB/audit gagal. Delete melakukan mutation + audit dalam transaksi DB, lalu
menghapus file pada afterCommit. Bila penghapusan fisik gagal, record sudah
terhapus, file tetap privat dan kegagalan dicatat pada log untuk cleanup operator.
Jangan menganggap rollback DB dapat memulihkan file atau membatalkan afterCommit.
Backup/restore harus memulihkan DB dan file sebagai pasangan konsisten, bukan DB saja.

## Urutan instalasi / upgrade

1. Ambil release yang telah lulus QA. Pada upgrade, backup DB, private PDF dan
   rahasia konfigurasi/key; verifikasi restore pada lingkungan terisolasi.
2. Siapkan maintenance window (`php artisan down --no-interaction` pada aplikasi
   existing) dan hentikan writer lain selama migrasi. Jangan hapus storage release lama.
3. Install dari lock: `composer install --no-dev --optimize-autoloader --no-interaction`.
   `composer check-platform-reqs --no-dev` harus lulus pada server.
4. Siapkan .env, key untuk instalasi baru, storage persistent dan permissions.
   Bersihkan cache konfigurasi lama sebelum memakai environment baru:
   `php artisan optimize:clear --except=cache --no-interaction`.
5. Tinjau `php artisan migrate:status --no-interaction`, lalu
   `php artisan migrate --force --no-interaction`. --force melewati konfirmasi
   production, bukan mengizinkan penghapusan data. Jangan memakai --seed.
6. Buat admin pertama dengan prosedur berikut hanya bila belum ada admin resmi.
7. Bila cache aplikasi khusus release ini boleh dihapus, jalankan
   `php artisan optimize:clear --no-interaction`. Perintah ini juga flush cache
   aplikasi (termasuk limiter); jangan jalankan pada shared cache sembarangan.
   Gunakan --except=cache bila flush tidak diinginkan.
8. Jalankan `php artisan config:cache --no-interaction`,
   `php artisan route:cache --no-interaction`, dan
   `php artisan view:cache --no-interaction`. Semua harus exit 0.
9. Aktifkan release dengan web root public/ dan HTTPS. Pada upgrade jalankan
   `php artisan up --no-interaction`; lakukan smoke test checklist.
10. Monitor log/error/disk dan catat release identifier serta hasil QA.

Layout production memakai asset statis dalam public; tidak memerlukan npm build.
Deploy brand.css/public.css/internal.css dan public.js/internal.js bersama Blade.
Font Inter berasal dari fonts.bunny.net, dengan fallback system font; verifikasi
kebijakan outbound/CSP hosting. Jangan hapus package-lock atau mengganti dependency.

## Initial University Admin (terminal operator saja)

Tidak ada registrasi web atau command bootstrap khusus. Jangan gunakan demo seeder.
Gunakan terminal privat, `php artisan tinker`, lalu tempel closure berikut. Password
diminta lewat hidden prompt, bukan argumen shell/source/history. Jangan aktifkan
session recording yang merekam input rahasia. Validasi gagal harus diperbaiki lalu
ulang; jangan simpan credential literal dalam snippet.

```php
(function (): void {
    $name = \Laravel\Prompts\text('Nama admin', required: true);
    $email = strtolower(trim(\Laravel\Prompts\text('Email admin', required: true)));
    $password = \Laravel\Prompts\password('Password baru (minimal 12 karakter)', required: true);
    $confirmation = \Laravel\Prompts\password('Konfirmasi password', required: true);
    \Illuminate\Support\Facades\Validator::make([
        'name' => $name, 'email' => $email,
        'password' => $password, 'password_confirmation' => $confirmation,
    ], [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(12)],
    ])->validate();
    \App\Models\User::query()->create([
        'name' => $name, 'email' => $email, 'password' => $password,
        'role' => \App\Enums\UserRole::UNIVERSITY_ADMIN,
        'faculty_id' => null, 'study_program_id' => null, 'is_active' => true,
    ]);
    echo "Admin dibuat. Verifikasi login melalui HTTPS.\n";
})();
```

User password memakai cast hashed existing. Jangan mencetak model/password.
Akun bootstrap ini dibuat langsung, bukan melalui UserService, sehingga catat
pelaksana/waktu pada log perubahan operator tanpa credential. Pengguna berikutnya
dikelola melalui Manajemen Pengguna dengan audit normal. Jangan gunakan factory.

## Migration, rollback dan integrity

Tidak ada migration baru pada Phase 10. FK/unique existing melindungi slug
Fakultas/Prodi, (study_program_id, code), (curriculum_id, semester_number) dan
attachment Course per Curriculum. Beberapa FK memakai cascade; jangan hard-delete
master data melalui SQL. Scope nullable pada user tidak memberi akses global.

Snapshot migration backfill course_code/course_name lalu memberlakukan NOT NULL.
Data sumber hilang/tidak valid dapat menghentikan migrasi; lakukan preflight pada
salinan DB, backup dan hentikan writer sepanjang kedua tahap. DDL MySQL dapat
implicit commit. Jangan menganggap seluruh deployment transactional. Down snapshot
migration menghapus identitas historis; jangan menjalankan rollback membabi buta.

Satu published per Prodi dijaga service dengan lock parent dan transaksi, bukan
unique index. Suite SQLite menguji behavior/stale state; kontensi concurrency
MySQL nyata harus diverifikasi pada staging. Rollback release memerlukan strategi
kompatibilitas kode/schema; bila harus restore, pulihkan DB dan file dari backup
konsisten sambil menahan writer. Jangan menimpa history published/archived.

## Logging, security dan release gate

APP_DEBUG=false wajib; detail exception hanya untuk operator pada log terbatas
akses. daily/warning dengan retensi adalah rekomendasi, bukan perubahan config
lokal. Hindari password/token/PDF pada log; pantau kapasitas serta akses log.

Jangan jalankan test di production. CI/development memakai SQLite :memory: dan
RefreshDatabase, dengan config cache dibersihkan dahulu. PHPUnit override env
bukan perlindungan terhadap cached config dari lingkungan lain.

Periksa source control dan history untuk secret sebelum push/release. Audit Phase
10 pada workspace tanpa .git tidak dapat membuktikan isi commit/history. .env.*, DB
SQLite, runtime private uploads, logs/cache dan dependency artifacts harus tetap
ignored. Jika secret pernah committed, hapus dari distribusi dan rotasi credential.

Lakukan checklist release dan manual QA. Visual browser dan load benchmark belum
terverifikasi otomatis; engineering complete tidak berarti telah deployed.
