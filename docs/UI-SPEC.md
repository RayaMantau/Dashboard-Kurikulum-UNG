# UI-SPEC --- Dashboard Kurikulum UNG

## 1. Design Direction

Style: **Modern Academic Portal**

Prinsip: - clean - profesional - modern - informatif - whitespace -
akademik

## 2. Design Tokens

### Primary — Maroon (Phase 9)

Shared source: `public/brand.css`, loaded before public/internal styles.

| Token | Value |
|---|---|
| --color-primary | #7A1F2B |
| --color-primary-dark | #5C1620 |
| --color-primary-hover | #681A25 |
| --color-primary-soft | #F6E9EC |
| --color-primary-border | #E7C8CE |
| --color-on-primary | #FFFFFF |

Public, internal dan login memakai maroon yang sama pada brand, primary action,
active navigation, link dan focus. Permukaan putih/netral tetap dominan.
Success hijau, warning amber, danger merah dan info biru tetap independen.
Tombol hapus memakai danger outline/soft background dengan label Hapus; approve
hijau, revisi amber, publication primary maroon. Published tetap hijau mengikuti
badge existing; archived netral. Tidak semua status dijadikan maroon.
### Neutral

-   50: `#F8FAFC`
-   100: `#F1F5F9`
-   200: `#E2E8F0`
-   300: `#CBD5E1`
-   400: `#94A3B8`
-   500: `#64748B`
-   600: `#475569`
-   700: `#334155`
-   800: `#1E293B`
-   900: `#0F172A`

### Semantic

-   success: `#16A34A`
-   warning: `#F59E0B`
-   error: `#DC2626`
-   info: `#0EA5E9`

### Typography

Font: Inter

-   Display: 48/56, 700
-   H1: 36/44, 700
-   H2: 30/38, 700
-   H3: 24/32, 600
-   H4: 20/28, 600
-   Body Large: 18/28, 400
-   Body: 16/24, 400
-   Body Small: 14/20, 400
-   Caption: 12/16, 400

### Radius

6, 8, 12, 16, 20 px.

### Spacing

4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96 px.

### Layout

-   max width: 1280px
-   public horizontal padding: 24 mobile / 32 tablet / 40 desktop
-   admin sidebar: 260px
-   admin topbar: 72px

## 3. Public Homepage

Production Phase 8A menggunakan layout publik tersendiri, terpisah dari shell
internal: header/navigation, hero, kartu statistik, preview fakultas, preview
kurikulum aktif terbaru, dan footer identitas UNG. Arahnya **Modern Academic
Portal**, menggunakan maroon bersama sejak Phase 9 dengan warna utama `#7A1F2B`, dark
`#5C1620`, dan latar terang. Inter dimuat dari Bunny Fonts (400–700), dengan
fallback system sans-serif. Container maksimal 1280px termasuk padding horizontal
40px desktop, 32px pada lebar maksimal 1100px, dan 24px pada maksimal 640px.

Hero memakai ilustrasi SVG dekoratif. Sejak Phase 8B, CTA **Jelajahi Fakultas**
menuju `faculties.index`.
Phase 8G menyediakan CTA pencarian menuju `/search`. Statistik database menampilkan **Fakultas Aktif** (Faculty
aktif), **Program Studi Aktif** (Prodi aktif di Faculty aktif), dan **Kurikulum
Aktif** (published di Prodi dan Faculty aktif). Preview menampilkan maksimal enam
fakultas dan tiga kurikulum published. Sejak Phase 8B, preview Faculty memiliki
link **Lihat Fakultas** ke detail slug; preview Curriculum belum memiliki link detail.

Navbar: **Beranda** aktif; **Fakultas** menjadi link aktif sejak Phase 8B dengan
active state direktori/detail. Sejak Phase 8C **Program Studi** juga link aktif;
**Cari Mata Kuliah** aktif menuju `search` sejak Phase 8G. Guest melihat
**Masuk**; pengguna yang sudah login melihat **Dashboard Internal**. Footer
memuat identitas universitas dan link Beranda, tanpa informasi kontak buatan.

