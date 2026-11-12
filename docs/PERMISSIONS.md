# PERMISSIONS --- Dashboard Kurikulum UNG

## 1. Permission Matrix

Phase 11: monitoring publik hanya GET/HEAD, tanpa login, dengan current published
pada Prodi/Faculty aktif. Akses arsip melalui halaman versi existing tetap memakai
published/archived + kedua parent aktif, termasuk bagi user internal yang login.
Tanggal pengesahan dan external URL mengikuti CurriculumPolicy::update existing:
hanya Admin Prodi aktif, scope sendiri, status draft/revision. Role lain tidak
mendapat izin edit; workflow dan scope tidak berubah. Monitoring Faculty memakai
faculty_id authenticated user, mencakup seluruh Prodi sendiri via pagination.

  Fitur                    Prodi Admin   Kaprodi   Fakultas Admin   Universitas Admin
  ---------------------- ------------- --------- ---------------- -------------------
  View curriculum                    ✓         ✓                ✓                   ✓
  Create curriculum                  ✓        \-               \-                  \-
  Edit curriculum                    ✓        \-               \-                  \-
  Delete draft                       ✓        \-               \-                  \-
  Manage semester                    ✓        \-               \-                  \-
  Manage course                      ✓        \-               \-                  \-
  Manage document                    ✓        \-               \-                  \-
  Submit review                      ✓        \-               \-                  \-
  Review                            \-         ✓               \-                  \-
  Approve                           \-         ✓               \-                  \-
  Return revision                   \-         ✓               \-                  \-
  Monitor faculty                   \-        \-                ✓                   ✓
  Manage faculty                    \-        \-               \-                   ✓
  Manage Program Studi              \-        \-               \-                   ✓
  Manage users                      \-        \-               \-                   ✓
  Audit                             \-        \-               \-                   ✓
  Settings                          \-        \-               \-                   ✓

## 2. Scope

### Prodi Admin

-   `study_program_id` harus sama dengan curriculum.
-   Tidak boleh mengakses curriculum Prodi lain.

### Kaprodi

-   `study_program_id` harus sama dengan curriculum yang direview.
-   Tidak boleh mengedit isi curriculum.

### Admin Fakultas

-   `faculty_id` user harus sama dengan faculty curriculum/prodi.

### Admin Universitas

-   Global scope.
-   Global scope tidak berarti berhak mengedit isi curriculum Prodi
    dalam MVP.

## 3. Security

Authorization wajib dilakukan server-side menggunakan Policy/Gate.
Jangan mengandalkan: - hidden button, - disabled button, - URL
obscurity, - JavaScript, - menu visibility.

## 4. Policies

Minimal: - CurriculumPolicy - CoursePolicy - DocumentPolicy -
StudyProgramPolicy - UserPolicy

## 5. Sensitive Operations

Wajib authorization: - update curriculum - delete curriculum - submit -
approve - revision - publish - archive - upload document - delete
document - user management
