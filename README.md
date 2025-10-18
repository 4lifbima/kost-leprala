
<img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" />
<img src="https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" />
<img src="https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white" />
<img src="https://img.shields.io/badge/Node%20js-339933?style=for-the-badge&logo=nodedotjs&logoColor=white" />


# Kost Le Prala - Sistem Informasi Manajemen Kost

Sistem Informasi Manajemen (SIM) sederhana untuk mengelola data kamar, penghuni, dan pembayaran kost. Dibuat menggunakan PHP native dan Tailwind CSS.

## Fitur Utama

Aplikasi ini memiliki dua hak akses: **Admin** dan **Penghuni**.

### 💎 Fitur Admin
* **Dashboard:** Menampilkan statistik ringkas (Total kamar, kamar terisi, penghuni aktif, pemasukan bulan ini, dan total tunggakan).
* **Manajemen Kamar:** Operasi CRUD (Create, Read, Update, Delete) untuk data kamar, termasuk tarif dan fasilitas.
* **Manajemen Penghuni:** Operasi CRUD untuk data penghuni. Mendaftarkan penghuni baru akan otomatis membuat akun login untuk mereka dan mengubah status kamar menjadi "terisi".
* **Manajemen Pembayaran:** Mencatat dan mengelola status pembayaran (lunas/belum lunas) untuk setiap penghuni per periode.
* **Laporan:** Menghasilkan laporan pemasukan dan tunggakan berdasarkan periode (bulan & tahun) dengan fitur ekspor ke **PDF** dan **Excel (.xlsx)**.
* **Audit Trail:** Mencatat semua aktivitas penting yang dilakukan oleh pengguna untuk keperluan audit.

### 👤 Fitur Penghuni
* **Dashboard:** Menampilkan informasi kamar yang ditempati (fasilitas, tarif), status pembayaran bulan ini, dan total tunggakan pribadi.
* **Riwayat Pembayaran:** Melihat histori pembayaran 6 bulan terakhir.
* **Manajemen Profil:** Mengubah data pribadi dan password akun.

---

## Teknologi yang Digunakan

* **Backend:** PHP 8.x (Native/Prosedural dengan MySQLi)
* **Frontend:** Tailwind CSS (via CDN)
* **Database:** MySQL / MariaDB
* **Library (via Composer):**
    * `dompdf/dompdf`: Untuk ekspor laporan ke PDF.
    * `phpoffice/phpspreadsheet`: Untuk ekspor laporan ke Excel (.xlsx).

---

## Persyaratan dan Instalasi

### Persyaratan
* Web Server (Contoh: Apache, NGINX)
* PHP 8.0 atau lebih baru
* MySQL atau MariaDB
* Composer

### Langkah Instalasi
1.  **Clone repository:**
    ```bash
    git clone https://github.com/username/kost-leprala.git
    cd kost-leprala
    ```

2.  **Install dependensi PHP:**
    ```bash
    composer install
    ```
    (Ini akan mengunduh `dompdf` dan `phpspreadsheet` ke dalam folder `vendor/`).

3.  **Setup Database:**
    * Buat database baru di MySQL/MariaDB Anda (misalnya: `kost_le_prala`).
    * Impor file `db.sql` ke dalam database yang baru Anda buat.
    ```bash
    mysql -u root -p kost_le_prala < db.sql
    ```

4.  **Konfigurasi Koneksi:**
    * Buka file `config.php`.
    * Sesuaikan kredensial database (host, username, password, nama database) di bagian atas file:
    ```php
    // config.php
    try {
        $conn = new mysqli('localhost', 'root', '', 'kost_le_prala');
    ...
    }
    ```

5.  **Jalankan Aplikasi:**
    * Arahkan web server Anda ke direktori project atau gunakan server bawaan PHP:
    ```bash
    php -S localhost:8000
    ```
    * Buka `http://localhost:8000` di browser Anda.

---

## Susunan Project
```bash
kost-leprala/
│
├── 📁 dashboard/                     # Semua halaman setelah login
│   │
│   ├── 📄 index.php                  # Dashboard utama (admin & penghuni)
│   ├── 📄 kamar.php                  # Manajemen Kamar (CRUD)
│   ├── 📄 penghuni.php               # Manajemen Penghuni (CRUD)
│   ├── 📄 pembayaran.php             # Manajemen Pembayaran (CRUD)
│   ├── 📄 laporan.php                # Halaman Laporan & Ekspor
│   └── 📄 profile.php                # Halaman untuk edit profil pengguna
│
├── 📁 vendor/                        # Dependensi dari Composer
│
├── 📄 .gitignore                     # File konfigurasi Git (file yang diabaikan)
├── 📄 composer.json                  # Konfigurasi dependensi Composer
├── 📄 config.php                     # File konfigurasi utama (DB, session, helper)
├── 📄 db.sql                         # Dump SQL untuk struktur & data awal database
├── 📄 index.php                      # Halaman login utama
├── 📄 logout.php                     # Skrip proses logout
└── 📄 README.md                      # Dokumentasi proyek
```

---

## Contoh Penggunaan (Akun demo)

Setelah mengimpor `db.sql`, Anda dapat login menggunakan akun demo berikut:

**Akun Admin:**
* **Username:** `admin`
* **Password:** `password`

**Akun Penghuni:**
* **Username:** `budi123`
* **Password:** `password`

---

## Kontribusi

Merasa ada yang bisa ditingkatkan? Silakan buka *Issue* atau kirimkan *Pull Request*. Kontribusi Anda sangat kami hargai!

1.  Fork project ini.
2.  Buat branch fitur baru (`git checkout -b fitur/fitur-keren`).
3.  Commit perubahan Anda (`git commit -m 'Menambahkan fitur keren'`).
4.  Push ke branch Anda (`git push origin fitur/fitur-keren`).
5.  Buka Pull Request.

---

## Lisensi

Project ini dilisensikan di bawah **MIT License**. Lihat file `LICENSE` untuk detail lebih lanjut