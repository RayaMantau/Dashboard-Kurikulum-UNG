# PRD --- Dashboard Kurikulum UNG

## 1. Ringkasan Produk

Dashboard Kurikulum UNG adalah portal web terpusat untuk menyediakan
informasi kurikulum Program Studi di Universitas Negeri Gorontalo.

Sistem memiliki dua area: - Public portal untuk melihat informasi
kurikulum yang sudah dipublikasikan. - Authenticated management area
untuk pengelolaan, review, monitoring, dan administrasi.

## 2. Latar Belakang

Informasi kurikulum dapat tersebar pada dokumen atau website
masing-masing Program Studi. Pengguna membutuhkan tempat terpusat untuk
menemukan: - Program Studi, - kurikulum aktif, - mata kuliah, -
distribusi mata kuliah per semester, - jumlah SKS, - dokumen
kurikulum, - riwayat kurikulum.

## 3. Tujuan

1.  Memusatkan informasi kurikulum UNG.
2.  Memudahkan pencarian kurikulum dan mata kuliah.
3.  Menyediakan dokumen kurikulum yang dapat diakses publik ketika telah
    dipublikasikan.
4.  Menyediakan workflow pengelolaan dan review kurikulum.
5.  Menyimpan riwayat versi kurikulum.
6.  Menyediakan monitoring bertingkat untuk Program Studi, Fakultas, dan
    Universitas.

## 4. Target Pengguna

### Public

-   Calon mahasiswa
-   Mahasiswa
-   Dosen
-   Pengunjung umum

### Internal

-   Admin/Operator Prodi
-   Kaprodi
-   Admin Fakultas
-   Admin Universitas

## 5. Role

### Admin/Operator Prodi

Mengelola curriculum milik Program Studinya.

### Kaprodi

Mereview, menyetujui, atau mengembalikan curriculum untuk revisi.

### Admin Fakultas

Monitoring Program Studi dan curriculum dalam fakultasnya.

### Admin Universitas

Mengelola master data, user, monitoring global, audit, dan settings.

## 6. Workflow

`Draft → Submitted → Kaprodi Review → Approved OR Revision → Published → Archived`

Transition: - draft → submitted - submitted → revision - submitted →
approved - revision → submitted - approved → published - published →
archived

## 7. Fitur MVP

### Public

-   Beranda
-   Daftar fakultas
-   Daftar Program Studi
-   Detail Program Studi
-   Detail kurikulum
-   Daftar semester
-   Detail mata kuliah
-   Search
-   Filter fakultas, Program Studi, tahun
-   Download dokumen PDF
-   Riwayat kurikulum

### Admin Prodi

-   Login
-   Dashboard
-   CRUD curriculum
-   Kelola semester
-   Kelola mata kuliah
-   Kelola dokumen
-   Submit review
-   Melihat revisi
-   Melihat history
-   Notifikasi

### Kaprodi

-   Dashboard
-   Daftar pengajuan
-   Review curriculum
-   Approve/publish
-   Return for revision
-   History
-   Notifikasi

### Admin Fakultas

-   Dashboard fakultas
-   Daftar Program Studi
-   Monitoring curriculum
-   History

### Admin Universitas

-   Dashboard global
-   CRUD Fakultas
-   CRUD Program Studi
-   CRUD User
-   Monitoring
-   Audit/history
-   Settings

## 8. Batasan MVP

Tidak termasuk: - scraping website Program Studi, - integrasi API
eksternal, - public API, - perbandingan curriculum antar Prodi, - import
Excel, - analytics lanjutan, - workflow approval Fakultas, - editor
RPS/CPL/CPMK lengkap.

CPL/CPMK/RPS hanya ditampilkan jika datanya memang tersedia.

## 9. Data Source

MVP menggunakan input manual oleh Admin Prodi. Website/dokumen resmi
Program Studi dapat digunakan sebagai referensi pengisian.

## 10. Acceptance Criteria Utama

-   Public hanya melihat curriculum published.
-   Admin Prodi hanya mengelola curriculum Prodinya.
-   Kaprodi hanya mereview curriculum Prodinya.
-   Admin Fakultas hanya melihat data dalam fakultasnya.
-   Admin Universitas memiliki scope global.
-   Curriculum lama tidak ditimpa.
-   Satu Prodi hanya memiliki satu curriculum published.
-   Submit memiliki validasi kelengkapan.
-   Revision wajib memiliki catatan.
-   Publish mengarsipkan curriculum published sebelumnya.
-   Dokumen menjadi public ketika curriculum published.
-   Business rules penting memiliki automated tests.
