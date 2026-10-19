# USER FLOW --- Dashboard Kurikulum UNG

## 1. Public Flow

### Production Phase 8A–8H

Phase 8H memverifikasi alur Beranda > Fakultas > Prodi > versi Curriculum >
detail > Semester serta unduhan dokumen dan Search > Semester. Archived mengikuti
alur historis yang sama tanpa menjadi current. Snapshot tetap konsisten setelah
master Course berubah. GET tidak membuat/mengubah data domain, audit atau notifikasi.
Perubahan status/aktivasi parent berlaku pada seluruh halaman dan download, juga
bagi pengguna internal yang login. Salah pasangan nested menghasilkan 404.

Pengunjung membuka `GET /` tanpa akun, melihat hero, ringkasan portal yang aman
untuk publik, preview fakultas aktif, dan preview kurikulum published terbaru.
CTA **Jelajahi Fakultas** dan navbar **Fakultas** membuka `/fakultas`.
Preview Faculty membuka detail slug; preview Curriculum belum memiliki link detail.
Guest dapat memilih **Masuk** menuju
`/login`; pengguna internal yang sudah login dapat memilih **Dashboard Internal**
menuju `/admin`, dengan proteksi internal tetap berlaku.

Direktori menampilkan Faculty aktif, cuplikan deskripsi, dan jumlah Prodi aktif,
12 per halaman. **Lihat Fakultas** membuka identitas, deskripsi, dan kartu Prodi
aktif milik Faculty tersebut. Faculty nonaktif/slug tidak dikenal menghasilkan
404. Faculty tanpa Prodi aktif tetap tersedia dengan empty state. Breadcrumb
detail: Beranda > Fakultas > nama Faculty. Kartu Prodi aktif kini memiliki link
detail; data workflow Curriculum tidak ditampilkan pada halaman Faculty.

**Program Studi** membuka `/prodi`, dengan filter slug Faculty aktif dan pagination
12 yang mempertahankan filter. Nilai tidak dikenal/nonaktif menghasilkan daftar
kosong. Detail Prodi memuat profil, link Faculty, website HTTP/HTTPS valid, dan
satu ringkasan published. Prodi atau parent nonaktif = 404.
Tanpa published tampil **Belum ada kurikulum aktif yang dipublikasikan.**
Jika ada published atau archived, **Lihat Semua Kurikulum** membuka daftar versi.
Bagian **Kurikulum Aktif** menampilkan satu published; **Riwayat Kurikulum**
menampilkan archived berlabel **Arsip**, 12 per halaman. Arsip tetap tersedia
meskipun tidak ada published. Jika tidak ada versi publik, halaman daftar tetap
200 dengan **Belum ada kurikulum publik yang tersedia.**

**Lihat Kurikulum** membuka metadata versi, identitas Prodi/Faculty dan deskripsi.
URL memakai slug Prodi dan ID Curriculum; salah pasangan, status privat, atau
parent nonaktif = 404 bagi guest maupun pengguna internal. Halaman hanya baca,
tanpa review, aktor atau audit. Sejak Phase 8E, **Struktur Kurikulum**
menampilkan seluruh semester dan mata kuliah inline, termasuk semester kosong.
**Lihat Semester** membuka halaman fokus dengan breadcrumb ke Curriculum.
Published maupun archived memakai kode/nama snapshot attachment, tidak mengikuti
perubahan master Course. SKS semester/Curriculum tetap metadata tersimpan;
SKS mata kuliah berasal dari attachment. Prasyarat ditampilkan escaped bila terisi.
Attachment tetap ditampilkan tanpa filter is_active, mengikuti Phase 7D.
Parent/status privat atau nested ownership salah menghasilkan 404, juga saat login.
Sejak Phase 8F, bagian **Dokumen Kurikulum** setelah struktur memuat judul,
filename aman, PDF dan ukuran. **Unduh Dokumen** menuju route aplikasi nested,
bukan storage URL. Published dan archived diperbolehkan dengan kedua parent aktif;
status privat, parent nonaktif atau salah kepemilikan menghasilkan 404 meski login.
File hilang/path tidak aman juga 404. Daftar boleh memuat record yang file fisiknya
hilang; render tidak memeriksa filesystem per dokumen. Tanpa dokumen tampil
**Belum ada dokumen kurikulum yang tersedia.** Uploader/path tidak ditampilkan.
Upload/delete tetap internal. **Cari Mata Kuliah** aktif menuju `/search` sejak
Phase 8G; CTA pencarian pada beranda menuju halaman yang sama. Tidak ada halaman
global Course master.

