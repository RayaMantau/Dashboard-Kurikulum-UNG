# DEVELOPMENT-PLAN --- Dashboard Kurikulum UNG

## Catatan urutan implementasi aktual

Daftar fase di bawah adalah rancangan awal, bukan status pelaksanaan aktual.
Urutan pengembangan yang disepakati kemudian: Phase 1–4 foundation/policies/workflow,
Phase 5A–5D authentication/authorization/shell/dashboard, Phase 6A–6E master data
dan snapshot historis, Phase 7A–7I pengelolaan/workflow/history Curriculum,
serta Phase 8A–8H portal publik dan integrasi/hardening.

Phase 8A–8H telah diimplementasikan: homepage, Faculty/Prodi directory/detail,
versi/detail Curriculum, struktur snapshot Semester/Course, private-storage mediated
public PDF download dan search/filter snapshot. Integrasi dibuktikan feature tests;
visual desktop/mobile belum diverifikasi karena capability browser tidak tersedia.
Route production dan batas visibilitas kanonis tercatat di SITEMAP/TECHNICAL-SPEC.
Phase 9/10 engineering COMPLETE; hasil dan batas verifikasi tercatat di bawah.
Standalone Course master public detail,
notification center, comparison, Excel, API, analytics, advanced search serta
CPL/CPMK/RPS tetap deferred. Butir roadmap lama tidak otomatis menjadi fitur selesai.

## Phase 0 --- Foundation

1.  Pastikan Laravel 13 berjalan.
2.  Buat folder `docs/`.
3.  Simpan seluruh dokumentasi sebagai source of truth.
4.  Siapkan `.env` dan MySQL.
5.  Konfigurasi authentication.
6.  Buat enum role/status/course type.

## Phase 1 --- Database

1.  Migration faculties
2.  Migration study_programs
3.  Migration users
4.  Migration curricula
5.  Migration semesters
6.  Migration courses
7.  Migration curriculum_courses
8.  Migration documents
9.  Migration curriculum_reviews
10. Migration notifications
11. Migration audit_logs
12. Foreign keys/index/constraints

## Phase 2 --- Models

Buat model dan relationship: - Faculty - StudyProgram - User -
Curriculum - Semester - Course - CurriculumCourse - Document -
CurriculumReview - Notification - AuditLog

## Phase 3 --- Authorization

1.  UserRole enum
2.  Policies
3.  Role middleware jika diperlukan
4.  Scope helper/query scopes
5.  Feature tests authorization

## Phase 4 --- Public Portal

Urutan: 1. layout/navbar/footer 2. homepage 3. faculty list/detail 4.
prodi list/detail 5. curriculum detail 6. semester detail 7. course
detail 8. search 9. document download 10. history

## Phase 5 --- Admin Prodi

1.  dashboard
2.  curriculum list
3.  create/edit
4.  semester management
5.  course management
6.  document management
7.  submit validation
8.  pengajuan
9.  history
10. notification

## Phase 6 --- Kaprodi

1.  dashboard
2.  submission list
3.  review detail
4.  revision
5.  approve & publish
6.  history
7.  notifications

## Phase 7 --- Faculty Admin

1.  dashboard
2.  prodi list
3.  curriculum monitoring
4.  history

## Phase 8 --- University Admin

1.  dashboard
2.  faculty CRUD
3.  prodi CRUD
4.  users CRUD
5.  global monitoring
6.  audit
7.  settings

## Phase 9 --- Hardening

-   file security
-   validation
-   authorization audit
-   transaction audit
-   error handling
-   responsive QA
-   accessibility QA
-   performance review

## Phase 10 --- Testing

Run: - unit tests - feature tests - authorization tests - workflow
tests - upload/download tests - public visibility tests - publication
uniqueness tests

## Definition of Done

Feature dianggap selesai jika: - sesuai PRD - sesuai permissions -
sesuai UI-SPEC - business rule bekerja - test relevan lulus - tidak
menambah scope tanpa persetujuan - dokumentasi diperbarui jika ada
perubahan arsitektur

## Phase 9 aktual — COMPLETE

Primary brand publik/internal/login berubah ke maroon melalui shared brand.css.
Semantic info/success/warning/danger tetap terpisah. Perbaikan mencakup action
hapus/review, flash success, wrapping kontrol, error-field association dan pending
form recovery saat pageshow. Tidak ada perubahan domain, migration atau dependency.
Validasi akhir: 591 tests / 5.603 assertions PASS; Pint PASS; syntax JS PASS.
Baseline sebelumnya: 586 tests / 5.553 assertions. Verifikasi visual desktop/mobile
belum dilakukan karena capability browser tidak tersedia; source/HTTP tests bukan
visual verification. Hasil Phase 10 dicatat pada bagian berikut.

## Phase 10 aktual — COMPLETE (engineering / release preparation)

Validasi 8 Oktober 2026: baseline 591 tests / 5.603 assertions; final **597 tests /
5.632 assertions PASS**. ReleaseReadinessTest: 6 tests / 29 assertions PASS.
Critical regression: 589 tests / 5.601 assertions PASS, termasuk internal,
authorization, workflow, snapshot, public portal dan Phase 9 UI. Pint PASS;
node --check public/internal.js dan public/public.js PASS.

README diganti dengan panduan proyek. DEPLOYMENT.md dan RELEASE-CHECKLIST.md
mencakup environment production, initial admin via hidden terminal prompts,
storage private, backup, upload limits, migration, release dan manual QA.
Seeder demo menolak production/staging sebelum write. phpunit.xml memaksa
testing/SQLite :memory: serta cache/session array; tests tidak untuk production.
.env.example tidak mengandung credential nyata; .env.* di-ignore kecuali template.
Runtime private documents telah dicakup storage/app/.gitignore.

config:cache, route:cache, view:cache dan optimize:clear PASS. Verifikasi memakai
CACHE_STORE=array sementara agar tidak flush cache database lokal. Config/route
cache dibersihkan kembali. Tidak ada perubahan .env lokal, schema, dependency,
authorization atau workflow. Route audit: 78 route, tanpa duplicate names,
duplicate method/URI/domain atau debug/test endpoint. Runtime debug call tidak
ditemukan. Scan source/config/docs yang tersedia tidak menemukan credential
produksi literal; credential contoh hanya demo/factory yang dibatasi penggunaannya.

Status proyek: **ENGINEERING COMPLETE**, bukan klaim sudah deployed. Workspace
tidak memiliki metadata Git: audit tracked files/history belum dapat dilakukan.
Browser capability tidak tersedia; visual desktop/mobile QA menjadi checklist
operator. Hosting/HTTPS/permissions/backup restore, engine database production,
concurrency nyata dan load testing harus diverifikasi di lingkungan tujuan.
Tidak ada remote deployment atau fitur post-MVP yang dikerjakan.
