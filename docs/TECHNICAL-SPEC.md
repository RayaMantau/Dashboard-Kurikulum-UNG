# TECHNICAL-SPEC --- Dashboard Kurikulum UNG

## 1. Stack

-   Laravel 13
-   PHP
-   MySQL
-   Laragon
-   Blade
-   Laravel Authentication
-   Policies/Gates
-   Laravel Filesystem
-   MVC + Service Layer

## 2. Architecture

``` text
app/
├── Enums/
│   ├── UserRole.php
│   ├── CurriculumStatus.php
│   └── CourseType.php
├── Models/
├── Policies/
├── Http/
│   ├── Controllers/
│   │   ├── Public/
│   │   ├── Admin/
│   │   ├── Kaprodi/
│   │   ├── FacultyAdmin/
│   │   └── UniversityAdmin/
│   └── Requests/
└── Services/
    ├── Curriculum/
    │   ├── CurriculumService.php
    │   ├── CurriculumSubmissionService.php
    │   └── CurriculumPublicationService.php
    ├── Document/
    │   └── DocumentService.php
    └── Audit/
        └── AuditLogService.php
```

### Public homepage — foundation Phase 8A, integrasi Faculty Phase 8B

`routes/web.php` mendefinisikan `GET /` bernama `home`, menuju invokable
`App\Http\Controllers\HomeController`. Route tidak mensyaratkan autentikasi;
guest dan pengguna internal yang sudah login mendapat beranda publik yang sama.
Link Dashboard Internal tetap menuju route internal yang dilindungi middleware.
Controller aktual berada langsung di `Controllers/HomeController.php`; direktori
`Controllers/Public/` pada diagram arsitektur adalah rancangan, bukan lokasi
controller beranda ini.

View `resources/views/public/home.blade.php` memakai
`resources/views/layouts/public.blade.php`, dengan komponen reusable
`resources/views/components/public/header.blade.php` dan `footer.blade.php`.
Layout memuat `public/public.css` dan `public/public.js` melalui helper `asset()`;
JavaScript dimuat dengan `defer`, hanya menangani navigasi responsif tanpa framework.
Layout publik terpisah dari shell/composer internal. Inter dimuat melalui Bunny
Fonts dengan fallback system sans-serif.

Semua query homepage berada di HomeController, bukan Blade. Semantik agregat:

| Label | Kondisi database |
|---|---|
| Fakultas Aktif | `faculties.is_active = true` |
| Program Studi Aktif | `study_programs.is_active = true` dan Faculty terkait aktif |
| Kurikulum Aktif | `curricula.status = published`, Study Program terkait aktif, dan Faculty terkait aktif |

Ketiga angka memakai database `count()`, tidak menghitung collection preview.
Scope Prodi memakai `whereHas('faculty', ...)`; Curriculum dibatasi dengan
subquery ID Prodi aktif di Faculty aktif. Parameter request tidak memperluas
visibilitas. Tidak ada caching atau query yang memuat seluruh tabel untuk dihitung.

Preview Faculty mengambil maksimal enam record aktif, urut `name ASC, id ASC`,
dengan kolom `id`, `name`, `code`, `slug` (slug ditambahkan Phase 8B untuk URL).
Preview Curriculum mengambil maksimal tiga
record published dengan parent aktif, urut `published_at DESC, id DESC`, dengan
kolom `id`, `study_program_id`, `name`, `year`. Relasi `studyProgram` dan Faculty
di-eager-load dengan kolom identitas/nama yang diperlukan; Blade hanya menampilkan
nama, tahun, nama Prodi/Faculty dan badge Dipublikasikan, tanpa query per kartu.
ID dipakai untuk relasi, tidak ditampilkan sebagai informasi publik. Batas preview
tidak membatasi nilai agregat statistik.

Visibilitas memakai allowlist state publik: `draft`, `submitted`, `revision`,
dan `approved` tidak masuk count/preview. `archived` bersifat historis dan tidak
masuk kurikulum aktif; akses detail historis tersedia melalui Phase 8D. Faculty/Prodi nonaktif
serta published Curriculum di bawah parent nonaktif tidak ditampilkan.

GET beranda hanya membaca data domain. Tidak membaca/menampilkan AuditLog,
catatan CurriculumReview, Notifications, path file dokumen, atau identitas
pengunggah. Tidak menampilkan status internal, deskripsi internal Curriculum,
atau link/content management selain link Dashboard Internal yang sengaja tersedia
bagi pengguna yang sudah login. Output data memakai escaping Blade.

Navigasi aktual: Beranda ke `home`, Masuk ke `login` untuk guest, Dashboard Internal
ke `dashboard` (`/admin`) untuk pengguna yang sudah login. Sejak Phase 8B, Fakultas
dan CTA **Jelajahi Fakultas** menuju `faculties.index`; preview Faculty menuju
`faculties.show` dengan slug. Program Studi dan Cari Mata Kuliah masih teks non-link
**Segera** pada Phase 8B; sejak Phase 8C Program Studi aktif menuju
`study-programs.index`. Route detail/versi Curriculum tersedia sejak 8D.
Struktur semester/mata kuliah tersedia sejak 8E; download dokumen sejak 8F.
Search tersedia sejak 8G melalui navbar dan CTA beranda menuju `search`.
Kartu preview Curriculum tidak memiliki link detail.

`tests/Feature/PublicHomepageTest.php` memeriksa akses guest/internal, empty state,
semantik hitungan dan parent aktif, batas/urutan preview, tidak bocornya data
internal, navigasi, escaping, query stabil, dan GET tanpa mutasi domain.

### Public Faculty — production Phase 8B

`PublicFacultyController` berada di namespace controller root mengikuti
HomeController. Route tanpa auth: `GET /fakultas` (`faculties.index`) dan
`GET /fakultas/{faculty:slug}` (`faculties.show`). Binding slug hanya untuk route
publik; model tidak mengganti route key global, sehingga binding ID internal tetap.
Setelah binding, `show()` menolak Faculty nonaktif dengan 404 sebelum mengambil
Prodi. Slug tidak dikenal juga 404. Guest maupun pengguna internal dapat membaca.