Pada maksimal 960px, menu dibuka/ditutup melalui tombol dengan `aria-controls`
dan `aria-expanded`; Escape menutup menu serta mengembalikan fokus ke tombol.
Menu juga menutup saat klik di luar atau memilih link. Tanpa JavaScript,
navigasi tetap terlihat. Grid preview berubah dari tiga menjadi dua kolom pada
960px dan satu kolom pada 640px. Pada 640px, hero menjadi satu kolom, ilustrasi
disembunyikan, statistik ditumpuk, dan footer menumpuk. Teks panjang dapat membungkus.

Fondasi aksesibilitas mencakup `lang="id"`, skip link ke main, heading berurutan,
landmark semantik, fokus keyboard terlihat, dan badge dengan teks
**Dipublikasikan**. Empty state: **Belum ada fakultas aktif yang tersedia.** dan
**Belum ada kurikulum aktif yang dipublikasikan.** Tidak ada statistik buatan.

Preview/count tidak memuat parent nonaktif atau status draft/submitted/revision/
approved/archived. Archived bukan kurikulum aktif; tampilan historis tersedia
pada daftar/detail versi Phase 8D, dengan struktur semester/mata kuliah sejak 8E.
Dokumen tersedia sejak 8F dan search tersedia sejak 8G.

### Direktori/detail Fakultas — production Phase 8B

Memakai shell publik existing. Direktori memiliki judul **Fakultas**, breadcrumb
Beranda > Fakultas, dan grid 3/2/1 kolom existing. Kartu menampilkan kode, nama,
cuplikan deskripsi maksimal 180 karakter, jumlah Prodi aktif, dan **Lihat Fakultas**
dengan accessible name yang menyertakan nama Faculty. Pagination publik memakai
link Sebelumnya/Berikutnya serta nomor halaman dan total, tanpa CSS shell admin.

Detail menggunakan judul halaman **{Faculty Name} | Dashboard Kurikulum UNG**,
breadcrumb Beranda > Fakultas > nama (item terakhir non-link), kode, deskripsi
escaped dengan baris dipertahankan, dan jumlah Prodi aktif. Kartu Prodi
menampilkan nama, kode, jenjang, serta akreditasi bila terisi; nilai kosong tidak
diisi klaim buatan. Sejak Phase 8C tersedia link **Lihat Program Studi** ke detail
slug. Tidak ada tabel/status Curriculum pada halaman Faculty.

Empty states: **Belum ada fakultas yang tersedia.** dan **Belum ada Program Studi
aktif pada fakultas ini.** Heading/landmark semantik, fokus keyboard, wrapping teks
panjang, navbar mobile dan footer menggunakan fondasi Phase 8A.

## 4. Public Detail Prodi

Production Phase 8C: direktori memakai grid publik, filter Faculty dengan label
terlihat, tombol Terapkan filter, dan pagination 12/page. Kartu menampilkan nama,
kode, jenjang, Faculty, akreditasi bila terisi, dan indikator **Kurikulum tersedia**
atau **Kurikulum belum tersedia** berdasarkan published saja.

Detail memakai title nama Prodi, breadcrumb Beranda > Program Studi > nama,
profil escaped, link Faculty dan website HTTP/HTTPS valid. Ringkasan **Kurikulum
Aktif** memuat nama, tahun, tahun berlaku bila ada, SKS, dan jumlah semester.
Sejak Phase 8D tersedia **Lihat Semua Kurikulum** bila published/archived ada,
termasuk saat hanya arsip tersedia. Ringkasan current tetap dipertahankan.

Empty states: **Belum ada Program Studi yang tersedia.**, **Tidak ada Program
Studi yang tersedia untuk fakultas ini.**, dan **Belum ada kurikulum aktif yang
dipublikasikan.** Filter membungkus pada mobile; grid 3/2/1 kolom, fokus keyboard,
dan wrapping teks panjang memakai fondasi publik existing.

## 5. Public Detail Curriculum

Production Phase 8D: daftar versi memisahkan **Kurikulum Aktif** dari **Riwayat
Kurikulum**. Kartu aktif tampil terlebih dahulu; grid arsip memakai pagination
12 terpisah. Badge berteks **Kurikulum Aktif** (hijau) dan **Arsip** (netral).
Kartu menampilkan nama, tahun, tahun berlaku bila ada, SKS, jumlah semester, dan
link **Lihat Kurikulum** dengan nama versi pada accessible label.

