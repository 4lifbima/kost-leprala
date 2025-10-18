<?php
require_once '../config.php';
check_admin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $penghuni_id = $_POST['penghuni_id'];
        $periode_bulan = $_POST['periode_bulan'];
        $periode_tahun = $_POST['periode_tahun'];
        $jumlah = $_POST['jumlah'];
        $metode_bayar = !empty($_POST['metode_bayar']) ? $_POST['metode_bayar'] : null;
        $keterangan = !empty($_POST['keterangan']) ? $_POST['keterangan'] : null;
        $tanggal_bayar = !empty($_POST['tanggal_bayar']) ? $_POST['tanggal_bayar'] : null;
        $status = $tanggal_bayar ? 'lunas' : 'belum_lunas';

        if ($action == 'create') {
            $stmt = $conn->prepare("
                INSERT INTO pembayaran (penghuni_id, periode_bulan, periode_tahun, jumlah, tanggal_bayar, status, metode_bayar, keterangan, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiidsisss", $penghuni_id, $periode_bulan, $periode_tahun, $jumlah, $tanggal_bayar, $status, $metode_bayar, $keterangan, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                log_audit($conn, $_SESSION['user_id'], 'CREATE', 'pembayaran', $stmt->insert_id, null, json_encode($_POST));
                redirect('pembayaran.php', 'Pembayaran berhasil ditambahkan!', 'success');
            } else {
                redirect('pembayaran.php', 'Gagal menambahkan pembayaran!', 'error');
            }
            $stmt->close();
        }

        if ($action == 'update') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("
                UPDATE pembayaran 
                SET jumlah = ?, tanggal_bayar = ?, status = ?, metode_bayar = ?, keterangan = ?
                WHERE id = ?
            ");
            $stmt->bind_param("dssssi", $jumlah, $tanggal_bayar, $status, $metode_bayar, $keterangan, $id);
            
            if ($stmt->execute()) {
                // ✅ Perbaikan: $conn sebagai argumen pertama, bukan $conn->insert_id
                log_audit($conn, $_SESSION['user_id'], 'UPDATE', 'pembayaran', $id, null, json_encode($_POST));
                redirect('pembayaran.php', 'Pembayaran berhasil diupdate!', 'success');
            } else {
                redirect('pembayaran.php', 'Gagal mengupdate pembayaran!', 'error');
            }
            $stmt->close();
        }
    }
}

// Ambil semua pembayaran
$pembayaran_list = $conn->query("
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
    ORDER BY p.periode_tahun DESC, p.periode_bulan DESC, p.created_at DESC
");

// Ambil penghuni aktif untuk dropdown
$penghuni_aktif = $conn->query("
    SELECT peng.id, u.nama_lengkap, k.nomor_kamar, k.tarif
    FROM penghuni peng
    JOIN users u ON peng.user_id = u.id
    JOIN kamar k ON peng.kamar_id = k.id
    WHERE peng.status_sewa = 'aktif'
    ORDER BY k.nomor_kamar
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Pembayaran - Kost Le Prala</title>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="ml-2 text-xl font-bold text-gray-800">Kost Le Prala</span>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-4">
                        <a href="../dashboard" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Dashboard</a>
                        <a href="kamar.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Kamar</a>
                        <a href="penghuni.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Penghuni</a>
                        <a href="pembayaran.php" class="border-b-2 border-red-600 text-gray-900 inline-flex items-center px-1 pt-1 text-sm font-medium">Pembayaran</a>
                        <a href="laporan.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Laporan</a>
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

        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Management Pembayaran</h1>
                <p class="text-gray-600 mt-1">Catat dan kelola pembayaran kost penghuni</p>
            </div>
            <button onclick="openModal('create')" class="mt-4 sm:mt-0 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white px-6 py-3 rounded-lg font-semibold shadow-lg hover:shadow-xl transition duration-300 transform hover:-translate-y-0.5">
                + Tambah Pembayaran
            </button>
        </div>

        <!-- Pembayaran Table -->
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
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
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
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600"><?php echo format_rupiah($row['jumlah']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                <?php echo $row['tanggal_bayar'] ? format_tanggal($row['tanggal_bayar']) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($row['status'] == 'lunas'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Lunas</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Belum Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                <?php echo $row['metode_bayar'] ?: '-'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick='openModal("edit", <?php echo htmlspecialchars(json_encode($row), ENT_QUOTES); ?>)' class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada pembayaran</h3>
                <p class="mt-1 text-sm text-gray-500">Mulai catat pembayaran penghuni.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 id="modal-title" class="text-xl font-bold text-gray-800">Tambah Pembayaran Baru</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form id="pembayaran-form" method="POST" class="p-6">
                <input type="hidden" name="action" id="form-action" value="create">
                <input type="hidden" name="id" id="form-id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Penghuni -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Penghuni *</label>
                        <select name="penghuni_id" id="form-penghuni_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                            <option value="">Pilih penghuni</option>
                            <?php while ($p = $penghuni_aktif->fetch_assoc()): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['nama_lengkap']); ?> (Kamar <?php echo $p['nomor_kamar']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Periode Bulan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bulan *</label>
                        <select name="periode_bulan" id="form-periode_bulan" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo nama_bulan($i); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Periode Tahun -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tahun *</label>
                        <input type="number" name="periode_tahun" id="form-periode_tahun" min="2020" max="<?php echo date('Y') + 2; ?>" value="<?php echo date('Y'); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                    </div>

                    <!-- Jumlah -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah (Rp) *</label>
                        <input type="number" step="0.01" name="jumlah" id="form-jumlah" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                    </div>

                    <!-- Tanggal Bayar -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Bayar</label>
                        <input type="date" name="tanggal_bayar" id="form-tanggal_bayar" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                        <p class="text-xs text-gray-500 mt-1">Kosongkan untuk status "Belum Lunas"</p>
                    </div>

                    <!-- Metode Bayar -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Metode Bayar</label>
                        <input type="text" name="metode_bayar" id="form-metode_bayar" placeholder="Cash, Transfer, dll" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                    </div>

                    <!-- Keterangan -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Keterangan</label>
                        <textarea name="keterangan" id="form-keterangan" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"></textarea>
                    </div>
                </div>

                <div class="mt-8 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Batal</button>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg font-semibold hover:from-red-700 hover:to-red-800 transition shadow-md">
                        <span id="submit-text">Simpan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(mode, data = null) {
            const modal = document.getElementById('modal');
            const form = document.getElementById('pembayaran-form');
            const actionInput = document.getElementById('form-action');
            const submitText = document.getElementById('submit-text');

            form.reset();
            if (data) {
                document.getElementById('form-id').value = data.id;
                document.getElementById('form-penghuni_id').value = data.penghuni_id;
                document.getElementById('form-periode_bulan').value = data.periode_bulan;
                document.getElementById('form-periode_tahun').value = data.periode_tahun;
                document.getElementById('form-jumlah').value = data.jumlah;
                document.getElementById('form-tanggal_bayar').value = data.tanggal_bayar || '';
                document.getElementById('form-metode_bayar').value = data.metode_bayar || '';
                document.getElementById('form-keterangan').value = data.keterangan || '';

                actionInput.value = 'update';
                submitText.textContent = 'Update';
                document.getElementById('modal-title').textContent = 'Edit Pembayaran';
            } else {
                actionInput.value = 'create';
                submitText.textContent = 'Simpan';
                document.getElementById('modal-title').textContent = 'Tambah Pembayaran Baru';
            }
            modal.classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>