Index memilih Faculty aktif, kolom identitas/deskripsi yang diperlukan, dan
`withCount('studyPrograms')` yang dibatasi `is_active = true`, urut `name ASC,
id ASC`, pagination database 12 record. Detail mengambil Prodi aktif dari relasi
Faculty terpilih dalam satu query, urut `name ASC, id ASC`, hanya nama, kode,
degree, accreditation. Jumlah detail dihitung controller dari hasil yang memang
ditampilkan, tanpa query per kartu atau query Blade. Tidak ada agregat Curriculum.

View `public/faculties/index.blade.php` dan `show.blade.php` memakai layout publik,
komponen `public.breadcrumbs` dan `public.pagination`, serta `public.css` existing.
Deskripsi dan nama memakai escaping Blade; field internal, workflow, dokumen,
audit dan aktor tidak ditampilkan. GET tidak memutasi data domain. Sejak Phase 8C,
Prodi memiliki link detail slug. Tidak ada dependency atau migration baru.

`PublicFacultyTest` mencakup akses guest/internal, filtering parent/Prodi, count,
404, slug, empty state, pagination deterministik, escaping, integrasi homepage,
query tanpa N+1, serta tidak adanya query/kebocoran/mutasi data workflow.

### Public Study Program — production Phase 8C

`PublicStudyProgramController` melayani `GET /prodi` (`study-programs.index`) dan
`GET /prodi/{studyProgram:slug}` (`study-programs.show`) tanpa auth middleware.
Binding slug khusus publik; binding ID internal tetap. Visibilitas mensyaratkan
Prodi aktif dan Faculty aktif. Detail memeriksa kedua kondisi sebelum mengambil
Curriculum; hidden/unknown menghasilkan 404.

Index eager-load Faculty dan memakai `withExists` constrained published untuk
indikator ketersediaan tanpa query per kartu. Urut nama Prodi ASC lalu ID ASC,
mengikuti konvensi Phase 8B. Pagination 12 mempertahankan `faculty` slug.
Pilihan filter hanya Faculty aktif, diurutkan nama/ID. Filter unknown, inactive,
atau bentuk non-string menghasilkan daftar kosong, bukan scope global.
Tidak ada search tambahan.

Detail mengambil satu Curriculum `PUBLISHED`, urut `published_at DESC, id DESC`
mengikuti homepage; hanya nama, year, effective_year, total_sks, total_semesters.
Jika data lama memiliki beberapa published, pemilihan tetap deterministik tanpa
repair melalui GET. Draft/submitted/revision/approved/archived tidak pernah
dianggap current meskipun tahunnya lebih baru. Tanpa published tampil empty state.
Tidak ada query dokumen, audit, review, notifikasi atau aktor.

Website di CRUD sudah divalidasi `url:http,https`; controller publik memeriksa
ulang URL valid dan allowlist scheme HTTP/HTTPS untuk data lama. URL lain
disembunyikan. Link memakai escaping dan `rel="noopener noreferrer"`; deskripsi
juga escaped. View `public/study-programs/index.blade.php` dan `show.blade.php`
memakai shell/breadcrumb/pagination publik, yang kini menerima label jenis data.
Navbar Program Studi aktif, kartu Prodi pada Faculty memiliki link slug.
Sejak Phase 8D, detail Prodi menambahkan link daftar versi bila current ada atau
query archived `exists()` bernilai true. Struktur tersedia sejak 8E, dokumen sejak
8F; search tersedia sejak 8G.
Tidak ada perubahan schema atau workflow.

### Public Curriculum — production Phase 8D

`PublicCurriculumController` melayani route publik tanpa auth:
`GET /prodi/{studyProgram:slug}/kurikulum` (`study-programs.curricula.index`) dan
`GET /prodi/{studyProgram:slug}/kurikulum/{curriculum}` (`study-programs.curricula.show`).
Curriculum tidak memiliki slug; ID adalah identitas versi, bukan tahun yang
tidak unique. Binding internal tidak berubah.

Guard publik eksplisit mensyaratkan Prodi aktif dengan Faculty aktif, status
Curriculum published/archived, serta `curriculum.study_program_id` sama dengan
Prodi pada URL. Pelanggaran/ID tidak dikenal menghasilkan 404, bukan 403.
Internal CurriculumPolicy::view tidak dipakai pada route publik; login atau role
internal tidak membuka draft/submitted/revision/approved melalui URL publik.

Index mengambil satu published dengan `published_at DESC, id DESC`, mengikuti
Phase 8C. Duplikasi legacy tidak diperbaiki dari GET dan tidak ditampilkan semua
sebagai current. Detail ID published lain tetap tunduk pada allowlist yang sama.
Arsip valid sebagai versi historis, bukan master nonaktif: query archived dibatasi
ke Prodi, urut `archived_at DESC, year DESC, id DESC`, pagination 12 terpisah dari
current. Nullable timestamp tidak menjadi syarat visibilitas; status adalah sumber
kebenaran. Index hanya memilih kolom metadata kartu. Detail hanya memuat relasi
Faculty dari Prodi; sejak Phase 8E juga semester dan attachment snapshot,
tanpa graph master Course/review. Sejak 8F metadata Document dipilih terpisah,
tanpa uploader/path.

View `public/curricula/index.blade.php` dan `show.blade.php` memakai layout,
breadcrumb, pagination publik dan komponen `public.curriculum-card`. Label hanya
Kurikulum Aktif/Arsip. Deskripsi di-escape; timestamp workflow, creator/updater,
review, audit, notification, file path tidak ditampilkan. Total SKS/semester adalah
metadata tersimpan, bukan perhitungan attachment. GET tidak menulis data domain
atau mengubah snapshot. UI dokumen tersedia sejak 8F; struktur sejak 8E.

`PublicCurriculumTest` memeriksa status untuk guest/semua role internal, parent
aktif, nested ownership, empty/archive-only, ordering/pagination, duplicate published,
escaping, integrasi Prodi, serta preservasi child records dan tidak adanya query
ke tabel privat/master Course pada GET. Tidak ada migration atau dependency baru.

