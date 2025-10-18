<?php
require_once '../config.php';
check_admin();

// Load library jika ekspor
if (isset($_GET['export'])) {
    require_once '../vendor/autoload.php';
}

// Default bulan & tahun
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Ambil data pembayaran
$stmt = $conn->prepare("
    SELECT 
        p.*, 
        u_peng.nama_lengkap AS nama_penghuni,
        k.nomor_kamar,
        u.nama_lengkap AS admin_pencatat
    FROM pembayaran p
    JOIN penghuni peng ON p.penghuni_id = peng.id
    JOIN users u_peng ON peng.user_id = u_peng.id
    LEFT JOIN kamar k ON peng.kamar_id = k.id
    JOIN users u ON p.created_by = u.id
    WHERE p.periode_bulan = ? AND p.periode_tahun = ?
    ORDER BY p.created_at DESC
");
$stmt->bind_param("ii", $bulan, $tahun);
$stmt->execute();
$pembayaran_list = $stmt->get_result();

// Hitung total
$total_pemasukan = $total_tunggakan = 0;
$data_rows = [];
while ($row = $pembayaran_list->fetch_assoc()) {
    $data_rows[] = $row;
    if ($row['status'] === 'lunas') {
        $total_pemasukan += $row['jumlah'];
    } else {
        $total_tunggakan += $row['jumlah'];
    }
}

// === EKSPOR PDF ===
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    ob_clean(); // Bersihkan output sebelumnya

    $html = '
    <style>
        body { font-family: DejaVu Sans, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .badge-lunas { color: green; font-weight: bold; }
        .badge-belum { color: red; font-weight: bold; }
    </style>
    <h2 style="text-align:center">Laporan Pembayaran Kost Le Prala</h2>
    <p style="text-align:center">Periode: ' . nama_bulan($bulan) . ' ' . $tahun . '</p>
    <table>
        <thead>
            <tr>
                <th>Penghuni</th>
                <th>Kamar</th>
                <th>Periode</th>
                <th>Jumlah</th>
                <th>Tanggal Bayar</th>
                <th>Status</th>
                <th>Metode</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($data_rows as $row) {
        $status = $row['status'] === 'lunas' ? '<span class="badge-lunas">Lunas</span>' : '<span class="badge-belum">Belum Lunas</span>';
        $tgl_bayar = $row['tanggal_bayar'] ? format_tanggal($row['tanggal_bayar']) : '-';
        $metode = $row['metode_bayar'] ?: '-';

        $html .= '<tr>
            <td>' . htmlspecialchars($row['nama_penghuni']) . '</td>
            <td>Kamar ' . htmlspecialchars($row['nomor_kamar']) . '</td>
            <td>' . nama_bulan($row['periode_bulan']) . ' ' . $row['periode_tahun'] . '</td>
            <td class="text-right">' . format_rupiah($row['jumlah']) . '</td>
            <td>' . $tgl_bayar . '</td>
            <td>' . $status . '</td>
            <td>' . $metode . '</td>
        </tr>';
    }

    $html .= '</tbody></table>
    <div style="margin-top: 20px; text-align: right;">
        <p>Total Pemasukan: <strong>' . format_rupiah($total_pemasukan) . '</strong></p>
        <p>Total Tunggakan: <strong>' . format_rupiah($total_tunggakan) . '</strong></p>
    </div>';

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("Laporan_Kost_" . $bulan . "_" . $tahun . ".pdf", ["Attachment" => true]);
    exit;
}

// === EKSPOR EXCEL ===
if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    ob_clean();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan Pembayaran');

    // Header
    $sheet->setCellValue('A1', 'Penghuni');
    $sheet->setCellValue('B1', 'Kamar');
    $sheet->setCellValue('C1', 'Periode');
    $sheet->setCellValue('D1', 'Jumlah');
    $sheet->setCellValue('E1', 'Tanggal Bayar');
    $sheet->setCellValue('F1', 'Status');
    $sheet->setCellValue('G1', 'Metode');

    // Style header
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);
    $sheet->getStyle('A1:G1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFDDDDDD');

    // Data
    $row = 2;
    foreach ($data_rows as $data) {
        $sheet->setCellValue('A' . $row, $data['nama_penghuni']);
        $sheet->setCellValue('B' . $row, 'Kamar ' . $data['nomor_kamar']);
        $sheet->setCellValue('C' . $row, nama_bulan($data['periode_bulan']) . ' ' . $data['periode_tahun']);
        $sheet->setCellValue('D' . $row, $data['jumlah']);
        $sheet->setCellValue('E' . $row, $data['tanggal_bayar'] ?: '-');
        $sheet->setCellValue('F' . $row, ucfirst($data['status']));
        $sheet->setCellValue('G' . $row, $data['metode_bayar'] ?: '-');
        $row++;
    }

    // Auto-size kolom
    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Format kolom jumlah sebagai angka
    $sheet->getStyle('D2:D' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');

    // Simpan ke file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $filename = "Laporan_Kost_{$bulan}_{$tahun}.xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $writer->save('php://output');
    exit;
}
// Reset pointer
$pembayaran_list->data_seek(0);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pembayaran - Kost Le Prala</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="ml-2 text-xl font-bold text-gray-800">Kost Le Prala</span>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-4">
                        <a href="dashboard.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Dashboard</a>
                        <a href="kamar.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Kamar</a>
                        <a href="penghuni.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Penghuni</a>
                        <a href="pembayaran.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Pembayaran</a>
                        <a href="laporan.php" class="border-b-2 border-red-600 text-gray-900 inline-flex items-center px-1 pt-1 text-sm font-medium">Laporan</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="ml-3 relative">
                        <div class="flex items-center space-x-3">
                            <span class="text-sm text-gray-700">
                                <span class="font-semibold"><?php echo $_SESSION['nama_lengkap']; ?></span>
                                <span class="text-xs text-gray-500 block">Admin</span>
                            </span>
                            <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-200">
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
            <h1 class="text-3xl font-bold text-gray-900">Laporan Pembayaran</h1>
            <p class="text-gray-600 mt-1">Laporan pembayaran per periode</p>
        </div>

        <!-- Filter Form -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex gap-3 mb-6">
                <a href="?bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&export=pdf" 
                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition shadow">
                    Unduh PDF
                </a>
                <a href="?bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>&export=xlsx" 
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition shadow">
                    Ekspor Excel
                </a>
            </div>
            <form method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
                    <select name="bulan" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $bulan ? 'selected' : ''; ?>>
                                <?php echo nama_bulan($i); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
                    <input type="number" name="tahun" value="<?php echo $tahun; ?>" min="2020" max="<?php echo date('Y') + 2; ?>" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                <div class="sm:mt-6">
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg font-semibold hover:from-red-700 hover:to-red-800 transition shadow-md">
                        Tampilkan
                    </button>
                </div>
            </form>
            
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white card-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Total Pemasukan</p>
                        <p class="text-2xl font-bold mt-2"><?php echo format_rupiah($total_pemasukan); ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 text-sm">
                    <span class="text-green-100"><?php echo nama_bulan($bulan) . ' ' . $tahun; ?></span>
                </div>
            </div>

            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 text-white card-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">Total Tunggakan</p>
                        <p class="text-2xl font-bold mt-2"><?php echo format_rupiah($total_tunggakan); ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 text-sm">
                    <span class="text-orange-100">Belum dibayar</span>
                </div>
            </div>

            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white card-shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Transaksi</p>
                        <p class="text-2xl font-bold mt-2"><?php echo $pembayaran_list->num_rows; ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 text-sm">
                    <span class="text-blue-100">Semua status</span>
                </div>
            </div>
        </div>

        <!-- Laporan Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <?php if ($pembayaran_list->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penghuni</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kamar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periode</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Bayar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metode</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($row = $pembayaran_list->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['nama_penghuni']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">Kamar <?php echo htmlspecialchars($row['nomor_kamar']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                <?php echo nama_bulan($row['periode_bulan']) . ' ' . $row['periode_tahun']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php echo $row['status'] === 'lunas' ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo format_rupiah($row['jumlah']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                <?php echo $row['tanggal_bayar'] ? format_tanggal($row['tanggal_bayar']) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($row['status'] === 'lunas'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Lunas</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Belum Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                <?php echo $row['metode_bayar'] ?: '-'; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Tidak ada data laporan</h3>
                <p class="mt-1 text-sm text-gray-500">Coba ubah periode filter.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>