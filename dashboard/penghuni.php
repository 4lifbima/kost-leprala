<?php
require_once '../config.php';
check_admin();

// Handle Create/Update Penghuni
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'create') {
            $nama_lengkap = $_POST['nama_lengkap'];
            $username = $_POST['username'];
            $email = $_POST['email'];
            $no_telp = $_POST['no_telp'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $kamar_id = $_POST['kamar_id'];
            $tanggal_masuk = $_POST['tanggal_masuk'];
            $lama_sewa = $_POST['lama_sewa'];
            
            // Create user
            $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, no_telp, role, status) VALUES (?, ?, ?, ?, ?, 'penghuni', 'aktif')");
            $stmt->bind_param("sssss", $username, $password, $nama_lengkap, $email, $no_telp);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                
                // Create penghuni
                $stmt2 = $conn->prepare("INSERT INTO penghuni (user_id, kamar_id, tanggal_masuk, lama_sewa, status_sewa) VALUES (?, ?, ?, ?, 'aktif')");
                $stmt2->bind_param("iisi", $user_id, $kamar_id, $tanggal_masuk, $lama_sewa);
                
                if ($stmt2->execute()) {
                    // Update status kamar
                    $conn->query("UPDATE kamar SET status = 'terisi' WHERE id = $kamar_id");
                    
                    log_audit($conn, $_SESSION['user_id'], 'CREATE', 'penghuni', $stmt2->insert_id, null, json_encode($_POST));
                    redirect('penghuni.php', 'Penghuni berhasil ditambahkan!', 'success');
                } else {
                    // Rollback user if penghuni creation fails
                    $conn->query("DELETE FROM users WHERE id = $user_id");
                    redirect('penghuni.php', 'Gagal menambahkan penghuni!', 'error');
                }
                $stmt2->close();
            } else {
                redirect('penghuni.php', 'Username atau email sudah digunakan!', 'error');
            }
            $stmt->close();
        }
        
        if ($action == 'update') {
            $id = $_POST['id'];
            $user_id = $_POST['user_id'];
            $nama_lengkap = $_POST['nama_lengkap'];
            $email = $_POST['email'];
            $no_telp = $_POST['no_telp'];
            $kamar_id = $_POST['kamar_id'];
            $tanggal_masuk = $_POST['tanggal_masuk'];
            $lama_sewa = $_POST['lama_sewa'];
            
            // Get old kamar_id
            $old_data = $conn->query("SELECT kamar_id FROM penghuni WHERE id = $id")->fetch_assoc();
            $old_kamar_id = $old_data['kamar_id'];
            
            // Update user
            $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, no_telp = ? WHERE id = ?");
            $stmt->bind_param("sssi", $nama_lengkap, $email, $no_telp, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Update penghuni
            $stmt2 = $conn->prepare("UPDATE penghuni SET kamar_id = ?, tanggal_masuk = ?, lama_sewa = ? WHERE id = ?");
            $stmt2->bind_param("isii", $kamar_id, $tanggal_masuk, $lama_sewa, $id);
            
            if ($stmt2->execute()) {
                // Update status kamar
                if ($old_kamar_id != $kamar_id) {
                    $conn->query("UPDATE kamar SET status = 'kosong' WHERE id = $old_kamar_id");
                    $conn->query("UPDATE kamar SET status = 'terisi' WHERE id = $kamar_id");
                }
                
                log_audit($conn, $_SESSION['user_id'], 'UPDATE', 'penghuni', $id, json_encode($old_data), json_encode($_POST));
                redirect('penghuni.php', 'Penghuni berhasil diupdate!', 'success');
            } else {
                redirect('penghuni.php', 'Gagal mengupdate penghuni!', 'error');
            }
            $stmt2->close();
        }
        
        if ($action == 'nonaktifkan') {
            $id = $_POST['id'];
            $user_id = $_POST['user_id'];
            $tanggal_keluar = date('Y-m-d');
            
            // Get kamar_id
            $penghuni = $conn->query("SELECT kamar_id FROM penghuni WHERE id = $id")->fetch_assoc();
            
            // Nonaktifkan penghuni
            $stmt = $conn->prepare("UPDATE penghuni SET status_sewa = 'selesai', tanggal_keluar = ? WHERE id = ?");
            $stmt->bind_param("si", $tanggal_keluar, $id);
            $stmt->execute();
            $stmt->close();
            
            // Nonaktifkan user
            $conn->query("UPDATE users SET status = 'nonaktif' WHERE id = $user_id");
            
            // Update status kamar
            $conn->query("UPDATE kamar SET status = 'kosong' WHERE id = {$penghuni['kamar_id']}");
            
            log_audit($conn, $_SESSION['user_id'], 'NONAKTIFKAN', 'penghuni', $id);
            redirect('penghuni.php', 'Penghuni berhasil dinonaktifkan!', 'success');
        }
    }
}

