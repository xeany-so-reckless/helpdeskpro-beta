# HelpdeskPro

Aplikasi web **IT helpdesk ticketing** untuk melaporkan, menugaskan, menangani, dan memantau masalah teknologi informasi di dalam organisasi. Dibangun sebagai monolit Laravel dengan antarmuka Vue 3 melalui Inertia.js.

## Fitur

- **Tiket**: karyawan membuat tiket (judul, kategori, prioritas, deskripsi, lampiran), lalu memantau statusnya. Nomor tiket berformat `HD-YYYY-0001` dan reset setiap tahun.
- **Alur status**: Open → Assigned → In Progress → Resolved → Closed, dengan jalur Reopened. Perpindahan status diatur di satu tempat dan setiap perubahan tercatat di riwayat.
- **Penugasan**: admin menugaskan atau menugaskan ulang teknisi, teknisi dapat mengambil tiket dari antrean.
- **Penyelesaian**: teknisi menulis catatan solusi, pelapor mengonfirmasi (Closed) atau membuka kembali dalam 7 hari. Tiket Resolved yang tidak dikonfirmasi ditutup otomatis setelah 7 hari.
- **Komentar dan catatan internal**: percakapan di tiket, dengan catatan internal yang hanya terlihat oleh IT Support dan Administrator.
- **Lampiran**: PNG, JPG, dan PDF (maks. 5 MB per file, 5 file per unggahan), disimpan privat dan diunduh lewat pengecekan akses.
- **Timeline aktivitas**: riwayat pembuatan, perubahan status, penugasan, dan prioritas.
- **Dashboard per role**: kartu statistik, antrean, grafik tren, dan performa tim.
- **Laporan** (Admin): filter periode, statistik per status, prioritas, kategori, dan teknisi, plus grafik tren.
- **Manajemen kategori dan pengguna** (Admin): tambah, edit, cari, dan nonaktifkan. Data tidak dihapus agar riwayat tiket tetap utuh.
- **Light dan Dark mode** dengan tema Slate, dapat diganti dari header.

## Role

| Role | Kemampuan utama |
|---|---|
| **Employee** | Membuat tiket, melihat dan mengomentari tiket miliknya, mengonfirmasi atau membuka kembali tiket yang selesai |
| **IT Support** | Melihat antrean dan tiket yang ditugaskan, mengambil tiket, mengubah status dan prioritas, menulis catatan internal |
| **Administrator** | Semua di atas, ditambah penugasan, kategori, pengguna, dan laporan |

Hak akses dijaga di backend (Policy dan validasi), bukan hanya di tampilan.

## Teknologi

- **Backend**: PHP 8.3+, Laravel 13, Pest
- **Frontend**: Vue 3 (`<script setup>` + TypeScript), Inertia.js, Tailwind CSS 4
- **UI**: shadcn-vue (reka-ui), ikon Lucide
- **Grafik**: Unovis
- **Database**: MySQL / MariaDB (SQLite untuk pengujian)
- **Build**: Vite

## Persyaratan

- PHP 8.3 atau lebih baru (ekstensi umum Laravel, termasuk `fileinfo` dan `pdo_mysql`)
- Composer 2
- Node.js 20 atau lebih baru dan npm
- MySQL atau MariaDB

## Instalasi (development)

```bash
# 1. Salin proyek dan pasang dependensi
git clone <url-repositori> helpdeskpro
cd helpdeskpro
composer install
npm install

# 2. Konfigurasi environment
cp .env.example .env
php artisan key:generate
```

Buat database kosong (mis. `helpdeskpro`), lalu isi `.env`:

```dotenv
APP_NAME=HelpdeskPro
APP_URL=http://helpdeskpro.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=helpdeskpro
DB_USERNAME=root
DB_PASSWORD=
```

```bash
# 3. Migrasi, data awal, dan jalankan
php artisan migrate --seed
composer run dev
```

`composer run dev` menjalankan server Laravel, queue, log, dan Vite sekaligus. Wayfinder membuat helper route TypeScript saat perintah ini berjalan. Jika muncul error "has no exported member" pada impor `@/routes/...`, restart perintah tersebut atau jalankan `php artisan wayfinder:generate --with-form`.

### Akun contoh (hanya development)

Seeder membuat tiga akun dan beberapa kategori. Seeder pengguna **otomatis dilewati di production**.

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@helpdeskpro.test` | `password` |
| IT Support | `support@helpdeskpro.test` | `password` |
| Employee | `employee@helpdeskpro.test` | `password` |

Ganti atau hapus akun ini sebelum aplikasi dipakai di luar mesin lokal. Di production, buat akun admin pertama secara manual (mis. lewat `php artisan tinker`), lalu kelola pengguna lainnya dari menu Users.

## Pengujian

```bash
php artisan test
```

Pengujian memakai Pest dan mencakup alur status, hak akses tiap role, lampiran, komentar, dashboard, laporan, dan manajemen pengguna.

## Tugas terjadwal

Penutupan otomatis tiket Resolved dijalankan oleh scheduler setiap hari pukul 01:00. Untuk mencobanya secara manual:

```bash
php artisan tickets:auto-close
```

Di server, tambahkan satu entri cron agar scheduler berjalan:

```cron
* * * * * cd /path/ke/helpdeskpro && php artisan schedule:run >> /dev/null 2>&1
```

## Penyimpanan lampiran

Lampiran disimpan di disk privat (`storage/app/private/ticket-attachments/{id-tiket}/`) dengan nama acak dan **tidak dapat diakses lewat URL langsung**. Unduhan melewati pengecekan akses di aplikasi. Pastikan folder `storage` dapat ditulis oleh user web server, dan sertakan folder ini dalam backup.

## Struktur singkat

```
app/
  Enums/            Role, TicketStatus, TicketPriority, TicketEvent
  Http/Controllers/ Tiket, aksi tiket, komentar, lampiran, kategori, pengguna, dashboard, laporan
  Policies/         Aturan akses (Ticket, Category, User)
  Services/         TicketWorkflow, TicketNumberGenerator, DashboardStats, ReportBuilder, ...
resources/js/
  pages/            Halaman Inertia (tickets, dashboard, categories, users, reports, settings)
  components/       Komponen aplikasi dan shadcn-vue (components/ui)
tests/Feature/      Pengujian Pest
```

## Keamanan

- Otorisasi di backend melalui Policy pada setiap aksi, termasuk unduhan lampiran.
- Kolom sensitif (`status`, `assigned_to`, `role`, `is_active`, dan sebagainya) tidak dapat diisi lewat mass assignment.
- Validasi lampiran berdasarkan isi file, bukan nama atau ekstensi.
- Akun yang dinonaktifkan otomatis keluar pada request berikutnya.
- Administrator tidak dapat menonaktifkan atau mengubah role akunnya sendiri.
- Jangan menyimpan secret di repositori. `.env` sudah diabaikan oleh Git.

## Lisensi

Proyek ini dilisensikan di bawah [MIT License](LICENSE).