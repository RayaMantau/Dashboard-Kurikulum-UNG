# Dashboard Kurikulum UNG

Portal informasi kurikulum Universitas Negeri Gorontalo. Pengunjung publik dapat
menelusuri Fakultas, Program Studi, versi kurikulum published/archived, struktur
semester, PDF, serta pencarian mata kuliah tanpa akun.

Internal: pengelolaan master data, kurikulum dan snapshot mata kuliah, dokumen
privat, submission, review/revisi, publikasi, riwayat dan dashboard sesuai scope.
Primary brand: maroon `#7A1F2B`.

## Stack dan kebutuhan

PHP 8.3+, Composer 2, Laravel 13 (versi tepat pada composer.lock), Blade,
CSS/JavaScript statis. Ekstensi PHP harus memenuhi `composer check-platform-reqs`;
PDO driver database, fileinfo, mbstring dan ekstensi Laravel diperlukan.
SQLite dipakai oleh test; production perlu database yang dikonfigurasi dan diuji
operator. Laravel mendukung konfigurasi MySQL di repository ini.

Layout production memakai public/brand.css, public/public.css, public/internal.css,
public/public.js dan public/internal.js langsung. Tidak perlu npm build untuk
halaman aplikasi saat ini. Vite/Tailwind skeleton tetap tersedia; bila mengubah
entry Vite gunakan `npm ci` dan `npm run build` dengan versi Node yang memenuhi
package-lock.json. Inter dimuat dari Bunny Fonts dengan system-font fallback.

## Instalasi lokal

1. Salin repository dan jalankan `composer install`.
2. Salin `.env.example` menjadi `.env`; sesuaikan APP_URL dan database lokal.
3. Jalankan `php artisan key:generate --no-interaction` sekali untuk instalasi baru.
4. Untuk SQLite buat file kosong `database/database.sqlite` jika belum ada.
5. Jalankan `php artisan migrate --no-interaction`.
6. Jalankan `php artisan serve --no-interaction`; buka URL yang ditampilkan.

Jangan memakai database production untuk pengembangan atau tests. Jangan
menjalankan migrate:fresh pada database yang memiliki data penting.

## Data demo — DEVELOPMENT ONLY, DO NOT USE IN PRODUCTION

Opsional, hanya pada database lokal kosong: `php artisan db:seed --no-interaction`.
Seeder tidak idempotent; pengulangan dapat gagal karena unique constraints.
Kedua entry seeder menolak environment selain local/testing, termasuk --force.
Jangan mengganti APP_ENV di production untuk melewati guard.

DashboardKurikulumSeeder membuat Fakultas Teknik, Prodi Teknik Sipil, kurikulum
draft, semester dan mata kuliah; tidak menyediakan PDF siap submission.
Akun contoh berikut semuanya memakai password `password` dan **hanya untuk demo**:

| Email | Tujuan |
|---|---|
| admin.universitas@example.test | Master data dan pengguna |
| admin.fakultas@example.test | Monitoring Fakultas |
| admin.prodi@example.test | Pengelolaan kurikulum Prodi |
| kaprodi@example.test | Review dan publikasi Prodi |

DatabaseSeeder juga membuat Test User (`test@example.com`) melalui factory.
Jangan mengirim database demo ke production. Admin production pertama dibuat
melalui prosedur terminal terkontrol di [deployment guide](docs/DEPLOYMENT.md).

## Role dan keamanan

- Admin Universitas: master Fakultas/Prodi/pengguna dan monitoring global.
- Admin Fakultas: monitoring Fakultas sendiri.
- Admin Prodi: mata kuliah dan kurikulum Prodi sendiri.
- Kaprodi: review, revisi, persetujuan dan publikasi Prodi sendiri.

Global monitoring bukan izin editing kurikulum. Semua akun internal wajib aktif.
Satu login `/login`; entry internal `/admin`. Tidak ada registrasi publik.
Policy, validasi scope dan workflow service tetap menjadi batas keamanan.

PDF maksimal 10 MiB disimpan pada disk documents di storage/app/private-documents.
Akses hanya melalui route download aplikasi; jangan buat symlink publik ke folder
tersebut. Backup harus mencakup database dan PDF bersama-sama.

## Validasi (development/CI saja)

Bersihkan config/route cache sebelum tests. phpunit.xml memaksa SQLite :memory:,
cache/session array dan queue sync; jangan menjalankan tests di server production.
RefreshDatabase memverifikasi migrasi dari kosong tanpa menghapus database lokal.

```sh
php artisan optimize:clear --except=cache --no-interaction
php artisan test --compact tests/Feature/ReleaseReadinessTest.php
php vendor/bin/pint --format agent
node --check public/internal.js
node --check public/public.js
php artisan test --compact
```

## Deployment dan QA

Baca [DEPLOYMENT.md](docs/DEPLOYMENT.md) dan
[RELEASE-CHECKLIST.md](docs/RELEASE-CHECKLIST.md), termasuk manual QA per role.
APP_ENV=production, APP_DEBUG=false, HTTPS, web root public/, izin file yang tepat,
initial admin aman dan backup merupakan persyaratan release.

## Troubleshooting

- Perubahan .env belum berlaku: rebuild config cache pada release yang benar.
- 419: periksa HTTPS/cookie/session, expiry, CSRF dan konfigurasi proxy.
- 403: periksa akun aktif, role, assignment dan status kurikulum; jangan bypass Policy.
- 404 download: periksa eligibility publik/nested ownership dan keberadaan file privat.
- Upload gagal: periksa izin documents disk, fileinfo dan batas PHP/web server.
- Perubahan CSS/JS belum terlihat: refresh browser/CDN cache; asset portal saat ini statis.
- Vite error pada view yang memakai Vite: jalankan npm ci/npm run build.
- Jangan aktifkan APP_DEBUG di production untuk diagnosis; gunakan log terbatas akses.

## Status

Phase 1–9 selesai. Hasil final Phase 10 dan batas verifikasinya dicatat di
[development plan](docs/DEVELOPMENT-PLAN.md). Engineering readiness bukan bukti
aplikasi sudah deployed: checklist server, visual QA dan operasional harus
diselesaikan oleh operator pada lingkungan tujuan.