### Public structure — production Phase 8E

PublicCurriculumController::show memuat semester `semester_number ASC, id ASC`
dengan eager-loading curriculumCourses. Method `semester()` melayani
`GET /prodi/{studyProgram:slug}/kurikulum/{curriculum}/semester/{semester}`,
bernama `study-programs.curricula.semesters.show`. Keduanya memakai guard publik
yang sama: published/archived, Prodi/Faculty aktif, Curriculum milik Prodi pada URL.
Halaman semester juga memeriksa semester.curriculum_id. Pelanggaran = 404 untuk
guest maupun role internal; tidak menggunakan Policy internal untuk membuka status privat.

Query attachment dibatasi semester serta curriculum_id yang cocok, urut
`course_code ASC, id ASC`. Select hanya ID pencocokan relasi dan field presentasi
snapshot/SKS/type/prasyarat. Tidak eager-load atau membaca master Course.
CurriculumCourse.course_code/course_name adalah identitas historis otoritatif;
Course.code/name/description mutable tidak ditampilkan sebagai sejarah.
FK course_id tetap cascade existing; tidak ada penghapusan master melalui fase ini.

Tidak memfilter attachment.is_active, mengikuti daftar Phase 7D yang mempertahankan
semua attachment tersimpan. Flag tidak ditafsirkan sebagai visibilitas publik baru.
CourseType::label() existing menghasilkan Wajib/Pilihan. Prasyarat nonblank di-escape.
Semester kosong tetap dimuat; Curriculum tanpa semester tetap 200 dengan empty state.
Semester.total_sks dan Curriculum.total_sks tetap metadata manual, sementara SKS
mata kuliah dari attachment. Tidak ada rekalkulasi, sinkronisasi, atau validasi sum.

View detail Curriculum dan `public/curricula/semester.blade.php` memakai komponen
`public.course-list`. Tidak ada global Course detail/directory atau semester index
baru. PublicCurriculumStructureTest memeriksa snapshot setelah master berubah,
arsip, seluruh private status, parent/nested ownership, attachment mismatch,
urutan, empty states, escaping, label, query growth konstan, dan GET read-only.
Dokumen tersedia sejak 8F; search tersedia sejak 8G. Tidak ada perubahan schema, dependency,
atau workflow.

### Public documents — production Phase 8F

PublicCurriculumController menambahkan `download()` untuk
`GET /prodi/{studyProgram:slug}/kurikulum/{curriculum}/dokumen/{document}/download`
(`study-programs.curricula.documents.download`). Controller meneruskan model
binding ke DocumentService::downloadForPublic; entry point service memeriksa Prodi
aktif, Faculty aktif, status published/archived, Curriculum milik Prodi dan
Document milik Curriculum. Semua pelanggaran = 404 untuk guest maupun internal.
Policy internal tidak membuka dokumen status privat melalui URL publik.

Service memakai streamDocument existing bersama download Admin Prodi/Kaprodi:
disk `documents`, root `storage/app/private-documents`, visibility private,
serve=false. Path harus cocok `curricula/{curriculum_id}/documents/{uuid}.pdf`
sesuai whitelist existing, tidak menerima input path dari request. readStream
gagal/non-resource menghasilkan controlled 404. Response streamDownload memakai
application/pdf, nosniff, private/no-store dan safeFilename existing untuk mencegah
path/control character pada filename/header. Tidak memuat seluruh file ke memori,
tidak memindahkan file ke public disk, tidak membuat storage symlink atau URL.

Detail Curriculum mengambil id/title/file_name/file_size dokumen melalui satu
query, `id DESC` mengikuti listing internal. Filename display memakai sanitasi
safeFilename yang sama (hanya model in-memory, tidak disimpan ulang). Tidak memuat
uploader, file_path atau melakukan stat filesystem per record. File hilang boleh
tetap terdaftar; download menolak dengan 404. UI tetap PDF-only sesuai upload 7E,
dengan ukuran KB/MB atau keterangan bila null. Tidak ada download audit atau mutasi.
Upload/delete tetap internal; tidak ada preview PDF atau public upload.

PublicCurriculumDocumentTest mencakup published/archived, semua private status
dan role internal, parent/nested ownership, file hilang, path unsafe, forged query
path, filename/header/escaping, privasi uploader, GET read-only dan query growth.
Regresi mencakup CurriculumDocumentManagementTest dan CurriculumReviewManagementTest.
Search tersedia sejak Phase 8G; tidak ada migration/dependency/perubahan aturan internal.

### Public Course search — production Phase 8G

`GET /search`, named route `search`, menuju invokable `PublicSearchController`
tanpa auth middleware. `public/search.blade.php` memakai shell, breadcrumbs,
card dan pagination publik existing; navbar dan CTA beranda menuju route ini.

Sumber hasil adalah CurriculumCourse.course_code/course_name (snapshot), bukan
Course master. Satu attachment adalah satu kemunculan: Course yang sama pada dua
versi tetap dua hasil. Query join ke Curriculum, Prodi, Faculty dan Semester untuk
filter serta ordering SQL. Semester harus cocok dengan curriculum_id attachment;
data dengan pasangan salah dikeluarkan agar link hasil memiliki konteks valid.
Allowlist tetap published/archived dengan Prodi aktif DAN Faculty aktif untuk
guest maupun internal yang login. Input status tidak dapat memperluas allowlist.
Tidak ada filter attachment.is_active, sesuai tampilan struktur Phase 8E.

Input hanya `q`, `faculty`, `study_program`, `year`, berupa string maksimal 255
karakter, di-trim. q mencari kode OR nama dalam grup query yang tetap dibatasi
scope publik. whereLike memakai bound parameters, pencarian tidak peka kapital
pada konfigurasi database proyek. `%` dan `_` tetap wildcard LIKE, bukan literal;
tidak ada interpolasi input menjadi SQL. Tidak ada full-text engine/index baru.