Detail memiliki title nama Curriculum, breadcrumb Beranda > Program Studi >
nama Prodi > Kurikulum > nama versi, dengan item terakhir non-link. Metadata
ditampilkan dalam grid responsif; deskripsi escaped mempertahankan baris dan
membungkus teks panjang. Identitas Prodi/Faculty memiliki link production.

Empty states: **Belum ada kurikulum aktif yang dipublikasikan.**, **Belum ada
kurikulum publik yang tersedia.**, dan **Belum ada riwayat kurikulum pada halaman
ini.** Tidak ada kontrol mutasi, timestamp workflow, identitas aktor, atau review.
Phase 8E menambahkan **Struktur Kurikulum** setelah metadata. Setiap semester
menampilkan nomor/nama, total SKS tercatat, daftar mata kuliah inline dan link
**Lihat Semester**. Komponen `public.course-list` dipakai juga pada detail semester.
Daftar semantik menampilkan kode/nama snapshot, SKS attachment, label Wajib/Pilihan,
dan Prasyarat escaped bila terisi. Desktop memisahkan identitas dan fakta; mobile
menumpuknya tanpa tabel lebar. Nama/prasyarat panjang membungkus.

Detail semester memakai title **Semester N - {Curriculum Name}**, badge current/
arsip, konteks Curriculum/Prodi, SKS tercatat dan daftar mata kuliah. Breadcrumb
existing ditambah Semester N sebagai item terakhir non-link. Empty states:
**Belum ada struktur semester yang tersedia.** dan **Belum ada mata kuliah pada
semester ini.** Semester kosong tidak disembunyikan.

Phase 8F menambahkan **Dokumen Kurikulum** setelah struktur. Kartu memakai grid
publik responsif existing, menampilkan judul, filename display yang disanitasi,
PDF, serta ukuran KB/MB dengan format Indonesia seperti daftar internal.
Filename/judul panjang membungkus; **Unduh Dokumen** memiliki accessible label
yang menyertakan judul. Tidak ada uploader, path, timestamp atau disk storage.
Empty state: **Belum ada dokumen kurikulum yang tersedia.** Tidak ada upload,
delete publik, atau embedded PDF viewer.

### Pencarian Mata Kuliah — production Phase 8G

Title **Cari Mata Kuliah | Dashboard Kurikulum UNG**, breadcrumb Beranda > Cari
Mata Kuliah, dan form GET dengan label Kata kunci, Fakultas, Program Studi, Tahun
Kurikulum. Tombol **Cari** dan link **Reset Filter**. Pilihan Prodi mengikuti Faculty
terpilih; pilihan tahun hanya dari Curriculum publik dengan parent aktif.

State awal: **Masukkan nama atau kode mata kuliah untuk memulai pencarian.**
Hasil kosong: **Tidak ada mata kuliah yang sesuai dengan pencarian.** Ringkasan
memuat kata kunci escaped dan jumlah hasil database. Kartu responsif existing
menampilkan identitas snapshot, SKS, Wajib/Pilihan, konteks semester/Curriculum/
Prodi/Faculty dan badge berteks Kurikulum Aktif/Arsip. **Lihat Semester** memiliki
accessible label dengan kode dan nama Curriculum. Pagination publik dipakai ulang.
Filter membungkus, fokus terlihat dan teks panjang membungkus tanpa UI framework baru.

## 6. Public Detail Mata Kuliah

Rancangan berikut belum menjadi halaman global production. Phase 8E menampilkan
informasi mata kuliah dalam konteks snapshot Curriculum/Semester saja.

Tampilkan: - kode - nama - SKS - semester - jenis - prasyarat -
deskripsi - CPL/CPMK/RPS jika tersedia

## 7. Admin Layout

Sidebar: - Dashboard - Kurikulum - Mata Kuliah - Dokumen - Pengajuan -
Riwayat - Notifikasi - Profil

Dashboard cards: - Total Kurikulum - Draft - Menunggu Review - Revision

## 8. Kaprodi Layout

Sidebar: - Dashboard - Pengajuan - Riwayat - Notifikasi - Profil

Review page: - informasi curriculum - semester - mata kuliah - dokumen -
notes - review history

