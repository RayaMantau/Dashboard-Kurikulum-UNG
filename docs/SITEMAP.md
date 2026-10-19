# SITEMAP --- Dashboard Kurikulum UNG

## Public — production Phase 8A–8H

Phase 8H memverifikasi sepuluh route publik di bawah sebagai GET/HEAD, tanpa
auth middleware atau mutation domain. Published/archived hanya tersedia jika
Prodi dan Faculty aktif; login internal tidak memperluas visibilitas. Semua
pasangan nested yang salah menghasilkan 404. Homepage current tetap published-only.

- `GET /search` (`search`): pencarian snapshot mata kuliah pada Curriculum
  published/archived dengan Prodi dan Faculty aktif. Filter `q`, `faculty` (slug),
  `study_program` (slug), dan `year` digabung AND. Satu hasil per attachment,
  pagination 15, link menuju `study-programs.curricula.semesters.show`.
  Tanpa input bermakna tampil petunjuk; filter tidak valid menghasilkan hasil kosong.

- `GET /prodi/{studyProgram:slug}/kurikulum/{curriculum}/dokumen/{document}/download`
  (`study-programs.curricula.documents.download`): PDF published/archived dengan
  parent aktif. Salah nested ownership, status privat, parent nonaktif, file hilang
  atau path tidak aman = 404. File tetap privat; tidak ada URL storage langsung.

- `GET /prodi/{studyProgram:slug}/kurikulum/{curriculum}/semester/{semester}`
  (`study-programs.curricula.semesters.show`): semester dan attachment snapshot.
  Mewarisi visibilitas Curriculum; salah pasangan Prodi/Curriculum/Semester = 404.
  Detail Curriculum sendiri menjadi overview semua semester/mata kuliah inline.

- `GET /prodi/{studyProgram:slug}/kurikulum` (`study-programs.curricula.index`):
  satu published aktif dan riwayat archived dengan pagination terpisah.
- `GET /prodi/{studyProgram:slug}/kurikulum/{curriculum}` (`study-programs.curricula.show`):
  detail metadata published/archived memakai ID Curriculum. Status privat,
  Curriculum milik Prodi lain, atau Prodi/Faculty nonaktif menghasilkan 404.

- `GET /prodi` (`study-programs.index`): Prodi aktif di Faculty aktif, filter
  `?faculty={faculty-slug}`, pagination 12 dengan filter dipertahankan.
- `GET /prodi/{studyProgram:slug}` (`study-programs.show`): profil dan ringkasan
  published saja. Prodi/Faculty nonaktif atau slug tidak dikenal = 404.

- `GET /`, named route `home`: beranda publik tanpa syarat login, tersedia bagi
  guest maupun pengguna internal yang sudah login.
- `GET /fakultas`, named route `faculties.index`: direktori Faculty aktif,
  12 per halaman dengan jumlah Prodi aktif.
- `GET /fakultas/{faculty:slug}`, named route `faculties.show`: detail Faculty
  aktif dan Prodi aktif miliknya; Faculty nonaktif atau slug tidak dikenal = 404.
- Guest dapat membuka `/login` melalui **Masuk**. Pengguna yang sudah login
  mendapat link **Dashboard Internal** ke `/admin`; proteksi internal tetap berlaku.

### Roadmap public — belum diimplementasikan

Daftar berikut adalah rencana, bukan route production saat ini. Preview Faculty
pada beranda menuju detail slug; kartu Prodi pada Faculty kini menuju detail Prodi.
Detail Prodi menyediakan link ke versi Curriculum jika ada published/archived.
Kartu versi menuju detail metadata. Preview Curriculum homepage belum memiliki
link detail. Halaman Faculty tidak menampilkan workflow.

-   `/mata-kuliah/{course}` (rancangan lama, tidak diimplementasikan Phase 8E;
    identitas historis ditampilkan dari attachment, bukan halaman global master)

Pencarian mata kuliah tersedia sejak 8G; integrasi dan hardening Phase 8H selesai.
Phase 9/10 belum dinyatakan selesai. Detail master Course standalone tetap deferred.
Upload/delete dokumen tetap hanya melalui area internal.