Faculty/Prodi memakai slug; tahun integer 0–65535 mengikuti schema. Semua filter
digabung AND. Filter unknown/inactive, mismatch Faculty/Prodi, array, tahun salah
atau input terlalu panjang menghasilkan 200 dengan hasil kosong. Filter-only
diperbolehkan; tanpa input bermakna tidak menjalankan query CurriculumCourse,
hanya menyiapkan pilihan filter dan petunjuk. Tidak ada dump semua hasil default.

Pilihan Faculty hanya aktif; pilihan Prodi hanya aktif di Faculty aktif dan
dibatasi Faculty terpilih bila ada. Pilihan tahun DISTINCT DESC hanya berasal
dari Curriculum publik dengan parent aktif (termasuk versi tanpa attachment).
Urutan hasil: published dahulu, Curriculum.year DESC, Faculty.name ASC,
StudyProgram.name ASC, Semester.semester_number ASC, snapshot course_code ASC,
CurriculumCourse.id ASC. Pagination database 15 mempertahankan empat parameter.

Select attachment hanya ID relasi dan field presentasi. Relasi Curriculum,
StudyProgram, Faculty dan Semester di-eager-load dengan kolom terbatas; master
Course, audit, review, aktor, notifikasi dan dokumen tidak dimuat. Tidak ada query
Blade atau query per kartu. GET tidak memutasi data atau membuat audit.
Hasil memakai label Kurikulum Aktif/Arsip dan CourseType::label(), seluruh teks
di-escape. Link `study-programs.curricula.semesters.show` memakai slug Prodi,
ID Curriculum dan ID Semester yang cocok. Tidak ada global Course master detail.

PublicCourseSearchTest mencakup snapshot, allowlist seluruh status/role, parent
nonaktif, filter AND dan filter-only, opsi aman, input invalid, urutan, pagination,
tautan nested, XSS, query count tetap dan read-only. LIKE dengan leading wildcard
dapat memerlukan scan pada data besar; optimasi perlu pengukuran terpisah.
Phase 8H memverifikasi integrasi portal; tidak ada perubahan workflow/schema/dependency.

### Public integration and hardening — Phase 8H

Kontrak kanonis: published/archived DAN StudyProgram aktif DAN Faculty aktif.
Homepage current/preview hanya published. Query eksplisit pada HomeController,
PublicFacultyController, PublicStudyProgramController, PublicCurriculumController,
PublicSearchController dan guard DocumentService dipertahankan; tidak ada scope
baru yang mengubah otorisasi internal. Guest dan internal memakai guard publik sama.

Sepuluh route portal hanya GET/HEAD. Binding slug Faculty/Prodi tetap khusus route
publik; Curriculum/Semester/Document memakai ID dengan pemeriksaan nested ownership.
Mismatch, parent nonaktif, status privat, file unsafe/hilang menghasilkan 404.
Dokumen tetap melalui private streaming, nosniff dan private/no-store; listing
tidak memeriksa filesystem per file. Tidak ada perubahan permission internal.

PublicPortalIntegrationTest menelusuri alur seluruh route untuk published/archived,
memeriksa link production, snapshot setelah master berubah, privasi uploader/path,
header/isi download, preservasi sepuluh tabel domain, serta metode route tanpa auth.
Matriks lintas endpoint mencakup perubahan seluruh status, pencabutan akses saat
parent nonaktif, dan wrong nesting untuk guest/University Admin. Duplicate published
memakai published_at DESC, id DESC konsisten pada ringkasan Prodi/versi/homepage;
GET tidak memperbaiki data. Test per-fitur tetap mencakup empty states, escaping,
safe website, invalid file paths, filter/pagination dan query count tanpa N+1.

Audit source tidak menemukan raw Blade output atau rendering data workflow privat.
Eager loading/count/exists dan pagination existing dipertahankan. Jumlah query
tidak bertambah per kartu; detail satu Curriculum tetap memuat seluruh struktur/
dokumennya, detail Faculty seluruh Prodi aktifnya, dan opsi search seluruh unit
aktif. Batas volume ini mengikuti MVP, bukan klaim load testing skala besar.
LIKE wildcard tetap seperti Phase 8G, tanpa full-text engine atau cache baru.

Verifikasi visual tidak dilakukan karena capability browser tidak tersedia.
Deployment production tetap harus memakai APP_DEBUG=false; audit ini tidak mengubah
konfigurasi environment atau middleware global. Phase 9/10, notification center,
comparison, Excel, API, analytics, CPL/CPMK/RPS dan standalone Course master detail
tidak diimplementasikan oleh Phase 8H.

## 3. State Machine

Allowed: - draft → submitted - submitted → revision - submitted →
approved - revision → submitted - approved → published - published →
archived

Invalid: - draft → published - published → draft - archived → published

## 4. Publication Rule

Saat publish: 1. start DB transaction 2. cari curriculum published lama
untuk Prodi 3. archive curriculum lama jika ada 4. publish curriculum
baru 5. set published_at 6. update audit log 7. create notifications 8.
commit

Jika gagal, rollback.

## 5. Submit Validation

Wajib: - informasi curriculum lengkap - struktur semester tepat 1..total_semesters - minimal
satu course - total SKS konsisten - dokumen resmi tersedia

Jika gagal, tampilkan field/requirement yang kurang.

Phase 7C: SemesterService mengunci actor, curriculum, lalu semester sebelum
mutasi; CurriculumPolicy::update tetap otoritatif untuk scope/status. Nomor
semester diperiksa terhadap batas pada curriculum terkunci dan unique per
curriculum. Delete memeriksa attachment dengan exists() dan menolak semester
berisi mata kuliah. Mutation dan audit semester.created/updated/deleted atomic.
CurriculumService menggunakan lock curriculum yang sama untuk proteksi penurunan
total_semesters. Submission membaca ulang actor/curriculum dengan lock sebelum
memeriksa kelengkapan agar relasi yang pernah dimuat tidak menjadi sumber stale.
Jalur attachment Phase 7D wajib mengikuti urutan lock curriculum lalu semester
untuk menjaga konsistensi bersama operasi struktur. SKS tidak dihitung otomatis.

## 6. File Security