Actions: - Kembalikan untuk Revisi - Setujui Kurikulum (Phase 7G)
- Publikasikan Kurikulum melalui konfirmasi terpisah setelah approved (Phase 7H).
Approval tidak otomatis mempublikasikan. Antrean review submitted dipisahkan dari
antrean approved yang menunggu publikasi.

## 9. Faculty Admin

Sidebar: - Dashboard - Program Studi - Kurikulum - Monitoring -
Riwayat - Notifikasi - Profil

MVP bersifat read-only terhadap isi curriculum.

## 10. University Admin

Sidebar: - Dashboard - Fakultas - Program Studi - Kurikulum - Users -
Monitoring - Riwayat - Settings - Profil

## 11. Status Badge

-   draft → neutral
-   submitted → blue
-   revision → warning
-   approved → success
-   published → success
-   archived → neutral

Status tidak boleh hanya dibedakan dengan warna.

## 12. Components

Phase 7I menggunakan shell dan daftar/card existing untuk Riwayat Kurikulum.
Ringkasan versi diikuti Riwayat Workflow (oldest-first) dan Riwayat Review terpisah.
Setiap item menampilkan judul Bahasa Indonesia, waktu sesuai timezone aplikasi,
nama/peran pengguna saat ini, serta catatan review bila ada. Aktor kosong tampil
Sistem. Notes di-escape; raw audit JSON/security metadata tidak ditampilkan.
Empty state: "Belum ada riwayat aktivitas untuk kurikulum ini."
Daftar menumpuk alami pada mobile dan tetap read-only, tanpa tombol mutation.

Reusable components minimal: - Button - Badge - Card - Input - Select -
Search - Modal - Alert - EmptyState - ErrorState - LoadingState -
Table - Pagination - FileUpload - Breadcrumb - Tabs - Timeline -
NotificationItem

## 13. Forms

Rules: - label selalu terlihat - required indicator - inline validation
error - disabled state jelas - loading state saat submit - upload
menampilkan nama dan ukuran file

## 14. Responsive

Mobile: - navbar → hamburger/drawer - sidebar admin → drawer - table →
card/list jika memungkinkan - hindari horizontal scroll - action utama
tetap mudah dijangkau

## 15. Accessibility

### Verifikasi integrasi Phase 8H

Seluruh sembilan halaman HTML publik menggunakan layout publik, title dengan
suffix Dashboard Kurikulum UNG, navbar production dan breadcrumb dengan item
terakhir non-link. Download adalah response PDF terpisah. Tidak ada link Segera;
CSS placeholder future-nav dihapus. Layout internal tetap terpisah.

Audit source mempertahankan label form, status berteks, fokus keyboard, menu
mobile dengan Escape/aria-expanded, grid responsif, filter wrapping dan teks
panjang yang membungkus. Nama/deskripsi/snapshot/prasyarat/query/judul dokumen
memakai escaping Blade; website Prodi hanya HTTP/HTTPS valid.
Verifikasi visual desktop/mobile belum dilakukan karena capability browser
tidak tersedia; feature tests tidak dianggap sebagai verifikasi visual.

-   keyboard navigable
-   visible focus
-   semantic HTML
-   label form
-   contrast memadai
-   status tidak hanya menggunakan warna
-   modal dapat ditutup dengan keyboard

### Phase 9 UI polish

Tombol utama memiliki hover/pressed/focus; danger, approve dan revision memakai
kelas semantik tersendiri. Flash success hijau dan escaped. Form assignment/review
menghubungkan error ke field melalui aria-describedby. Input catatan prasyarat
berbentuk array yang ditolak validasi tidak dirender sebagai teks old input.
Kontrol form min-width 0, tombol panjang wrapping, pagination/action groups
membungkus dan tabel tetap di scroll wrapper berlabel/focusable. Empty states dan
label enum Bahasa Indonesia existing dipertahankan (Menunggu Review/Perlu Revisi).
Tombol pending dipulihkan pada pageshow agar browser Back tidak meninggalkan form
terkunci. Desktop/mobile ditinjau pada source; verifikasi visual belum dilakukan
karena capability browser tidak tersedia. Tidak ada klaim screenshot/visual QA.