// Get all penghuni
$penghuni_list = $conn->query("
    SELECT p.*, u.username, u.nama_lengkap, u.email, u.no_telp, u.status as user_status, k.nomor_kamar, k.tarif
    FROM penghuni p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN kamar k ON p.kamar_id = k.id
    WHERE p.status_sewa = 'aktif'
    ORDER BY k.nomor_kamar
");

// Get kamar kosong for dropdown
$kamar_kosong = $conn->query("SELECT * FROM kamar WHERE status = 'kosong' ORDER BY nomor_kamar");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Penghuni - Kost Le Prala</title>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span class="ml-2 text-xl font-bold text-gray-800">Kost Le Prala</span>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-4">
                        <a href="../dashboard" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Dashboard
                        </a>
                        <a href="kamar.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Kamar
                        </a>
                        <a href="penghuni.php" class="border-b-2 border-red-600 text-gray-900 inline-flex items-center px-1 pt-1 text-sm font-medium">
                            Penghuni
                        </a>
                        <a href="pembayaran.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Pembayaran
                        </a>
                        <a href="laporan.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Laporan
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="ml-3 relative">
                        <div class="flex items-center space-x-3">
                            <span class="text-sm text-gray-700">
                                <span class="font-semibold"><?php echo $_SESSION['nama_lengkap']; ?></span>
                                <span class="text-xs text-gray-500 block">Admin</span>
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

        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Management Penghuni</h1>
                <p class="text-gray-600 mt-1">Kelola data penghuni kost Anda</p>
            </div>
            <button onclick="openModal('create')" class="mt-4 sm:mt-0 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white px-6 py-3 rounded-lg font-semibold shadow-lg hover:shadow-xl transition duration-300 transform hover:-translate-y-0.5">
                + Tambah Penghuni
            </button>
        </div>

        <!-- Penghuni Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <?php if ($penghuni_list->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kamar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarif</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mulai Sewa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $no = 1; while ($row = $penghuni_list->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo $no++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($row['username']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                <?php echo $row['nomor_kamar'] ? 'Kamar ' . htmlspecialchars($row['nomor_kamar']) : '<span class="text-orange-500">Belum ditentukan</span>'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                <?php echo $row['tarif'] ? format_rupiah($row['tarif']) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                <?php echo format_tanggal($row['tanggal_masuk']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($row['user_status'] == 'aktif'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick='openModal("edit", <?php echo htmlspecialchars(json_encode($row), ENT_QUOTES); ?>)' class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menonaktifkan penghuni ini?')">
                                    <input type="hidden" name="action" value="nonaktifkan">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">Nonaktifkan</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada penghuni</h3>
                <p class="mt-1 text-sm text-gray-500">Mulai tambahkan penghuni baru.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 id="modal-title" class="text-xl font-bold text-gray-800">Tambah Penghuni Baru</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form id="penghuni-form" method="POST" class="p-6">
                <input type="hidden" name="action" id="form-action" value="create">
                <input type="hidden" name="id" id="form-id">
                <input type="hidden" name="user_id" id="form-user-id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Nama Lengkap -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" id="form-nama_lengkap" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                    </div>

                    <!-- Username -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                        <input type="text" name="username" id="form-username" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" id="form-email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                    </div>

                    <!-- No. Telepon -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">No. Telepon</label>
                        <input type="text" name="no_telp" id="form-no_telp" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                    </div>

                    <!-- Password (only for create) -->
                    <div id="password-field">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                        <input type="password" name="password" id="form-password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                    </div>

                    <!-- Kamar -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kamar *</label>
                        <select name="kamar_id" id="form-kamar_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                            <option value="">Pilih kamar kosong</option>
                            <?php while ($kamar = $kamar_kosong->fetch_assoc()): ?>
                                <option value="<?php echo $kamar['id']; ?>">Kamar <?php echo htmlspecialchars($kamar['nomor_kamar']); ?> (Rp <?php echo number_format($kamar['tarif'], 0, ',', '.'); ?>/bln)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Tanggal Masuk -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Masuk *</label>
                        <input type="date" name="tanggal_masuk" id="form-tanggal_masuk" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                    </div>

                    <!-- Lama Sewa (bulan) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Lama Sewa (bulan) *</label>
                        <input type="number" name="lama_sewa" id="form-lama_sewa" min="1" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
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
            const form = document.getElementById('penghuni-form');
            const actionInput = document.getElementById('form-action');
            const submitText = document.getElementById('submit-text');
            const passwordField = document.getElementById('password-field');

            // Reset form
            form.reset();
            if (data) {
                // Fill form for edit
                document.getElementById('form-id').value = data.id;
                document.getElementById('form-user-id').value = data.user_id;
                document.getElementById('form-nama_lengkap').value = data.nama_lengkap || '';
                document.getElementById('form-username').value = data.username || '';
                document.getElementById('form-email').value = data.email || '';
                document.getElementById('form-no_telp').value = data.no_telp || '';
                document.getElementById('form-kamar_id').value = data.kamar_id || '';
                document.getElementById('form-tanggal_masuk').value = data.tanggal_masuk || '';
                document.getElementById('form-lama_sewa').value = data.lama_sewa || '';

                actionInput.value = 'update';
                submitText.textContent = 'Update';
                passwordField.style.display = 'none';
            } else {
                // Create mode
                actionInput.value = 'create';
                submitText.textContent = 'Simpan';
                passwordField.style.display = 'block';
                document.getElementById('form-password').setAttribute('required', 'required');
            }

            document.getElementById('modal-title').textContent = mode === 'create' ? 'Tambah Penghuni Baru' : 'Edit Penghuni';
            modal.classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        // Close modal on ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>