Data publik memakai allowlist: hanya Faculty aktif, Study Program aktif dengan
Faculty aktif, dan Curriculum `published`/`archived` dengan kedua parent aktif. Status
`draft`, `submitted`, `revision`, dan `approved` tidak ditampilkan. `archived`
bersifat historis, tersedia pada daftar/detail versi tetapi tidak masuk hitungan/
preview kurikulum aktif. Parent yang nonaktif menyembunyikan kurikulum
published terkait dari beranda.

Statistik **Fakultas Aktif** menghitung Faculty aktif; **Program Studi Aktif**
menghitung Prodi aktif di Faculty aktif; **Kurikulum Aktif** menghitung record
published pada Prodi dan Faculty aktif. Angka berasal dari database, bukan jumlah
kartu preview. Beranda tidak menampilkan audit, catatan review, notifikasi, path
dokumen, identitas pengunggah, atau konten/link management selain pintu masuk
Dashboard Internal bagi pengguna yang sudah login.

### Alur publik — mata kuliah tetap dalam konteks Curriculum/Semester

``` text
Beranda
 ├─ Fakultas
 │   └─ Detail Fakultas
 │       └─ Program Studi
 │           └─ Detail Prodi
 │               └─ Detail Kurikulum
 │                   ├─ Semester
 │                   ├─ Mata Kuliah
 │                   ├─ Dokumen
 │                   └─ Riwayat
 ├─ Program Studi
 ├─ Search
 └─ Login
```

## 2. Search Flow

Production Phase 8G: navbar/CTA beranda membuka `GET /search`. Pengunjung dapat
mengisi nama/kode snapshot atau memilih Faculty, Prodi, dan tahun Curriculum.
Filter digabung AND; filter saja diperbolehkan. Tanpa input bermakna tampil
petunjuk, bukan seluruh data. Input tidak valid atau pasangan Faculty/Prodi salah
menghasilkan hasil kosong tanpa error.

Hanya published/archived dengan kedua parent aktif yang dicari, termasuk bagi
pengguna internal yang login. Setiap kemunculan mata kuliah adalah satu hasil
dengan kode/nama snapshot, SKS, Wajib/Pilihan, semester, Curriculum/tahun,
Kurikulum Aktif/Arsip, Prodi dan Faculty. Satu Course pada dua versi tetap dua hasil.
Pagination 15 mempertahankan filter. **Lihat Semester** membuka konteks versi
yang sesuai. Perubahan master Course tidak mengubah identitas hasil historis.

``` text
Search
 → input kata kunci
 → hasil snapshot mata kuliah dalam konteks versi kurikulum
 → pilih hasil
 → halaman semester pada kurikulum terkait
```

## 3. Admin Prodi Flow

Production Phase 7F: dari daftar Kurikulum, Admin Prodi memilih **Ajukan Review**
pada Draft/Perlu Revisi. Halaman **Ajukan Kurikulum untuk Review** menampilkan
metadata, struktur semester, mata kuliah, dan dokumen sebagai checklist.
Persyaratan yang belum lengkap memiliki link menuju halaman perbaikan.

Jika lengkap, pengguna mengonfirmasi melalui **Ajukan untuk Review** (POST).
Server memeriksa ulang kondisi terkini; penghapusan dokumen/struktur setelah
halaman kesiapan dibuka tetap menyebabkan penolakan dengan pesan yang jelas.
Sukses kembali ke daftar Curriculum dengan flash message dan status Menunggu Review.
Metadata, Semester, Mata Kuliah Curriculum, dan Dokumen kemudian terkunci untuk
editing; halaman readonly tetap tersedia. Kaprodi aktif pada Prodi tersebut
menerima notifikasi in-app dan Curriculum masuk antrean dashboard existing.

Revision memakai konfirmasi yang sama untuk pengajuan ulang. Riwayat review,
snapshot mata kuliah, dan dokumen dipertahankan. Dokumen diperiksa berdasarkan
record database, tanpa pemeriksaan ulang file fisik. Review Kaprodi tersedia pada
Phase 7G; riwayat versi internal tersedia pada Phase 7I. Pusat notifikasi lengkap tetap ditunda.