-   allow hanya format yang diperlukan, MVP utama PDF
-   validasi MIME dan extension
-   batasi file size
-   generate safe filename
-   simpan menggunakan Laravel Filesystem
-   jangan gunakan filename user sebagai executable path
-   authorization sebelum download
-   jangan izinkan script execution dari upload directory

## 7. Authorization Sequence

``` text
Authentication
 → Authorization
 → Scope Validation
 → Input Validation
 → File Validation
 → Business Rule
 → Audit Log
```

## 8. Services

### CurriculumService

CRUD dan domain operations dasar.

Phase 7B hanya mengubah metadata nama, tahun, tahun berlaku, total SKS, jumlah
semester, dan deskripsi. Total SKS/jumlah semester masih input manual; konsistensi
dan perhitungan ulang ditinjau ketika struktur kurikulum tersedia. Pengisian jumlah
semester tidak membuat Semester otomatis. Tahun bukan unique version identifier;
beberapa record pada tahun yang sama diperbolehkan. Versi dibedakan oleh ID record.
Create selalu draft, creator/updater berasal dari actor. Edit hanya draft/revision
melalui Policy yang diperiksa kembali pada record terkunci, bersama audit dalam
satu transaksi. Perubahan metadata tidak mengubah struktur maupun status workflow.

### CurriculumSubmissionService

-   validate completeness
-   submit
-   audit curriculum.submitted
-   notification ke Kaprodi aktif dalam Prodi yang sama

Phase 7F memakai service existing untuk GET/POST
`/admin/kurikulum/{curriculum}/submit`, bernama
`admin.curricula.submit.confirm` dan `admin.curricula.submit.store`.
Area management tetap hanya Admin Prodi aktif dengan scope valid;
CurriculumPolicy::submit melindungi kedua route. Draft/revision diperbolehkan,
submitted/approved/published/archived ditolak. GET hanya menampilkan kesiapan.

`readiness()` adalah representasi tunggal kelengkapan untuk UI dan validasi
domain: nama tidak kosong, tahun nonzero, total_sks > 0, total_semesters > 0,
nomor semester tepat 1..N (bukan hanya jumlah), minimal satu CurriculumCourse,
dan minimal satu record Document. Query hanya mengambil semester_number serta
exists() untuk attachment/dokumen. Tidak memakai relasi yang sudah dimuat sebagai
sumber kesiapan, tidak memuat seluruh Course/Document, dan tidak memindai filesystem.
effective_year/description tidak diwajibkan. Kesamaan jumlah SKS dan isi setiap
semester belum menjadi syarat; itu tetap keputusan hardening terpisah.

Konfirmasi menampilkan empat checklist, alasan ketidaklengkapan, serta link
perbaikan ke route production. Tombol POST ber-CSRF hanya muncul saat lengkap.
POST tidak menerima metadata, scope, status, timestamp, atau readiness dari client.
Service mengunci dan membaca ulang actor/Curriculum sebelum memeriksa ulang
status, scope, dan kelengkapan. Hasil GET bukan izin untuk melewati validasi POST.
DomainException kelengkapan ditampilkan sebagai validation message Bahasa
Indonesia pada halaman konfirmasi. Otorisasi route yang gagal menghasilkan 403.

Sukses mengubah status menjadi submitted dan mengisi submitted_at, lalu mencatat
tepat satu audit curriculum.submitted dengan old/new values dan mengirim in-app
Notification bertipe curriculum_submitted kepada setiap Kaprodi aktif di Prodi
yang sama. Tidak membuat CurriculumReview baru. Transaction membatalkan status,
audit, dan notifikasi bila salah satu side effect gagal. Pengajuan kedua ditolak
oleh Policy/status lock tanpa audit/notifikasi tambahan.

Revision diajukan melalui alur yang sama, tanpa versi Curriculum baru dan tanpa
mengubah riwayat review, Semester, attachment/snapshot, atau metadata/file Document.
Submitted langsung mengunci editing melalui Policy/service Phase 7B-7E existing.
Index menampilkan link Ajukan Review hanya untuk draft/revision, menuju konfirmasi.
Queue dashboard Kaprodi existing menerima Curriculum submitted tersebut; UI review
Phase 7G, history, dan pusat notifikasi belum diimplementasikan pada Phase 7F.

### CurriculumReviewService — Phase 7G

Production review menggunakan `/kaprodi/pengajuan`, namespace
`kaprodi.submissions.*`: GET antrean/detail, POST approve/revision, dan GET
download dokumen nested. Hanya Kaprodi aktif dengan Prodi valid; antrean dibatasi
Prodi actor dan status submitted, urutan submitted_at ASC lalu id ASC, 15/page.
Timestamp null tampil sebagai belum tercatat dan diurutkan sebelum timestamp
terisi pada database proyek. Scope tidak dibaca dari input client.

CurriculumPolicy::review melindungi detail, kedua mutation, dan download.
Detail eager-load semester, attachment, dan dokumen tanpa membaca identitas
Course master: kode/nama menggunakan snapshot CurriculumCourse. Tidak ada
kontrol edit atau publikasi. Sidebar dan antrean dashboard menuju route produksi.

`approve(User, Curriculum, ?notes)` dan `requestRevision(User, Curriculum, notes)`
tetap menjadi pelaksana review. Dalam transaction service membaca ulang serta
mengunci actor, lalu Curriculum, sebelum memeriksa role, scope, aktivasi, dan
status submitted. Instance lama bukan sumber otorisasi final. Approve menghasilkan
approved + approved_at; revision menghasilkan revision + approved_at null.
Keduanya membuat CurriculumReview, audit curriculum.approved atau
curriculum.revision_requested, serta notifikasi curriculum_approved atau
curriculum_revision_requested kepada semua Admin Prodi aktif pada Prodi yang sama.
Kegagalan audit/notifikasi membatalkan seluruh transaction.

Form revisi memerlukan notes string maksimal 10.000 karakter; service tetap
menolak whitespace-only. Approve UI tidak menerima notes; contract notes opsional
service dipertahankan. Input status, reviewer, scope dan timestamp tidak
diteruskan ke mutation. POST kedua ditolak tanpa side effects tambahan.

