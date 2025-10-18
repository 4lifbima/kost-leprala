<?php
// config.php
session_start();

// Koneksi Database
try {
    $conn = new mysqli('localhost', 'root', '', 'kost_le_prala');
    
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Fungsi untuk mencatat audit trail
function log_audit($conn, $user_id, $action, $table_name, $record_id = null, $old_data = null, $new_data = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, action, table_name, record_id, old_data, new_data, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $user_id, $action, $table_name, $record_id, $old_data, $new_data, $ip_address);
    $stmt->execute();
    $stmt->close();
}

// Fungsi untuk format rupiah
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Fungsi untuk format tanggal Indonesia
function format_tanggal($tanggal) {
    if (!$tanggal) return '-';
    
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    $split = explode('-', $tanggal);
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}

// Fungsi untuk nama bulan
function nama_bulan($bulan) {
    $bulan_array = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    return $bulan_array[$bulan];
}

// Fungsi untuk mengecek login
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
}

// Fungsi untuk mengecek role admin
function check_admin() {
    check_login();
    if ($_SESSION['role'] != 'admin') {
        header('Location: dashboard.php');
        exit();
    }
}

// Fungsi untuk redirect dengan pesan
function redirect($url, $message = '', $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header('Location: ' . $url);
    exit();
}

// Fungsi untuk menampilkan pesan
function show_message() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'success';
        $bg_color = $type == 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
        
        echo '<div class="' . $bg_color . ' border px-4 py-3 rounded relative mb-4" role="alert">';
        echo '<span class="block sm:inline">' . $_SESSION['message'] . '</span>';
        echo '</div>';
        
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}
?>