``` text
Login
 → Dashboard
 → Kurikulum
 → Create/Edit
 → Kelola Semester
 → Kelola Mata Kuliah
 → Upload Dokumen
 → Validasi
 → Submit
 → Menunggu Review
```

Jika revision:

``` text
Revision
 → lihat catatan Kaprodi
 → edit
 → submit ulang
```

Jika approved:

``` text
Approved
 → lihat status
 → menunggu publikasi eksplisit oleh Kaprodi
```

## 4. Kaprodi Flow

Production Phase 7G: sidebar **Review Kurikulum** dan dashboard membuka
`/kaprodi/pengajuan`. Antrean hanya memuat submitted pada Prodi Kaprodi, dari
pengajuan paling lama. Detail menampilkan metadata, semester, snapshot mata
kuliah, dan link download PDF privat tanpa kontrol editing.

**Setujui Kurikulum** membuka konfirmasi sebelum POST persetujuan. Hasilnya
**Disetujui**, bukan publikasi. **Minta Revisi** memerlukan catatan (maksimal
10.000 karakter). Keputusan berhasil kembali ke antrean dengan flash message;
Admin Prodi aktif pada Prodi yang sama menerima notifikasi in-app.

Revision membuka editing Admin Prodi kembali; pengajuan ulang mempertahankan
riwayat review. Setelah disetujui semua mutation struktur/metadata/dokumen
terkunci. Review berulang atau status selain submitted ditolak server.

``` text
Login
 → Dashboard
 → Pengajuan
 → Review curriculum
 → lihat data + dokumen
 ├─ Kembalikan untuk Revisi
 │   └─ tulis catatan
 └─ Setujui (menunggu publikasi)
```

Production Phase 7H: Kaprodi membuka **Menunggu publikasi** dari dashboard atau
daftar pengajuan. `/kaprodi/pengajuan/disetujui` hanya memuat approved pada Prodi
sendiri. Pilih **Publikasikan** untuk melihat ringkasan target dan kurikulum aktif
yang akan diarsipkan. Bila belum ada, tampil **Belum ada kurikulum aktif yang akan
diarsipkan.** GET konfirmasi tidak mengubah data.

POST **Publikasikan Kurikulum** memeriksa ulang state terkini. Target approved
menjadi published, versi published sebelumnya pada Prodi yang sama menjadi
archived. Riwayat dan dokumen tidak dihapus. Sukses kembali ke antrean dengan
pesan **Kurikulum berhasil dipublikasikan.** Admin Prodi aktif pada Prodi yang sama
menerima notifikasi in-app; dashboard menampilkan versi aktif yang baru.

Hanya satu versi published per Prodi. Published/archived tidak dapat diedit atau
direview ulang. Ini menyelesaikan publikasi internal, bukan implementasi public
portal atau akses dokumen publik.

Phase 7I: pilih **Riwayat** dari index Kurikulum Admin Prodi, detail review/daftar
approved Kaprodi, atau **Riwayat Kurikulum** pada halaman monitoring Curriculum
Fakultas/Universitas. Semua menuju halaman internal yang sama, dengan pemeriksaan
scope server-side. Riwayat archived tetap dapat dibuka sesuai hak monitoring.

Halaman menampilkan identitas versi dan status saat ini, lalu event workflow
tercatat dari paling lama. Pengajuan ulang tidak digabungkan. Riwayat Review
menampilkan semua catatan revisi/persetujuan secara terpisah agar tidak menebak
pasangan audit-review. Riwayat kosong tidak diisi event buatan. Halaman hanya baca;
tidak menyediakan aksi edit, restore, publish, atau penghapusan riwayat.

## 5. Admin Fakultas Flow

``` text
Login
 → Dashboard
 → Program Studi
 → Kurikulum
 → Monitoring
 → Riwayat
```

## 6. Admin Universitas Flow

``` text
Login
 → Dashboard
 ├─ Fakultas
 ├─ Program Studi
 ├─ Kurikulum
 ├─ Users
 ├─ Monitoring
 ├─ Riwayat
 └─ Settings
```

## 7. Submit Validation Flow

``` text
Klik Submit
 → cek informasi curriculum
 → cek semester
 → cek mata kuliah
 → cek total SKS
 → cek dokumen resmi
 ├─ gagal → tampilkan daftar kekurangan
 └─ lolos → status submitted
```