DocumentService::downloadForReview memeriksa role, Prodi, Policy review, dan
kepemilikan Document terhadap Curriculum. Cross-parent menghasilkan 403.
Streaming, safe path, private disk, safe filename dan missing-file 404 dipakai
bersama dengan download Admin Prodi; tidak ada public URL maupun input path.
Dokumen hanya dapat diunduh melalui review surface ketika submitted.

Revision membuka kembali mutation Admin Prodi melalui Policy draft/revision
existing. Resubmit mempertahankan riwayat, snapshot, semester dan dokumen.
Approved mengunci metadata, semester, attachment dan dokumen. Approval tidak
mengisi published_at, tidak mengarsipkan versi lain, dan tidak memanggil
CurriculumPublicationService. Publication UI tersedia pada Phase 7H melalui konfirmasi terpisah.

Tests mencakup stale actor/state, review berulang, transaction rollback,
revision-resubmit-approve, snapshot, scope, private download dan query count.
SQLite tidak membuktikan kontensi row-lock lintas koneksi; pengujian concurrency
database produksi tetap diperlukan untuk memverifikasi transaksi simultan.

### CurriculumPublicationService

Phase 7H menyediakan antrean approved pada `kaprodi.submissions.approved`
(`/kaprodi/pengajuan/disetujui`), GET konfirmasi dan POST publikasi pada
`kaprodi.submissions.publish.confirm/store` (`/kaprodi/pengajuan/{curriculum}/publish`).
Route statis disetujui diletakkan sebelum binding Curriculum. Antrean scoped
ke Prodi actor, status approved, urutan approved_at ASC lalu id ASC, 15/page.
Review queue tetap submitted-only. Dashboard dan review queue menautkan antrean
publikasi. Tidak ada perubahan akses detail/download review Phase 7G.

Policy publish tetap hanya Kaprodi aktif pada Prodi sendiri dan status approved.
Missing scope/role lain/cross-Prodi/status salah ditolak 403; guest menuju login.
Controller tidak menerima field mutation: POST hanya meneruskan actor dan target
route ke `publish(User, Curriculum): Curriculum`. Semua keputusan dilakukan service.

Service mengunci dan membaca ulang User dalam transaction, memvalidasi role dan
scope valid, lalu mengunci StudyProgram sebagai titik serialisasi publikasi per
Prodi, termasuk ketika belum ada versi published. Berikutnya target dan semua
published pada Prodi tersebut dikunci dalam urutan id ASC. Otorisasi dan status
target diperiksa ulang menggunakan record segar sebelum mutation. Lock actor saja
tidak cukup jika dua Kaprodi berbeda mempublikasikan versi pada Prodi yang sama.

Semua versi published lain di Prodi tersebut menjadi archived, archived_at diisi,
published_at historis dipertahankan. Target menjadi published, published_at diisi,
archived_at dikosongkan. Timestamp submission/approval dan metadata lain tetap.
Setiap versi lama menghasilkan curriculum.archived; target curriculum.published.
Notifikasi curriculum_published hanya untuk Admin Prodi aktif pada Prodi yang sama.
Archival, publication, audit dan notifikasi memakai satu transaction database.
Kegagalan side effect membatalkan semuanya. Snapshot, semester, dokumen/file privat,
dan riwayat review tidak diubah. Tidak ada kalkulasi atau validasi SKS tambahan.

Konfirmasi menampilkan target dan seluruh versi published yang akan diarsipkan
(normalnya satu), atau empty state bila belum ada. Ini hanya preview: POST membaca
ulang current publication dan tidak memakai ID versi lama dari client. Stale target
atau publikasi kedua ditolak. Satu published per Prodi dijaga melalui service,
bukan constraint baru. Semua penulis status publication harus melalui service ini.
SQLite tests membuktikan fresh-state/rollback dan publikasi berurutan, bukan
kontensi lock database produksi. Tidak ada klaim pengujian concurrency simultan.

Published/archived tetap read-only berdasarkan Policy existing. Approval Phase 7G
tetap terpisah; publikasi memerlukan konfirmasi POST eksplisit. Phase 7H tidak
menyediakan public portal maupun public document download.

-   publish
-   archive old publication
-   notifications
-   audit

### DocumentService

-   scoped list, upload, download, delete
-   authorization dan metadata server-owned
-   kompensasi filesystem dan audit

Phase 7E tidak menyediakan replace/edit, versioning, kategori, atau public
download. Route `admin.curricula.documents.*` mengikuti management surface
Admin Prodi: hanya actor aktif dengan scope Prodi valid. CurriculumPolicy::view
dipakai setelah role Gate untuk list/download; update untuk upload/delete.
Document harus milik Curriculum pada URL dan Curriculum harus milik Prodi actor.
Draft/revision dapat dimutasi; submitted/approved/published/archived readonly.

Upload menerima title maksimal 255 karakter dan PDF maksimal 10 MiB (10.240 KB,
ditampilkan sebagai 10 MB). Laravel memvalidasi file, MIME dari isi server,
ekstensi pdf, serta ukuran; MIME client bukan sumber kebenaran. Ini bukan antivirus
atau parser/sanitizer seluruh fitur PDF. Download memakai attachment, PDF content
type, nosniff, dan private/no-store; tidak ada inline preview.

Disk `documents` memakai local driver dengan root `storage/app/private-documents`,
visibility private, serve false, throw true. Root terpisah dari public/storage
maupun root disk local yang memiliki signed serving. Path dihasilkan server:
`curricula/{id}/documents/{UUID}.pdf`. Original filename disanitasi untuk
display/download saja. Tidak ada URL storage langsung atau parameter path download.
Path tersimpan diperiksa terhadap namespace Curriculum sebelum read/delete.
Path legacy/tidak sesuai format ditolak 404 dan perlu pemeriksaan operator;
file hilang pada path valid menghasilkan download 404, sementara penghapusan
record tetap diperbolehkan. Factory/seed metadata tidak membuat file fisik.

