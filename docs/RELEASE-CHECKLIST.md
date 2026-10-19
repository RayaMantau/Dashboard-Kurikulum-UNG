# Release checklist dan manual QA

Checklist operator untuk setiap deployment. Kotak kosong berarti belum diverifikasi
pada lingkungan tujuan; automated tests bukan bukti visual/server production.
Catat release ID, tanggal, operator, lingkungan/database engine dan hasil di tiket
release. Panduan teknis: [DEPLOYMENT.md](DEPLOYMENT.md).

## Release gate

- [ ] Full suite dan Pint PASS pada development/CI; catat jumlah tests/assertions.
- [ ] Config/route cache dibersihkan sebelum tests; SQLite :memory: terisolasi.
- [ ] Review source control/history untuk secret (workspace Phase 10 tidak memiliki .git).
- [ ] Tidak ada debug/test route atau runtime dd/dump/credential produksi hardcoded.
- [ ] Dependency terpasang dari lock; platform requirements target PASS.
- [ ] APP_ENV=production, APP_DEBUG=false, APP_URL benar dan HTTPS.
- [ ] APP_KEY unik instalasi tersedia; key lama dipertahankan pada upgrade.
- [ ] Database dan credentials benar, berbeda dari development/test.
- [ ] Backup database + private PDF + konfigurasi/key aman telah dibuat; restore teruji.
- [ ] Maintenance window/writer suspension dan rollback plan disepakati.
- [ ] Migrations selesai; snapshot backfill/preflight dan FK valid.
- [ ] Initial University Admin resmi siap; tidak ada akun/password demo.
- [ ] Tidak menjalankan demo seeders di production/staging atau mengganti APP_ENV untuk bypass.
- [ ] Web root menuju project/public; .env/source/vendor/storage/backup tidak dapat diakses web.
- [ ] storage/, bootstrap/cache/ dan private-documents writable oleh service user, tanpa 777.
- [ ] storage/app/private-documents persistent antar release dan tidak punya public alias/symlink.
- [ ] PHP/web server mengizinkan PDF 10 MiB + multipart overhead.
- [ ] config:cache, route:cache, view:cache PASS; kebijakan optimize:clear/flush cache aman.
- [ ] Log writable, akses terbatas, retensi diatur, debug off.
- [ ] Cookie secure/HttpOnly/SameSite dan proxy HTTPS benar; login/logout berfungsi.
- [ ] Asset CSS/JS tersedia; font atau fallback tampil; tidak ada console/network error.
- [ ] Public portal/search/PDF serta 403/404 diuji pada deployment.
- [ ] Manual QA berikut selesai; release disetujui operator.

## Manual QA — autentikasi dan role

Gunakan data uji staging, dua Fakultas dan dua Prodi, seluruh role. Jangan mengubah
kurikulum resmi production untuk menjalankan skenario destructive atau workflow.

- [ ] Guest menuju /admin diarahkan login; satu login internal untuk semua role.
- [ ] Login benar regenerasi session; salah password/inactive ditolak; throttle bekerja.
- [ ] Logout menutup session; user dinonaktifkan tidak dapat melanjutkan akses internal.
- [ ] Request mutation tanpa CSRF ditolak; production error tidak menunjukkan trace/SQL/path.
- [ ] University Admin kelola Fakultas/Prodi/pengguna; search/filter/pagination dan audit benar.
- [ ] Role/scope user dinormalisasi; forged scope tidak dipercaya; password tidak tampil.
- [ ] Admin tidak bisa menonaktifkan/demote diri sendiri.
- [ ] Faculty Admin hanya monitoring Fakultas sendiri, bukan master/content editor.
- [ ] Admin Prodi/Kaprodi hanya Prodi sendiri; direct URL cross-scope 403.
- [ ] University Admin global monitoring tidak mendapat izin edit konten kurikulum.

## Manual QA — pengelolaan dan workflow

- [ ] Admin Prodi CRUD yang diizinkan untuk Course; ownership tetap dan code unique per Prodi.
- [ ] Curriculum create/edit hanya draft/revision; server-owned fields tidak bisa dipalsukan.
- [ ] Semester number unique; perubahan metadata total semester tidak membuat child otomatis.
- [ ] Attachment Course memakai snapshot code/name; semester/SKS/type sesuai input valid.
- [ ] PDF valid <=10 MiB diterima; non-PDF/fake PDF/oversize ditolak dengan pesan jelas.
- [ ] PDF download aman; missing file controlled 404; tidak ada arbitrary path/public file URL.
- [ ] Upload/delete dokumen locked curriculum ditolak; draft/revision sesuai Policy.
- [ ] Submit membutuhkan metadata, semester lengkap, Course dan Document; status submitted.
- [ ] Kaprodi meminta revisi dengan catatan; Admin Prodi memperbaiki dan resubmit.
- [ ] Kaprodi approve -> approved, lalu publish -> published; role lain ditolak.
- [ ] Publikasi baru mengarsipkan published sebelumnya di Prodi sama saja.
- [ ] Stale/repeated mutations ditolak; audit/notifikasi tepat, tidak berlipat.
- [ ] Published/archived terkunci; snapshot, child dan dokumen historis tetap utuh.
- [ ] Riwayat submission/review/publication dapat dibaca hanya pada scope yang benar.

## Manual QA — publik

- [ ] Homepage, Fakultas, Prodi dan versi kurikulum memakai data nyata dan empty state jelas.
- [ ] Hanya Fakultas/Prodi aktif dan published/archived terlihat; draft/revision/approved/submitted tersembunyi.
- [ ] Manipulasi nested ID/slug menghasilkan 404 tanpa kebocoran data.
- [ ] Semester/Course menampilkan snapshot historis; link PDF lewat streaming aplikasi.
- [ ] Search code/name, filter dan pagination tidak membocorkan scope nonpublik.
- [ ] Back/forward mempertahankan navigasi; active state/breadcrumb/link valid.

## Visual dan accessibility — belum diverifikasi otomatis

- [ ] Desktop, tablet dan mobile (misalnya 360 px) diuji pada browser nyata.
- [ ] Sidebar/topbar/menu mobile, focus keyboard, labels dan error association terbaca.
- [ ] Nama/email/deskripsi panjang membungkus; tabel scroll tanpa overflow seluruh halaman.
- [ ] Status selalu ada teks, brand maroon konsisten; danger berbeda dari primary.
- [ ] Empty/error/success state, pagination, pending form dan pageshow recovery benar.
- [ ] Kontras, zoom 200%, tombol sentuh dan download behavior nyaman digunakan.

## Operasional setelah release

- [ ] Log/error/response time/disk usage dipantau setelah smoke test.
- [ ] Jadwal backup DB + PDF, retensi dan drill restore ditetapkan.
- [ ] Load/performance testing pada kapasitas nyata dijadwalkan bila diperlukan.
- [ ] Concurrency database production dan publication locking diuji di staging.
- [ ] Hasil/tanggung jawab visual QA dan keterbatasan dicatat sebelum go-live.

Large-scale load benchmark bukan hasil Phase 10. Tidak ada deployment remote atau
verifikasi visual yang diklaim oleh HTTP feature tests.
