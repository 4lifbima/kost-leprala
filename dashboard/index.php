<?php
require_once '../config.php';
check_login();

    $bulan_ini = date('n');
    $tahun_ini = date('Y');

// Ambil data statistik untuk admin
if ($_SESSION['role'] == 'admin') {
    // Total kamar
    $total_kamar = $conn->query("SELECT COUNT(*) as total FROM kamar")->fetch_assoc()['total'];
    
    // Kamar terisi
    $kamar_terisi = $conn->query("SELECT COUNT(*) as total FROM kamar WHERE status = 'terisi'")->fetch_assoc()['total'];
    
    // Kamar kosong
    $kamar_kosong = $total_kamar - $kamar_terisi;
    
    // Total penghuni aktif
    $penghuni_aktif = $conn->query("SELECT COUNT(*) as total FROM penghuni WHERE status_sewa = 'aktif'")->fetch_assoc()['total'];
    
    // Pemasukan bulan ini
    $pemasukan_bulan_ini = $conn->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM pembayaran WHERE periode_bulan = $bulan_ini AND periode_tahun = $tahun_ini AND status = 'lunas'")->fetch_assoc()['total'];
    
    // Tunggakan
    $tunggakan = $conn->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM pembayaran WHERE status = 'belum_lunas' AND periode_tahun = $tahun_ini AND periode_bulan <= $bulan_ini")->fetch_assoc()['total'];
    
    // Pembayaran terbaru
    $pembayaran_terbaru = $conn->query("
        SELECT p.*, u.nama_lengkap, k.nomor_kamar 
        FROM pembayaran p
        JOIN penghuni pe ON p.penghuni_id = pe.id
        JOIN users u ON pe.user_id = u.id
        JOIN kamar k ON pe.kamar_id = k.id
        WHERE p.status = 'lunas'
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    
    // Tunggakan per penghuni
    $tunggakan_list = $conn->query("
        SELECT u.nama_lengkap, k.nomor_kamar, COUNT(*) as jumlah_bulan, SUM(p.jumlah) as total_tunggakan
        FROM pembayaran p
        JOIN penghuni pe ON p.penghuni_id = pe.id
        JOIN users u ON pe.user_id = u.id
        JOIN kamar k ON pe.kamar_id = k.id
        WHERE p.status = 'belum_lunas' AND p.periode_tahun = $tahun_ini AND p.periode_bulan <= $bulan_ini
        GROUP BY pe.id
        ORDER BY total_tunggakan DESC
        LIMIT 5
    ");
} else {
    // Data untuk penghuni
    $user_id = $_SESSION['user_id'];
    
    // Ambil data penghuni
    $penghuni_data = $conn->query("
        SELECT p.*, k.nomor_kamar, k.tarif, k.fasilitas
        FROM penghuni p
        JOIN kamar k ON p.kamar_id = k.id
        WHERE p.user_id = $user_id AND p.status_sewa = 'aktif'
    ")->fetch_assoc();
    
    if ($penghuni_data) {
        // Status pembayaran bulan ini
        $pembayaran_bulan_ini = $conn->query("
            SELECT status FROM pembayaran 
            WHERE penghuni_id = {$penghuni_data['id']} 
            AND periode_bulan = $bulan_ini 
            AND periode_tahun = $tahun_ini
        ")->fetch_assoc();
        
        // Total tunggakan
        $total_tunggakan = $conn->query("
            SELECT COUNT(*) as jumlah, COALESCE(SUM(jumlah), 0) as total 
            FROM pembayaran 
            WHERE penghuni_id = {$penghuni_data['id']} 
            AND status = 'belum_lunas'
            AND periode_tahun = $tahun_ini 
            AND periode_bulan <= $bulan_ini
        ")->fetch_assoc();
        
        // Riwayat pembayaran
        $riwayat_pembayaran = $conn->query("
            SELECT * FROM pembayaran 
            WHERE penghuni_id = {$penghuni_data['id']}
            ORDER BY periode_tahun DESC, periode_bulan DESC
            LIMIT 6
        ");
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Kost Le Prala</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .card-shadow:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span class="ml-2 text-xl font-bold text-gray-800">Kost Le Prala</span>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-4">
                        <a href="../dashboard" class="border-b-2 border-red-600 text-gray-900 inline-flex items-center px-1 pt-1 text-sm font-medium">
                            Dashboard
                        </a>
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                        <a href="kamar.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Kamar
                        </a>
                        <a href="penghuni.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Penghuni
                        </a>
                        <a href="pembayaran.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Pembayaran
                        </a>
                        <a href="laporan.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Laporan
                        </a>
                        <?php endif; ?>

                        <a href="profile.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Profil
                        </a>

                    </div>
                </div>
                <div class="flex items-center">
                    <div class="ml-3 relative">
                        <div class="flex items-center space-x-3">
                            <span class="text-sm text-gray-700">
                                <span class="font-semibold"><?php echo $_SESSION['nama_lengkap']; ?></span>
                            </span>
                            <a href="../logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-200">
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php show_message(); ?>
        
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-gray-600 mt-1">Selamat datang di Sistem Informasi Management Kost Le Prala</p>
        </div>

        <?php if ($_SESSION['role'] == 'admin'): ?>
        <!-- Admin Dashboard -->
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-6 text-white card-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-100 text-sm font-medium">Total Kamar</p>
                        <p class="text-3xl font-bold mt-2"><?php echo $total_kamar; ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="text-red-100">Terisi: <?php echo $kamar_terisi; ?> | Kosong: <?php echo $kamar_kosong; ?></span>
                </div>
            </div>

            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white card-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Penghuni Aktif</p>
                        <p class="text-3xl font-bold mt-2"><?php echo $penghuni_aktif; ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="text-blue-100">Total penghuni saat ini</span>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white card-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Pemasukan Bulan Ini</p>
                        <p class="text-2xl font-bold mt-2"><?php echo format_rupiah($pemasukan_bulan_ini); ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="text-green-100"><?php echo nama_bulan($bulan_ini); ?> <?php echo $tahun_ini; ?></span>
                </div>
            </div>

            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 text-white card-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Total Tunggakan</p>
                        <p class="text-2xl font-bold mt-2"><?php echo format_rupiah($tunggakan); ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="text-orange-100">Belum dibayar</span>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Pembayaran Terbaru -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Pembayaran Terbaru</h2>
                    <a href="pembayaran.php" class="text-red-600 hover:text-red-700 text-sm font-medium">Lihat Semua →</a>
                </div>
                <div class="space-y-4">
                    <?php if ($pembayaran_terbaru->num_rows > 0): ?>
                        <?php while ($row = $pembayaran_terbaru->fetch_assoc()): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800"><?php echo $row['nama_lengkap']; ?></p>
                                    <p class="text-xs text-gray-500">Kamar <?php echo $row['nomor_kamar']; ?> - <?php echo nama_bulan($row['periode_bulan']); ?> <?php echo $row['periode_tahun']; ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-green-600"><?php echo format_rupiah($row['jumlah']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo format_tanggal($row['tanggal_bayar']); ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">Belum ada pembayaran</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Daftar Tunggakan -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Daftar Tunggakan</h2>
                    <span class="text-orange-600 text-sm font-medium"><?php echo $tunggakan_list->num_rows; ?> Penghuni</span>
                </div>
                <div class="space-y-4">
                    <?php if ($tunggakan_list->num_rows > 0): ?>
                        <?php while ($row = $tunggakan_list->fetch_assoc()): ?>
                        <div class="flex items-center justify-between p-4 bg-orange-50 rounded-lg border border-orange-200">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800"><?php echo $row['nama_lengkap']; ?></p>
                                    <p class="text-xs text-gray-500">Kamar <?php echo $row['nomor_kamar']; ?> - <?php echo $row['jumlah_bulan']; ?> bulan</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-orange-600"><?php echo format_rupiah($row['total_tunggakan']); ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <svg class="w-16 h-16 text-green-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-gray-500">Tidak ada tunggakan!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Penghuni Dashboard -->
        <?php if ($penghuni_data): ?>
        
        <!-- Info Kamar -->
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-8 text-white shadow-lg mb-8">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-bold mb-2">Kamar <?php echo $penghuni_data['nomor_kamar']; ?></h2>
                    <p class="text-red-100 mb-4"><?php echo $penghuni_data['fasilitas']; ?></p>
                    <div class="flex items-center space-x-6">
                        <div>
                            <p class="text-red-100 text-sm">Tarif Bulanan</p>
                            <p class="text-2xl font-bold"><?php echo format_rupiah($penghuni_data['tarif']); ?></p>
                        </div>
                        <div>
                            <p class="text-red-100 text-sm">Mulai Sewa</p>
                            <p class="text-lg font-semibold"><?php echo format_tanggal($penghuni_data['tanggal_masuk']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-20 p-4 rounded-lg">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Status Pembayaran Bulan Ini</h3>
                <?php if ($pembayaran_bulan_ini && $pembayaran_bulan_ini['status'] == 'lunas'): ?>
                    <div class="flex items-center space-x-3 p-4 bg-green-50 rounded-lg border border-green-200">
                        <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="text-lg font-bold text-green-600">LUNAS</p>
                            <p class="text-sm text-gray-600"><?php echo nama_bulan($bulan_ini); ?> <?php echo $tahun_ini; ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex items-center space-x-3 p-4 bg-red-50 rounded-lg border border-red-200">
                        <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="text-lg font-bold text-red-600">BELUM LUNAS</p>
                            <p class="text-sm text-gray-600"><?php echo nama_bulan($bulan_ini); ?> <?php echo $tahun_ini; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Total Tunggakan</h3>
                <?php if ($total_tunggakan['jumlah'] > 0): ?>
                    <div class="flex items-center space-x-3 p-4 bg-orange-50 rounded-lg border border-orange-200">
                        <svg class="w-12 h-12 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="text-2xl font-bold text-orange-600"><?php echo format_rupiah($total_tunggakan['total']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo $total_tunggakan['jumlah']; ?> bulan belum dibayar</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="flex items-center space-x-3 p-4 bg-green-50 rounded-lg border border-green-200">
                        <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="text-lg font-bold text-green-600">Tidak Ada Tunggakan</p>
                            <p class="text-sm text-gray-600">Semua pembayaran lunas</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Riwayat Pembayaran -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-6">Riwayat Pembayaran</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Periode</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Jumlah</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Tanggal Bayar</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riwayat_pembayaran->num_rows > 0): ?>
                            <?php while ($row = $riwayat_pembayaran->fetch_assoc()): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4 text-sm text-gray-800"><?php echo nama_bulan($row['periode_bulan']); ?> <?php echo $row['periode_tahun']; ?></td>
                                <td class="py-3 px-4 text-sm font-semibold text-gray-800"><?php echo format_rupiah($row['jumlah']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-600"><?php echo $row['tanggal_bayar'] ? format_tanggal($row['tanggal_bayar']) : '-'; ?></td>
                                <td class="py-3 px-4">
                                    <?php if ($row['status'] == 'lunas'): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            Lunas
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                            </svg>
                                            Belum Lunas
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-gray-500">Belum ada riwayat pembayaran</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-8 text-center">
                <svg class="w-16 h-16 text-yellow-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Belum Memiliki Kamar</h3>
                <p class="text-gray-600">Anda belum terdaftar di kamar manapun. Silakan hubungi admin untuk informasi lebih lanjut.</p>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>