Upload/delete membaca ulang actor, Curriculum, lalu Document existing dengan
lock. Mutation dan audit document.created/document.deleted ada dalam transaksi DB.
Audit memuat metadata aman, tanpa konten atau file_path. Operasi tidak mengubah
Semester, attachment/snapshot, Course master, metadata total, review, notifikasi,
atau status workflow. Uploader di-eager-load dan daftar dipaginasi 15 per halaman.

**Konsistensi filesystem/DB:** keduanya bukan satu transaksi atomic.
Upload mendaftarkan kompensasi rollback untuk menghapus file baru ketika
DB/audit gagal, termasuk rollback transaksi luar. Kegagalan kompensasi dicatat
di server log dengan path untuk pemulihan operator. Delete melakukan commit
record/audit dahulu dan menghapus file pada afterCommit (setelah transaksi luar
juga commit). DB/audit failure tidak menyentuh file. Jika cleanup setelah commit
gagal, record tetap terhapus, file tetap private, pengguna mendapat pesan kegagalan
cleanup dan server log menyimpan document_id/path. Operator harus menyelesaikan
pembersihan file tersebut; tidak ada retry/outbox otomatis pada Phase 7E.
Process crash antara write/commit/cleanup masih dapat meninggalkan orphan private;
rekonsiliasi storage terhadap DB/backup menjadi pekerjaan operasional.

Submission existing tetap memerlukan minimal satu record Document dan tidak
memeriksa ulang file fisik atau kategori resmi. Upload production menghasilkan PDF
tervalidasi; kehilangan file di luar aplikasi perlu pemulihan operator. Penghapusan
PDF terakhir membuat draft/revision tidak lengkap untuk submission. Tidak ada
Submit UI atau public document route pada fase ini. Batas upload PHP/web server
dapat lebih rendah dari aplikasi; Phase 7E tidak mengubah konfigurasi server.

### CurriculumHistoryService — Phase 7I

GET `/internal/kurikulum/{curriculum}/riwayat`, nama `internal.curricula.history`,
adalah proyeksi read-only satu versi Curriculum. Route berada di group auth dan
active internal user, di luar group management Admin Prodi. CurriculumPolicy::view
tetap authoritative: Admin Prodi/Kaprodi pada Prodi sendiri, Admin Fakultas pada
fakultas sendiri, Admin Universitas global. Service juga mewajibkan relasi scope
yang valid bagi role scoped. Tidak ada akses publik atau perluasan mutation role.

AuditLog difilter database dengan entity_type persis App\Models\Curriculum,
entity_id Curriculum, serta allowlist curriculum.created/submitted/
revision_requested/approved/published/archived. Urutan created_at ASC lalu id ASC.
Semua submission tetap ditampilkan; label pengajuan ulang hanya dipakai jika
old_values.status secara eksplisit revision. Metadata updated dan aktivitas child
tidak dimasukkan ke timeline utama. Status/timestamp Curriculum hanya ringkasan,
bukan alasan untuk membuat event historis yang tidak tercatat.

CurriculumReview tidak memiliki FK ke AuditLog; pencocokan berdasarkan timestamp
atau latest review tidak dapat diandalkan. Semua review versi tersebut ditampilkan
terpisah dalam Riwayat Review, urutan reviewed_at ASC lalu id ASC. Timestamp null
ditampilkan sebagai belum tercatat (diurutkan lebih awal pada database proyek).
Notes revision dan optional approval di-escape Blade; null/blank notes tidak
menghasilkan blok catatan kosong. Review status legacy tidak dikenal mendapat
label umum tanpa memunculkan nilai mentah.

Relasi user/reviewer di-eager-load dengan id, name, role saja. Aktor kosong memakai
Sistem. Nama/role mengikuti data pengguna saat ini; schema tidak menyimpan snapshot
identitas aktor. Tanggal memakai d/m/Y H:i dalam config app.timezone. Tidak ada
raw JSON, IP, user_agent, internal path, maupun credential pada representation
view; service menghasilkan array presentasi berisi title, waktu, actor, dan notes.

MVP memuat semua event workflow/review satu versi tanpa pagination agar urutan
pengajuan berulang utuh. Ini membatasi query menurut versi dan jenis event, bukan
batas jumlah absolut; jika siklus review kelak sangat banyak, pagination perlu
ditinjau. Tidak memuat audit global, tidak N+1, tidak membuat audit/notifikasi baru.

Riwayat dapat dibuka dari index Admin Prodi untuk semua status, detail review dan
daftar approved Kaprodi, serta halaman Curriculum monitoring Fakultas/Universitas.
Archived tetap bisa dibaca sesuai scope. Data legacy kosong menampilkan empty state;
kejadian yang tidak diaudit tidak direkonstruksi. Aktivitas penyusunan rinci,
restore/rollback, notification center, dan public history tidak diimplementasikan.

### AuditLogService

Mencatat: - CREATE - UPDATE - DELETE - SUBMIT - APPROVE - REVISION -
PUBLISH - ARCHIVE - LOGIN - LOGOUT - UPLOAD_DOCUMENT - DELETE_DOCUMENT

## 9. Notifications

### Prodi Admin

-   revision
-   approved
-   published

### Kaprodi

-   submission baru
-   resubmission

### Faculty Admin

-   informasi monitoring penting

### University Admin

-   user/master/system events

## 10. Error Handling

User: - pesan Bahasa Indonesia - jelaskan tindakan yang dapat dilakukan

Server: - detail exception masuk log - jangan tampilkan stack trace ke
user production

## 11. Testing

Feature/unit tests minimal: - public tidak melihat draft - public
melihat published - Prodi Admin tidak dapat mengakses Prodi lain -
Kaprodi tidak dapat review Prodi lain - Faculty Admin hanya scope
fakultas - University Admin global - invalid transition ditolak -
revision wajib memiliki note - submit validation bekerja - publish
mengarsipkan curriculum lama - tidak ada dua published curriculum pada
Prodi yang sama - dokumen authorization bekerja

## 12. Routes

