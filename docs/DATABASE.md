# DATABASE --- Dashboard Kurikulum UNG

## 1. Entity

-   users
-   faculties
-   study_programs
-   curricula
-   semesters
-   courses
-   curriculum_courses
-   documents
-   curriculum_reviews
-   notifications
-   audit_logs

## 2. faculties

-   id PK
-   name
-   code
-   slug
-   description nullable
-   is_active
-   timestamps

## 3. study_programs

-   id PK
-   faculty_id FK
-   name
-   code
-   degree
-   slug
-   description nullable
-   accreditation nullable
-   website nullable
-   is_active
-   timestamps

## 4. users

-   id PK
-   name
-   email unique
-   password
-   role
-   faculty_id nullable
-   study_program_id nullable
-   is_active
-   last_login_at nullable
-   timestamps

Role: - `university_admin` - `faculty_admin` - `study_program_admin` -
`head_of_study_program`

Scope: - university admin: faculty_id null, study_program_id null -
faculty admin: faculty_id set, study_program_id null - prodi
admin/kaprodi: faculty_id + study_program_id set

## 5. curricula

-   id PK
-   study_program_id FK
-   name
-   year
-   effective_year nullable
-   total_sks decimal
-   total_semesters integer
-   description nullable
-   status enum: draft/submitted/revision/approved/published/archived
-   submitted_at nullable
-   approved_at nullable
-   published_at nullable
-   archived_at nullable
-   created_by FK users
-   updated_by FK users nullable
-   timestamps

Setiap versi adalah record baru.

## 6. semesters

-   id PK
-   curriculum_id FK
-   semester_number
-   name
-   total_sks
-   timestamps

Kontrak Semester Phase 7C: `curricula.total_semesters` adalah jumlah semester
yang direncanakan/diizinkan; baris `semesters` adalah struktur aktual. Nomor harus
1..total_semesters dan unique per curriculum. Struktur boleh belum lengkap pada
draft/revision, tetapi submission mensyaratkan set nomor tepat 1..total_semesters.
Metadata total_semesters tidak boleh diturunkan di bawah nomor tertinggi existing.
Tidak ada pembuatan semester otomatis. Nama baru/hasil edit dihasilkan server
sebagai `Semester {semester_number}`; total_sks tetap metadata manual.
Semester berisi CurriculumCourse tidak boleh dihapus melalui aplikasi meskipun
FK database memakai cascade. Semester kosong dapat dihapus saat draft/revision.

## 7. courses

-   id PK
-   study_program_id FK
-   code
-   name
-   description nullable
-   timestamps

Course adalah master course per Program Studi.

## 8. curriculum_courses

-   id PK
-   curriculum_id FK
-   semester_id FK
-   course_id FK
-   course_code string(255) NOT NULL, tanpa default: snapshot kode
-   course_name string(255) NOT NULL, tanpa default: snapshot nama
-   sks decimal
-   course_type enum: wajib/pilihan
-   prerequisite_notes nullable
-   is_active
-   timestamps

SKS/semester dapat berubah antar versi tanpa mengubah master course.

Phase 7D memakai unique `(curriculum_id, course_id)`: satu Course hanya sekali
per Curriculum, termasuk lintas semester. `sks` decimal(4,2) divalidasi 0..99.99,
maksimal dua desimal. Attachment baru memakai `is_active = true`; UI tidak
mengubah status attachment existing. Update hanya menerima semester, SKS, jenis,
dan catatan prasyarat. Penghapusan hanya attachment pada draft/revision;
Course master, Semester, dan Curriculum tetap ada. Total SKS manual tidak disinkronkan.

Identitas kode/nama diambil dari Course di server saat attachment pertama kali
dibuat. Snapshot tidak berubah saat master Course diedit, struktur attachment
diubah, atau workflow submit/revisi/approve/publish/archive berjalan. Tampilan
kurikulum historis wajib menggunakan `course_code` dan `course_name` pada
CurriculumCourse, bukan `course.code`/`course.name`. `course_id` tetap menjadi
referensi master, dan penggantiannya pada attachment existing belum didukung.

Migration Phase 6E menambahkan kolom nullable, melakukan backfill Query Builder
per 500 ID secara berurutan, kemudian mewajibkan NOT NULL tanpa default kosong.
Semua atribut lama termasuk timestamps dipertahankan. Sumber Course yang hilang,
kosong, atau melebihi 255 karakter menyebabkan kegagalan yang jelas.
Backfill hanya menyimpan **metadata Course terbaik yang tersedia saat migration**;
nilai historis sebelum perubahan master tidak dapat direkonstruksi secara pasti.

Tidak ada snapshot deskripsi. Jika Phase 8 menampilkan deskripsi sebagai data
versi historis, keputusan ini harus ditinjau kembali. FK `course_id` masih cascade
delete: penghapusan langsung Course di database dapat menghapus attachment.
Hard delete Course melalui aplikasi tetap tidak disediakan.

## 9. documents

-   id PK
-   curriculum_id FK
-   title
-   file_name
-   file_path
-   file_type
-   file_size
-   uploaded_by FK users
-   timestamps

Kontrak Phase 7E: title/file_name/file_path/file_type adalah string(255).
file_size adalah unsigned bigint nullable; upload aplikasi selalu mengisinya
dalam byte, bukan KB/MB. curriculum_id cascade-delete; uploaded_by restrict-delete.
Relasi Document adalah curriculum() dan uploader(). Tidak ada kategori, official
flag, status dokumen, atau nomor versi.

Server menetapkan curriculum_id, nama display aman, path private, MIME terdeteksi
(`application/pdf`), ukuran aktual, dan uploaded_by. File fisik disimpan terpisah
dari nama display dengan UUID. Satu PDF attached memenuhi prasyarat dokumen MVP;
penghapusan dokumen terakhir pada draft/revision diperbolehkan, tetapi submission
kemudian ditolak sampai tersedia dokumen lagi. Tidak ada edit/replace/move.

## 10. curriculum_reviews

-   id PK
-   curriculum_id FK
-   reviewer_id FK users
-   status enum: submitted/approved/revision
-   notes nullable
-   reviewed_at nullable
-   timestamps

## 11. notifications

-   id PK
-   user_id FK
-   type
-   title
-   message
-   data JSON nullable
-   read_at nullable
-   timestamps

## 12. audit_logs

-   id PK
-   user_id nullable
-   action
-   entity_type
-   entity_id nullable
-   old_values JSON nullable
-   new_values JSON nullable
-   ip_address nullable
-   user_agent nullable
-   created_at

## 13. Relationships

``` text
faculties 1:N study_programs
study_programs 1:N curricula
curricula 1:N semesters
curricula 1:N documents
curricula 1:N curriculum_courses
courses N:1 study_programs
curriculum_courses N:1 courses
users 1:N curriculum_reviews
users 1:N notifications
users 1:N audit_logs
curricula 1:N curriculum_reviews
```

## 14. Constraints

-   FK menggunakan referential integrity.
-   `users.email` unique.
-   `study_programs.slug` unique.
-   Curriculum published aktif maksimal satu per Program Studi.
-   Course code sebaiknya unique dalam scope Program Studi.
-   Jangan hard-delete published/archived curriculum.
-   Delete draft harus mempertimbangkan child records.

## 15. Index

Minimal index: - curricula.study_program_id - curricula.status -
curricula.year - semesters.curriculum_id - courses.study_program_id -
curriculum_courses.curriculum_id - curriculum_courses.semester_id -
curriculum_courses.course_id - notifications.user_id/read_at -
audit_logs.entity_type/entity_id