## Riwayat Internal Bersama (Phase 7I)

-   `/internal/kurikulum/{curriculum}/riwayat` (GET read-only; `internal.curricula.history`)
-   Memakai scope view Curriculum; tidak membuka group management `/admin` bagi role lain.

## Admin Prodi

-   `/admin`
-   `/admin/kurikulum`
-   `/admin/kurikulum/create`
-   `/admin/kurikulum/{curriculum}`
-   `/admin/kurikulum/{curriculum}/edit`
-   `/admin/kurikulum/{curriculum}/submit` (GET kesiapan/konfirmasi, POST pengajuan review)
-   `/admin/kurikulum/{curriculum}/semester`
-   `/admin/kurikulum/{curriculum}/semester/tambah`
-   `/admin/kurikulum/{curriculum}/semester/{semester}/edit`
-   `/admin/kurikulum/{curriculum}/mata-kuliah` (GET index, POST store)
-   `/admin/kurikulum/{curriculum}/mata-kuliah/tambah` (GET create)
-   `/admin/kurikulum/{curriculum}/mata-kuliah/{curriculumCourse}/edit` (GET edit)
-   `/admin/kurikulum/{curriculum}/mata-kuliah/{curriculumCourse}` (PUT/PATCH update, DELETE attachment saja)
-   `/admin/kurikulum/{curriculum}/dokumen` (GET index, POST upload)
-   `/admin/kurikulum/{curriculum}/dokumen/tambah` (GET form upload)
-   `/admin/kurikulum/{curriculum}/dokumen/{document}/download` (GET download internal)
-   `/admin/kurikulum/{curriculum}/dokumen/{document}` (DELETE dokumen saat editable)
-   `/admin/kurikulum/{curriculum}/history`
-   `/admin/pengajuan`
-   `/admin/notifikasi`
-   `/admin/profile`

## Kaprodi

-   `/kaprodi`
-   `/kaprodi/pengajuan`
-   `/kaprodi/pengajuan/disetujui` (GET antrean publikasi, sebelum binding Curriculum)
-   `/kaprodi/pengajuan/{curriculum}/publish` (GET konfirmasi, POST publikasi)
-   `/kaprodi/pengajuan/{curriculum}`
-   `/kaprodi/pengajuan/{curriculum}/approve` (POST persetujuan, bukan publikasi)
-   `/kaprodi/pengajuan/{curriculum}/revision` (POST permintaan revisi)
-   `/kaprodi/pengajuan/{curriculum}/dokumen/{document}/download` (GET privat)
-   `/kaprodi/riwayat`
-   `/kaprodi/notifikasi`
-   `/kaprodi/profile`

## Admin Fakultas

-   `/faculty-admin`
-   `/faculty-admin/program-studi`
-   `/faculty-admin/kurikulum`
-   `/faculty-admin/monitoring`
-   `/faculty-admin/riwayat`

## Admin Universitas

-   `/university-admin`
-   `/university-admin/fakultas`
-   `/university-admin/program-studi`
-   `/university-admin/kurikulum`
-   `/university-admin/users`
-   `/university-admin/monitoring`
-   `/university-admin/riwayat`
-   `/university-admin/settings`

## Navigasi Public

Phase 8B: **Beranda** menuju `home`; **Fakultas** menuju `faculties.index`, dengan
active state pada direktori/detail. Sejak Phase 8C **Program Studi** aktif menuju
`study-programs.index`; sejak Phase 8G **Cari Mata Kuliah** aktif menuju `search`.
**Masuk** menuju `/login` untuk guest; **Dashboard Internal** menuju `/admin`
untuk pengguna yang sudah login.

Pada lebar maksimal 960px, JavaScript menyediakan tombol menu buka/tutup.
Tanpa JavaScript, navigasi tetap terlihat. Beranda menyediakan CTA pencarian.
Hasil pencarian memakai snapshot dalam konteks Curriculum/Semester; tidak ada
halaman detail global master Course.