Gunakan sitemap sebagai sumber route publik dan role-specific. Jangan
mencampurkan controller antar role jika membuat authorization sulit
dipahami.

## 13. Historical Course Identity (Phase 6E)

Saat Course pertama kali ditambahkan ke Curriculum, server mengambil `code` dan
`name` master dan menyimpan `CurriculumCourse.course_code` serta `course_name`.
`CurriculumCourse::captureCourseIdentity()` adalah helper capture terpusat.
Event creating memanggil helper sebagai perlindungan normal Eloquent; nilai
snapshot dari request tidak masuk fillable dan tidak menjadi sumber kebenaran.

Snapshot dan `course_id` existing tidak dapat diubah melalui normal model update.
Perubahan semester, SKS, tipe, prerequisite, dan status attachment tidak melakukan
capture ulang. Submit, revisi, resubmit, approve, publish, dan archive juga tidak
menyegarkan snapshot. CourseService hanya memperbarui master.

Factory menangkap identitas setelah foreign key final ditentukan (termasuk
`for($course)`/override). Seeder memanggil helper secara eksplisit sebelum save;
`DatabaseSeeder` tetap menggunakan `WithoutModelEvents`. Raw/bulk insert, upsert,
query update, saveQuietly, dan operasi tanpa events melewati perlindungan model:
penulis jalur tersebut wajib memenuhi kontrak capture/immutability secara eksplisit.
NOT NULL di database tidak membuktikan sumber atau immutability snapshot.

### Kontrak implementasi Phase 7

1. Otorisasi Admin Prodi dan Curriculum sesuai Policy/workflow.
2. Validasi Course berasal dari Prodi authenticated user, bukan scope dari request.
3. Buat CurriculumCourse dengan course_id server-validated dan atribut struktur
   yang diizinkan; capture code/name dari master server saat creation.
4. Render identitas historis dari `curriculumCourse.course_code` dan
   `curriculumCourse.course_name`, **bukan** `curriculumCourse.course.code/name`.
   Relasi Course hanya untuk referensi master.
5. Jangan refresh snapshot saat edit struktur atau workflow. Perilaku replacement
   Course harus diputuskan eksplisit sebelum dibuat; Phase 6E menolaknya.

Phase 6E tidak membuat endpoint attachment atau UI Phase 7. Deskripsi belum
disnapshot; tinjau kembali sebelum Phase 8 bila deskripsi harus bersifat historis.

### Assignment mata kuliah Phase 7D

Route `admin.curricula.courses.*` hanya untuk Admin Prodi aktif dengan scope valid.
CurriculumPolicy mengizinkan mutation pada draft/revision milik Prodi actor;
status lain hanya dapat dibaca. Course harus milik Prodi Curriculum dan Semester
harus milik Curriculum tersebut. Nested assignment diperiksa terhadap parent
Curriculum, bukan hanya mengandalkan route-model binding.

CurriculumCourseService mengunci actor, Curriculum, Semester (urut ID), lalu
attachment existing atau Course master saat create. Otorisasi memakai state segar.
Mutasi dan audit `curriculum_course.created/updated/deleted` berada dalam satu
transaction. Parent lock menyelaraskan assignment dengan SemesterService dan
submission; writer baru harus mengikuti urutan yang sama.

Create menggunakan capture Phase 6E. Edit tidak dapat mengganti Course,
Curriculum, snapshot kode/nama, atau is_active. Daftar dan edit menampilkan snapshot,
diurutkan semester_number lalu course_code dan ID, termasuk semester kosong.

Total SKS Curriculum dan Semester tetap metadata manual. Jumlah SKS attachment
per semester maupun seluruh Curriculum dapat berbeda dari metadata tersebut.
Phase 7D tidak melakukan sinkronisasi atau menambah syarat kesamaan saat submit.
Submission existing tetap memerlukan minimal satu attachment; keputusan tentang
kesamaan total, perlakuan mata kuliah pilihan/is_active, dan kelengkapan setiap
semester perlu dipastikan sebelum aturan konsistensi diperketat.

### Deployment dan rollback snapshot (Phase 6E)

Backup database dan hentikan semua writer (maintenance aplikasi serta worker/
integrasi lain) selama kedua migration. Stage A memeriksa sumber sebelum DDL,
menambahkan kolom nullable, lalu backfill per 500 ID tanpa mengubah timestamps.
Sumber hilang/tidak valid menyebabkan gagal jelas, bukan skip atau data buatan.
Stage B memeriksa kelengkapan sebelum mengubah kedua kolom menjadi NOT NULL.

MySQL DDL dapat implicit commit dan tidak boleh dianggap rollback-safe. Jika
migration gagal, periksa schema/data aktual dan penyebabnya sebelum retry.
Stage A dapat dilanjutkan bila kolom sudah ada; snapshot non-null yang sudah
terisi dipertahankan. Jangan membuka writer di antara kedua stage.

Backfill existing attachment memakai metadata master terbaik saat migration,
bukan rekonstruksi pasti nilai historis asli. Stage B down mengembalikan nullable;
Stage A down menghapus snapshot dan **menghilangkan informasi historis** yang
mungkin tidak dapat dibuat ulang dari master. Restore backup bila diperlukan.

FK cascade Course tetap berlaku. Penghapusan langsung master di database masih
dapat menghapus attachment; aplikasi tidak menyediakan hard delete Course.
Perubahan strategi FK ditunda sebagai keputusan terpisah.

## Phase 9 — shared brand assets

`public/brand.css` is loaded before `public.css` or `internal.css` in all three
layouts (public, internal login/error, authenticated application). It centralizes
maroon primary and independent semantic tokens. Assets remain direct Laravel
public assets: no Vite/dependency/schema change. Hero SVG uses shared CSS variables.
Blue remaining in production assets is semantic info (submitted badge/info alert).
Internal pending forms restore their label/disabled state on pageshow.
UI changes do not alter queries, Policies, workflow, snapshots or download storage.
Public/internal query count regressions remain covered by existing feature tests.
403 uses the branded internal layout; 404/419/500 remain framework error pages.
Production must keep APP_DEBUG=false; no environment configuration is changed here.
