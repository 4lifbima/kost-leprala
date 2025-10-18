<?php
require_once '../config.php';
check_admin();

// Handle Create/Update/Delete Kamar
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'create') {
            $nomor_kamar = $_POST['nomor_kamar'];
            $tarif = $_POST['tarif'];
            $fasilitas = $_POST['fasilitas'];
            
            $stmt = $conn->prepare("INSERT INTO kamar (nomor_kamar, tarif, fasilitas, status) VALUES (?, ?, ?, 'kosong')");
            $stmt->bind_param("sds", $nomor_kamar, $tarif, $fasilitas);
            
            if ($stmt->execute()) {
                log_audit($conn, $_SESSION['user_id'], 'CREATE', 'kamar', $stmt->insert_id, null, json_encode($_POST));
                redirect('kamar.php', 'Kamar berhasil ditambahkan!', 'success');
            } else {
                redirect('kamar.php', 'Gagal menambahkan kamar!', 'error');
            }
            $stmt->close();
        }
        
        if ($action == 'update') {
            $id = $_POST['id'];
            $nomor_kamar = $_POST['nomor_kamar'];
            $tarif = $_POST['tarif'];
            $fasilitas = $_POST['fasilitas'];
            
            // Get old data for audit
            $old_data = $conn->query("SELECT * FROM kamar WHERE id = $id")->fetch_assoc();
            
            $stmt = $conn->prepare("UPDATE kamar SET nomor_kamar = ?, tarif = ?, fasilitas = ? WHERE id = ?");
            $stmt->bind_param("sdsi", $nomor_kamar, $tarif, $fasilitas, $id);
            
            if ($stmt->execute()) {
                log_audit($conn, $_SESSION['user_id'], 'UPDATE', 'kamar', $id, json_encode($old_data), json_encode($_POST));
                redirect('kamar.php', 'Kamar berhasil diupdate!', 'success');
            } else {
                redirect('kamar.php', 'Gagal mengupdate kamar!', 'error');
            }
            $stmt->close();
        }
        
        if ($action == 'delete') {
            $id = $_POST['id'];
            
            // Cek apakah kamar sedang ditempati
            $check = $conn->query("SELECT * FROM penghuni WHERE kamar_id = $id AND status_sewa = 'aktif'")->num_rows;
            
            if ($check > 0) {
                redirect('kamar.php', 'Kamar tidak dapat dihapus karena masih ditempati!', 'error');
            }
            
            $old_data = $conn->query("SELECT * FROM kamar WHERE id = $id")->fetch_assoc();
            
            $stmt = $conn->prepare("DELETE FROM kamar WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                log_audit($conn, $_SESSION['user_id'], 'DELETE', 'kamar', $id, json_encode($old_data), null);
                redirect('kamar.php', 'Kamar berhasil dihapus!', 'success');
            } else {
                redirect('kamar.php', 'Gagal menghapus kamar!', 'error');
            }
            $stmt->close();
        }
    }
}

// Get all kamar
$kamar_list = $conn->query("
    SELECT k.*, 
           u.nama_lengkap as penghuni_nama,
           p.tanggal_masuk
    FROM kamar k
    LEFT JOIN penghuni p ON k.id = p.kamar_id AND p.status_sewa = 'aktif'
    LEFT JOIN users u ON p.user_id = u.id
    ORDER BY k.nomor_kamar
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Kamar - Kost Le Prala</title>
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
                        <a href="kamar.php" class="border-b-2 border-red-600 text-gray-900 inline-flex items-center px-1 pt-1 text-sm font-medium">
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
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Management Kamar</h1>
                <p class="text-gray-600 mt-1">Kelola data kamar kost</p>
            </div>
            <button onclick="openModal('create')" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center shadow-lg">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Tambah Kamar
            </button>
        </div>

        <!-- Kamar Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php while ($kamar = $kamar_list->fetch_assoc()): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition duration-300">
                <div class="relative">
                    <?php if ($kamar['status'] == 'terisi'): ?>
                        <div class="absolute top-4 right-4 bg-red-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                            Terisi
                        </div>
                    <?php else: ?>
                        <div class="absolute top-4 right-4 bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                            Kosong
                        </div>
                    <?php endif; ?>
                    <div class="bg-gradient-to-br from-red-500 to-red-600 p-8 text-center">
                        <svg class="w-16 h-16 text-white mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <h3 class="text-3xl font-bold text-white mt-4">Kamar <?php echo $kamar['nomor_kamar']; ?></h3>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 mb-1">Tarif Bulanan</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo format_rupiah($kamar['tarif']); ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-500 mb-1">Fasilitas</p>
                        <p class="text-sm text-gray-700"><?php echo $kamar['fasilitas']; ?></p>
                    </div>
                    
                    <?php if ($kamar['status'] == 'terisi' && $kamar['penghuni_nama']): ?>
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500 mb-1">Penghuni</p>
                        <p class="text-sm font-semibold text-gray-800"><?php echo $kamar['penghuni_nama']; ?></p>
                        <p class="text-xs text-gray-500">Sejak: <?php echo format_tanggal($kamar['tanggal_masuk']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex space-x-2">
                        <button onclick='editKamar(<?php echo json_encode($kamar); ?>)' 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                            Edit
                        </button>
                        <button onclick="deleteKamar(<?php echo $kamar['id']; ?>, '<?php echo $kamar['nomor_kamar']; ?>')" 
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <?php if ($kamar_list->num_rows == 0): ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <svg class="w-24 h-24 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            <h3 class="text-xl font-bold text-gray-800 mb-2">Belum Ada Data Kamar</h3>
            <p class="text-gray-600 mb-4">Mulai tambahkan kamar dengan klik tombol di atas</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal Form -->
    <div id="kamarModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">Tambah Kamar</h2>
            </div>
            
            <form id="kamarForm" method="POST" action="" class="p-6">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="kamarId">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Nomor Kamar</label>
                    <input type="text" name="nomor_kamar" id="nomor_kamar" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-red-600">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Tarif Bulanan (Rp)</label>
                    <input type="number" name="tarif" id="tarif" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-red-600">
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Fasilitas</label>
                    <textarea name="fasilitas" id="fasilitas" rows="4" required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-red-600"
                        placeholder="Contoh: AC, Kamar Mandi Dalam, WiFi, Lemari, Kasur"></textarea>
                </div>
                
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200">
                        Simpan
                    </button>
                    <button type="button" onclick="closeModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(action) {
            document.getElementById('kamarModal').classList.remove('hidden');
            document.getElementById('formAction').value = action;
            
            if (action === 'create') {
                document.getElementById('modalTitle').textContent = 'Tambah Kamar';
                document.getElementById('kamarForm').reset();
                document.getElementById('kamarId').value = '';
            }
        }
        
        function closeModal() {
            document.getElementById('kamarModal').classList.add('hidden');
        }
        
        function editKamar(kamar) {
            openModal('update');
            document.getElementById('modalTitle').textContent = 'Edit Kamar';
            document.getElementById('kamarId').value = kamar.id;
            document.getElementById('nomor_kamar').value = kamar.nomor_kamar;
            document.getElementById('tarif').value = kamar.tarif;
            document.getElementById('fasilitas').value = kamar.fasilitas;
        }
        
        function deleteKamar(id, nomor) {
            if (confirm('Apakah Anda yakin ingin menghapus Kamar ' + nomor + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Close modal on background click
        document.getElementById('kamarModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>