-- Database: kost_le_prala

CREATE DATABASE IF NOT EXISTS kost_le_prala;
USE kost_le_prala;

-- Tabel Users (Admin & Penghuni)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    no_telp VARCHAR(15),
    role ENUM('admin', 'penghuni') NOT NULL,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Kamar
CREATE TABLE kamar (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nomor_kamar VARCHAR(10) UNIQUE NOT NULL,
    tarif DECIMAL(10,2) NOT NULL,
    fasilitas TEXT,
    status ENUM('kosong', 'terisi') DEFAULT 'kosong',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Penghuni (Detail tambahan untuk user dengan role penghuni)
CREATE TABLE penghuni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    kamar_id INT,
    tanggal_masuk DATE,
    tanggal_keluar DATE NULL,
    lama_sewa INT COMMENT 'dalam bulan',
    status_sewa ENUM('aktif', 'selesai') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (kamar_id) REFERENCES kamar(id) ON DELETE SET NULL
);

-- Tabel Pembayaran
CREATE TABLE pembayaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    penghuni_id INT NOT NULL,
    periode_bulan INT NOT NULL COMMENT '1-12',
    periode_tahun INT NOT NULL,
    jumlah DECIMAL(10,2) NOT NULL,
    tanggal_bayar DATE,
    status ENUM('lunas', 'belum_lunas') DEFAULT 'belum_lunas',
    metode_bayar VARCHAR(50),
    keterangan TEXT,
    created_by INT NOT NULL COMMENT 'Admin yang mencatat',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (penghuni_id) REFERENCES penghuni(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY unique_periode (penghuni_id, periode_bulan, periode_tahun)
);

-- Tabel Audit Trail
CREATE TABLE audit_trail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    old_data TEXT,
    new_data TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert data admin default
INSERT INTO users (username, password, nama_lengkap, email, no_telp, role, status) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@leprala.com', '081234567890', 'admin', 'aktif');
-- Password default: password

-- Insert contoh data kamar
INSERT INTO kamar (nomor_kamar, tarif, fasilitas, status) VALUES
('101', 1500000.00, 'AC, Kamar Mandi Dalam, WiFi, Lemari, Kasur', 'kosong'),
('102', 1500000.00, 'AC, Kamar Mandi Dalam, WiFi, Lemari, Kasur', 'kosong'),
('103', 1200000.00, 'Kipas Angin, Kamar Mandi Luar, WiFi, Lemari, Kasur', 'kosong'),
('104', 1200000.00, 'Kipas Angin, Kamar Mandi Luar, WiFi, Lemari, Kasur', 'kosong'),
('201', 1800000.00, 'AC, Kamar Mandi Dalam, WiFi, Lemari, Kasur, Balkon', 'kosong'),
('202', 1800000.00, 'AC, Kamar Mandi Dalam, WiFi, Lemari, Kasur, Balkon', 'kosong'),
('203', 1500000.00, 'AC, Kamar Mandi Dalam, WiFi, Lemari, Kasur', 'kosong'),
('204', 1500000.00, 'AC, Kamar Mandi Dalam, WiFi, Lemari, Kasur', 'kosong');

-- Insert contoh penghuni
INSERT INTO users (username, password, nama_lengkap, email, no_telp, role, status) VALUES
('budi123', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Budi Santoso', 'budi@email.com', '081234567891', 'penghuni', 'aktif'),
('siti456', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Siti Nurhaliza', 'siti@email.com', '081234567892', 'penghuni', 'aktif');

-- Update status kamar menjadi terisi
UPDATE kamar SET status = 'terisi' WHERE nomor_kamar IN ('101', '201');

-- Insert data penghuni detail
INSERT INTO penghuni (user_id, kamar_id, tanggal_masuk, lama_sewa, status_sewa) VALUES
(2, 1, '2025-01-01', 12, 'aktif'),
(3, 5, '2025-02-01', 12, 'aktif');

-- Insert contoh pembayaran
INSERT INTO pembayaran (penghuni_id, periode_bulan, periode_tahun, jumlah, tanggal_bayar, status, metode_bayar, created_by) VALUES
(1, 1, 2025, 1500000.00, '2025-01-05', 'lunas', 'Transfer Bank', 1),
(1, 2, 2025, 1500000.00, '2025-02-05', 'lunas', 'Transfer Bank', 1),
(1, 3, 2025, 1500000.00, '2025-03-05', 'lunas', 'Cash', 1),
(1, 4, 2025, 1500000.00, '2025-04-05', 'lunas', 'Transfer Bank', 1),
(1, 5, 2025, 1500000.00, '2025-05-05', 'lunas', 'Transfer Bank', 1),
(1, 6, 2025, 1500000.00, '2025-06-05', 'lunas', 'Cash', 1),
(1, 7, 2025, 1500000.00, '2025-07-05', 'lunas', 'Transfer Bank', 1),
(1, 8, 2025, 1500000.00, '2025-08-05', 'lunas', 'Transfer Bank', 1),
(1, 9, 2025, 1500000.00, '2025-09-05', 'lunas', 'Cash', 1),
(1, 10, 2025, 1500000.00, NULL, 'belum_lunas', NULL, 1),
(2, 2, 2025, 1800000.00, '2025-02-03', 'lunas', 'Transfer Bank', 1),
(2, 3, 2025, 1800000.00, '2025-03-03', 'lunas', 'Transfer Bank', 1),
(2, 4, 2025, 1800000.00, '2025-04-03', 'lunas', 'Cash', 1),
(2, 5, 2025, 1800000.00, '2025-05-03', 'lunas', 'Transfer Bank', 1),
(2, 6, 2025, 1800000.00, '2025-06-03', 'lunas', 'Transfer Bank', 1),
(2, 7, 2025, 1800000.00, '2025-07-03', 'lunas', 'Cash', 1),
(2, 8, 2025, 1800000.00, '2025-08-03', 'lunas', 'Transfer Bank', 1),
(2, 9, 2025, 1800000.00, '2025-09-03', 'lunas', 'Transfer Bank', 1),
(2, 10, 2025, 1800000.00, NULL, 'belum_lunas', NULL, 1);